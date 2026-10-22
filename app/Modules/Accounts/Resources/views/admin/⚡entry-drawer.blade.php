<?php

use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Money\Money;
use App\Foundation\Text\SearchText;
use App\Modules\Accounts\Areas\Admin\Forms\EntryForm;
use App\Modules\Accounts\Authorization\AccountPermission;
use App\Modules\Accounts\Contracts\AccountLedgerInterface;
use App\Modules\Accounts\Contracts\Data\ChargeRequest;
use App\Modules\Accounts\Contracts\Data\EntryOpening;
use App\Modules\Accounts\Contracts\Data\PaymentRequest;
use App\Modules\Accounts\Contracts\Data\PostedOperation;
use App\Modules\Accounts\Contracts\Data\VoidRequest;
use App\Modules\Accounts\Contracts\Enums\CorrectionReason;
use App\Modules\Accounts\Contracts\Enums\EntryKind;
use App\Modules\Accounts\Contracts\Enums\OverpaymentChoice;
use App\Modules\Accounts\Contracts\Enums\TransactionType;
use App\Modules\Accounts\Contracts\Exceptions\AccountChangedMeanwhile;
use App\Modules\Accounts\Contracts\Exceptions\CreditLimitExceeded;
use App\Modules\Accounts\Domain\Actions\CorrectEntry;
use App\Modules\Accounts\Domain\Exceptions\DiscountExceedsBalance;
use App\Modules\Accounts\Domain\Services\EntryLines;
use App\Modules\Accounts\Contracts\Exceptions\EntryAlreadyVoided;
use App\Modules\Accounts\Contracts\Exceptions\NothingOwed;
use App\Modules\Accounts\Contracts\Exceptions\OverpaymentNotAllowed;
use App\Modules\Accounts\Contracts\Exceptions\PriceChangedMeanwhile;
use App\Modules\Accounts\Contracts\Exceptions\ProductUnavailable;
use App\Modules\Accounts\Database\Models\AccountTransaction;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use App\Modules\Accounts\Domain\Queries\PopularProducts;
use App\Modules\Accounts\Domain\Services\AccountSettings;
use App\Modules\Accounts\Domain\Services\ChargeLinePricer;
use App\Modules\Catalog\Contracts\CategoryRepositoryInterface;
use App\Modules\Catalog\Contracts\Data\CategoryData;
use App\Modules\Catalog\Contracts\Data\ProductData;
use App\Modules\Catalog\Contracts\ProductRepositoryInterface;
use App\Modules\Catalog\Contracts\Enums\SaleUnit;
use App\Modules\Customers\Contracts\CustomerGroupRepositoryInterface;
use App\Modules\Customers\Contracts\CustomerRepositoryInterface;
use App\Modules\Customers\Contracts\Data\CustomerData;
use App\Modules\Customers\Contracts\Data\CustomerGroupData;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;
use Livewire\Component;

/**
 * The drawer that records «أخذ» and «دفع» for one customer, from wherever the
 * counter is — the list beside it stays in view.
 *
 * Each opening is one operation: its id goes with the save, so a double press
 * or a retry posts once. Before it posts it asks — never refuses — when the
 * same amount was recorded for the same customer moments ago. A payment
 * carries the balance it was decided on, and is turned back with the new one
 * when a colleague posted since. Ten seconds after a save, the toast offers to
 * undo it: a void, with the reason «undone at once».
 *
 * Opened from an entry's details, it corrects that entry instead: filled
 * with what it was, it asks why, and posts the reversal and the replacement
 * as one operation.
 */
new class extends Component {
    public EntryForm $form;

    public ?int $customerId = null;

    /** The id of the save this drawer makes, made once per opening. */
    public string $operationId = '';

    /** The account's latest entry when the drawer opened: what the person saw. */
    public ?int $seenLastEntryId = null;

    /** What the counter typed in the search box, not yet a line. */
    public string $search = '';

    /**
     * An entry just like this one, recorded moments ago, that the person is
     * asked about before posting.
     *
     * @var array{by: string, entry: string, ago: string}|null
     */
    public ?array $twin = null;

    public bool $twinConfirmed = false;

    /** Why the last save was turned back, in words. */
    public ?string $problem = null;

    /**
     * The last save, for the toast and its undo.
     *
     * @var array{message: string, entries: list<int>, undoable: bool}|null
     */
    public ?array $posted = null;

    /**
     * The entry being corrected, when the drawer opened to put one right:
     * its id, its kind, which way it moved the balance, and how it read.
     *
     * @var array{id: int, kind: string, sign: int, amount: int, label: string}|null
     */
    public ?array $correcting = null;

    public string $correctionReason = '';

    /**
     * Open for one customer. Another screen may hand it what it already
     * knows — see EntryOpening: an amount (a charge's one line, named by
     * `description`, or a payment's sum), a note, and the operation id the
     * save is to carry. It fills the form; the person still saves it.
     */
    #[On(EntryOpening::EVENT)]
    public function open(int $customerId, string $kind = 'charge', ?string $amount = null, string $description = '', string $note = '', ?string $operationId = null): void
    {
        $this->authorizePosting();

        $account = CustomerAccount::query()->where('customer_id', $customerId)->first();

        abort_if($account === null, 404);

        $this->form->reset();
        $this->resetErrorBag();
        $this->reset('search', 'twin', 'twinConfirmed', 'problem', 'correcting', 'correctionReason');
        unset($this->customer, $this->account, $this->groups);

        $this->customerId = $customerId;
        $this->form->kind = $kind === 'payment' ? 'payment' : 'charge';
        $this->operationId = $operationId !== null && Str::isUuid($operationId) ? $operationId : (string) Str::uuid();
        $this->seenLastEntryId = $account->last_entry_id;
        $this->form->note = Str::limit(trim($note), 500, '');

        $handed = $amount === null ? null : EntryForm::money($amount);

        if ($handed !== null && $this->form->isCharge()) {
            $this->form->addFreeLine(Str::limit(trim($description), 255, ''), $handed->toDecimalString());
        } elseif ($handed !== null) {
            $this->form->amount = $handed->toDecimalString();
        }

        $this->dispatch('overlay-show', id: 'entry-drawer');
    }

    /**
     * Open on an entry to correct it: a charge as its lines, anything else
     * as its amount. The balance it was decided on is the one the details
     * drawer showed.
     */
    #[On('open-correction')]
    public function openCorrection(int $entryId, ?int $expectedLastEntryId = null): void
    {
        $administrator = $this->authorizePosting();

        $entry = AccountTransaction::query()->with('items')->find($entryId);

        abort_unless($entry !== null && $entry->kind->isCorrectable() && $entry->reversal()->doesntExist() && Gate::forUser($administrator)->allows('correct', $entry), 403);

        $account = CustomerAccount::query()->findOrFail($entry->account_id);

        $this->form->reset();
        $this->resetErrorBag();
        $this->reset('search', 'twin', 'twinConfirmed', 'problem', 'correctionReason');
        unset($this->customer, $this->account, $this->groups);

        $this->customerId = $account->customer_id;
        $this->operationId = (string) Str::uuid();
        $this->seenLastEntryId = $expectedLastEntryId ?? $account->last_entry_id;
        $this->correcting = [
            'id' => $entry->getKey(),
            'kind' => $entry->kind->value,
            'sign' => $entry->type === TransactionType::Debit ? 1 : -1,
            'amount' => $entry->amount->minorUnits,
            'label' => $entry->kind->label().' '.$entry->amount->toDisplayString().' — '.local_time($entry->created_at)->locale(app()->getLocale())->isoFormat('D MMMM, LT'),
        ];

        if ($entry->kind === EntryKind::Charge) {
            $this->form->kind = 'charge';
            $this->form->fillFromItems($entry->items, app(EntryLines::class)->productsOf($entry), $this->categories());
        } else {
            $this->form->kind = 'payment';
            $this->form->amount = $entry->amount->toDecimalString();
        }

        $this->form->note = (string) $entry->description;

        $this->dispatch('overlay-show', id: 'entry-drawer');
    }

    #[Computed]
    public function customer(): ?CustomerData
    {
        return $this->customerId === null ? null : app(CustomerRepositoryInterface::class)->find($this->customerId);
    }

    #[Computed]
    public function account(): ?CustomerAccount
    {
        return $this->customerId === null ? null : CustomerAccount::query()->where('customer_id', $this->customerId)->first();
    }

    /**
     * @return list<CustomerGroupData>
     */
    #[Computed]
    public function groups(): array
    {
        return $this->customerId === null ? [] : (app(CustomerGroupRepositoryInterface::class)->getForCustomers([$this->customerId])->get($this->customerId) ?? []);
    }

    /**
     * What this save records, as far as it can be told yet.
     */
    #[Computed]
    public function recorded(): ?Money
    {
        if ($this->form->isCharge()) {
            return $this->form->chargeTotal();
        }

        $amount = $this->form->paymentAmount();

        if ($amount === null || $this->overpaidBy === null) {
            return $amount;
        }

        return $this->form->overpaymentChoice() === OverpaymentChoice::KeepAsCredit ? $amount : $this->owed;
    }

    /**
     * What the customer owes now, never below zero.
     */
    #[Computed]
    public function owed(): Money
    {
        $outstanding = $this->account?->outstanding ?? Money::zero();

        return $outstanding->isPositive() ? $outstanding : Money::zero();
    }

    /**
     * How much more than is owed a payment hands over, or null when it does not.
     */
    #[Computed]
    public function overpaidBy(): ?Money
    {
        $amount = $this->form->isCharge() || $this->correcting !== null ? null : $this->form->paymentAmount();

        return $amount !== null && $amount->isGreaterThan($this->owed) ? $amount->minus($this->owed) : null;
    }

    /**
     * The balance after the save, or null while nothing is complete.
     */
    #[Computed]
    public function after(): ?Money
    {
        $before = $this->account?->outstanding;
        $recorded = $this->recorded;

        if ($before === null || $recorded === null) {
            return null;
        }

        // A correction takes back what the entry did, and does it again right.
        if ($this->correcting !== null) {
            $sign = $this->correcting['sign'];

            return $before->minus(Money::fromMinorUnits($sign * $this->correcting['amount']))->plus(Money::fromMinorUnits($sign * $recorded->minorUnits));
        }

        if (! $this->form->isCharge()) {
            return $before->minus($recorded);
        }

        return $before->plus($recorded)->minus($this->form->paidNowAmount() ?? Money::zero());
    }

    /**
     * What this customer usually takes — or, with no history yet, what the
     * shop sells most — still for sale, the most first.
     *
     * @return array{products: list<ProductData>, fromShop: bool}
     */
    #[Computed]
    public function usual(): array
    {
        if ($this->account === null) {
            return ['products' => [], 'fromShop' => false];
        }

        $popular = app(PopularProducts::class);
        $ids = $popular->forAccount((int) $this->account->getKey());
        $fromShop = $ids === [];

        if ($fromShop) {
            $ids = array_slice($popular->inShop(), 0, 6);
        }

        $products = app(ProductRepositoryInterface::class)->findMany($ids);

        return [
            'products' => array_values(array_filter(
                array_map(static fn (int $id): ?ProductData => $products->get($id), $ids),
                static fn (?ProductData $product): bool => $product?->isActive === true,
            )),
            'fromShop' => $fromShop,
        ];
    }

    /**
     * The shelves, in the owner's order, for narrowing the search by one.
     *
     * @return Collection<int, CategoryData>
     */
    #[Computed]
    public function shelves(): Collection
    {
        return $this->categories()->filter(static fn (CategoryData $category): bool => $category->parentId === null)->take(8);
    }

    /**
     * The products the picker searches, as the browser holds them; only
     * whether they are still current when the version it holds is given.
     * Nothing is drawn again for it.
     *
     * @return array<string, mixed>
     */
    #[Renderless]
    public function pickerCatalog(?string $version = null): array
    {
        $this->authorizePosting();

        $products = app(ProductRepositoryInterface::class);
        $current = $products->getCatalogVersion();

        if ($version === $current) {
            return ['version' => $current, 'fresh' => true];
        }

        $categories = $this->categories();
        $active = $products->getActive();
        $popular = array_flip(app(PopularProducts::class)->inShop());
        $perShelf = $active->countBy(static fn (ProductData $product): int => $product->categoryId);

        return [
            'version' => $current,
            'items' => $active->map(static function (ProductData $product) use ($categories, $popular): array {
                $shelf = $categories->get($product->categoryId)?->name ?? '';

                return [
                    'id' => $product->id,
                    'label' => $product->name,
                    'detail' => trim($shelf.' · '.$product->unit->label(), ' ·'),
                    'aside' => $product->price->toDisplayString(),
                    // A word of the shelf finds what is on it: «ألبان» finds the cheese.
                    'terms' => trim($product->searchName.' '.SearchText::normalize($shelf)),
                    'group' => $product->categoryId,
                    'rank' => $popular[$product->id] ?? null,
                ];
            })->values()->all(),
            'groups' => $categories
                ->filter(static fn (CategoryData $category): bool => $perShelf->has($category->id))
                ->map(static fn (CategoryData $category): array => ['id' => $category->id, 'label' => $category->name, 'count' => $perShelf->get($category->id)])
                ->values()
                ->all(),
        ];
    }

    /**
     * A product chosen from the picker or the suggestions: one of it, or the
     * quantity typed before it («3*»). A weighed one waits for its weight.
     */
    public function addProduct(int $productId, string $quantity = ''): void
    {
        $this->authorizePosting();

        $product = app(ProductRepositoryInterface::class)->find($productId);

        if ($product === null || ! $product->isActive) {
            $this->problem = __('accounts::module.entry.unavailable');

            return;
        }

        $key = $this->form->addProduct(
            $product,
            $this->categories()->get($product->categoryId)?->name,
            ($quantity === '' ? null : EntryForm::quantity($quantity, $product->unit)) ?? SaleUnit::SCALE,
        );

        $this->search = '';
        $this->resetErrorBag('form.lines');

        if ($product->unit->allowsFractions() && $quantity === '') {
            // «One kilo» is seldom right: the weight is the next thing typed.
            $this->js("const field = document.getElementById('line-quantity-{$key}'); field?.focus(); field?.select()");
        }
    }

    /**
     * Why an entry is corrected rather than recorded again: its amount or
     * its lines were wrong, or something else. Recorded twice, or on the
     * wrong customer, are a void and a move.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function correctionReasons(): array
    {
        return collect([CorrectionReason::WrongAmount, CorrectionReason::WrongItems, CorrectionReason::Other])
            ->mapWithKeys(fn (CorrectionReason $reason): array => [$reason->value => $reason->label()])
            ->all();
    }

    #[Computed]
    public function keepsCredit(): bool
    {
        return app(AccountSettings::class)->allowsOverpayment();
    }

    public function updatedFormKind(): void
    {
        $this->reset('twin', 'twinConfirmed', 'problem');
        $this->resetErrorBag();
    }

    public function updatedForm(): void
    {
        // Whatever was asked about was asked about these figures.
        $this->reset('twin', 'twinConfirmed');
    }

    /**
     * Enter in the search box: a number is an amount with no product to it;
     * a word is a free line by that name, waiting for its amount.
     */
    public function addFromSearch(): void
    {
        $typed = trim($this->search);

        if ($typed === '') {
            return;
        }

        $amount = EntryForm::money($typed);

        if ($amount !== null) {
            $this->form->addFreeLine('', $amount->toDecimalString());
        } else {
            // A name with no amount yet: the amount is the next thing typed.
            $key = $this->form->addFreeLine($typed);
            $this->js("document.getElementById('line-amount-{$key}')?.focus()");
        }

        $this->search = '';
        $this->resetErrorBag('form.lines');
    }

    /**
     * A line with no product: its amount takes the focus, and a line left
     * unnamed is called what the owner calls such lines.
     */
    public function addFreeLine(): void
    {
        $key = $this->form->addFreeLine('');
        $this->js("document.getElementById('line-amount-{$key}')?.focus()");
    }

    /**
     * What a line with no name is called: the owner's word for it.
     */
    #[Computed]
    public function freeLineName(): string
    {
        return app(AccountSettings::class)->freeLineName();
    }

    /**
     * Which way this entry moves the balance — up for a charge, down for a
     * payment — so the drawer wears that direction's colour.
     */
    #[Computed]
    public function flow(): string
    {
        if ($this->correcting !== null) {
            return $this->correcting['sign'] > 0 ? 'flow-debit' : 'flow-credit';
        }

        return $this->form->isCharge() ? 'flow-debit' : 'flow-credit';
    }

    public function removeLine(string $key): void
    {
        $this->form->removeLine($key);
    }

    public function step(string $key, int $direction): void
    {
        $this->form->step($key, $direction > 0 ? 1 : -1);
    }

    public function sellByAmount(string $key, bool $byAmount): void
    {
        $this->form->sellByAmount($key, $byAmount);
    }

    public function setQuantity(string $key, string $quantity): void
    {
        $this->form->setQuantity($key, $quantity);
    }

    /**
     * Whether a line is a product weighed or measured, rather than counted.
     *
     * @param  array{unit: string|null, product: int|null}  $line
     */
    public function isLoose(array $line): bool
    {
        return $line['product'] !== null && (SaleUnit::tryFrom((string) $line['unit'])?->allowsFractions() ?? false);
    }

    public function unitLabel(?string $unit): string
    {
        return SaleUnit::tryFrom((string) $unit)?->label() ?? '';
    }

    /**
     * What the amount on a line sold by amount buys, as a person reads it:
     * «0.045 kg».
     *
     * @param  array{byAmount: bool, amount: string, price: string, unit: string|null}  $line
     */
    public function approxQuantity(array $line): ?string
    {
        $amount = $line['byAmount'] ? EntryForm::money($line['amount']) : null;
        $price = EntryForm::money($line['price']);

        if ($amount === null || $price === null || ! $amount->isPositive()) {
            return null;
        }

        $quantity = ChargeLinePricer::quantityFor($amount, $price);

        return $quantity > 0 ? EntryForm::quantityText($quantity).' '.$this->unitLabel($line['unit']) : null;
    }

    public function payAll(): void
    {
        $this->form->amount = $this->owed->toDecimalString();
    }

    public function save(AccountLedgerInterface $ledger): void
    {
        $administrator = $this->authorizePosting();
        $this->problem = null;
        $this->resetErrorBag();

        if ($this->customerId === null || $this->account === null) {
            return;
        }

        // Ctrl+Enter with an amount still in the box: it is the charge.
        if ($this->form->isCharge() && trim($this->search) !== '') {
            $this->addFromSearch();
        }

        $this->form->isCharge() ? $this->form->chargeLines($this->freeLineName) : $this->form->validPayment();

        if ($this->correcting !== null) {
            $this->saveCorrection($administrator);

            return;
        }

        if (! $this->twinConfirmed && ($this->twin = $this->recentTwin()) !== null) {
            return;
        }

        try {
            $operation = $this->form->isCharge()
                ? $ledger->charge(new ChargeRequest(
                    customerId: $this->customerId,
                    postedBy: (int) $administrator->getKey(),
                    operationId: $this->operationId,
                    lines: $this->form->chargeLines($this->freeLineName),
                    note: filled($this->form->note) ? trim($this->form->note) : null,
                    paidNow: $this->form->paidNowAmount(),
                ))
                : $ledger->payment(new PaymentRequest(
                    customerId: $this->customerId,
                    postedBy: (int) $administrator->getKey(),
                    operationId: $this->operationId,
                    amount: $this->form->validPayment(),
                    note: filled($this->form->note) ? trim($this->form->note) : null,
                    overpayment: $this->overpaidBy === null ? OverpaymentChoice::Refuse : $this->form->overpaymentChoice(),
                    expectedLastEntryId: $this->seenLastEntryId,
                ));
        } catch (AccountChangedMeanwhile $changed) {
            $this->seenLastEntryId = $changed->lastEntryId;
            unset($this->account);
            $this->problem = __('accounts::module.entry.changed', ['balance' => $this->inWords($changed->outstanding)]);

            return;
        } catch (CreditLimitExceeded $limit) {
            $this->problem = __('accounts::module.entry.over_limit', ['limit' => $limit->creditLimit->toDisplayString(), 'balance' => $this->inWords($limit->attemptedOutstanding)]);

            return;
        } catch (PriceChangedMeanwhile $price) {
            $this->problem = __('accounts::module.entry.price_changed', ['from' => $price->seen->toDisplayString(), 'to' => $price->current->toDisplayString()]);

            return;
        } catch (ProductUnavailable) {
            $this->problem = __('accounts::module.entry.unavailable');

            return;
        } catch (NothingOwed|OverpaymentNotAllowed) {
            $this->problem = __('accounts::module.entry.overpayment_refused');

            return;
        } catch (InvalidArgumentException) {
            $this->problem = __('accounts::module.entry.invalid');

            return;
        }

        $this->announce($operation);
    }

    /**
     * Post the correction: the reversal of the entry and what it should have
     * been, in one operation, against the balance the person saw.
     */
    private function saveCorrection(AdminUser $administrator): void
    {
        $this->validate(
            ['correctionReason' => ['required', Rule::in(array_keys($this->correctionReasons))]],
            attributes: ['correctionReason' => __('accounts::module.details.reason')],
        );

        $charge = $this->correcting['kind'] === EntryKind::Charge->value;

        try {
            $operation = app(CorrectEntry::class)->handle(
                entryId: $this->correcting['id'],
                postedBy: (int) $administrator->getKey(),
                operationId: $this->operationId,
                reason: CorrectionReason::from($this->correctionReason),
                amount: $charge ? null : $this->form->validPayment(),
                lines: $charge ? $this->form->chargeLines($this->freeLineName) : [],
                note: filled($this->form->note) ? trim($this->form->note) : null,
                expectedLastEntryId: $this->seenLastEntryId,
            );
        } catch (AccountChangedMeanwhile $changed) {
            $this->seenLastEntryId = $changed->lastEntryId;
            unset($this->account);
            $this->problem = __('accounts::module.entry.changed', ['balance' => $this->inWords($changed->outstanding)]);

            return;
        } catch (PriceChangedMeanwhile $price) {
            $this->problem = __('accounts::module.entry.price_changed', ['from' => $price->seen->toDisplayString(), 'to' => $price->current->toDisplayString()]);

            return;
        } catch (ProductUnavailable) {
            $this->problem = __('accounts::module.entry.unavailable');

            return;
        } catch (EntryAlreadyVoided) {
            $this->problem = __('accounts::module.details.why_voided');

            return;
        } catch (DiscountExceedsBalance|InvalidArgumentException) {
            $this->problem = __('accounts::module.entry.invalid');

            return;
        }

        $this->posted = [
            'message' => __('accounts::module.entry.corrected', ['balance' => $this->inWords($operation->outstandingAfter())]),
            'entries' => [],
            'undoable' => false,
        ];

        $this->form->reset();
        $this->reset('search', 'twin', 'twinConfirmed', 'problem', 'correcting', 'correctionReason');
        $this->operationId = (string) Str::uuid();

        $this->dispatch('overlay-hide', id: 'entry-drawer');
        $this->dispatch('entry-posted');
        $this->dispatch('overlay-show', id: 'entry-toast');
    }

    /**
     * «Yes, a new one»: record it after all.
     */
    public function confirmTwin(AccountLedgerInterface $ledger): void
    {
        $this->twinConfirmed = true;
        $this->save($ledger);
    }

    /**
     * «No, the same one»: nothing is recorded, and the drawer closes.
     */
    public function dropTwin(): void
    {
        $this->form->reset();
        $this->reset('twin', 'twinConfirmed', 'search');
        $this->dispatch('overlay-hide', id: 'entry-drawer');
    }

    /**
     * Undo the last save: void its entries, the last first, each with the
     * reason «undone at once» — and only while no one has posted since.
     */
    public function undo(AccountLedgerInterface $ledger): void
    {
        $administrator = $this->authorizePosting();

        if ($this->posted === null || ! $this->posted['undoable']) {
            return;
        }

        $entries = AccountTransaction::query()->whereKey($this->posted['entries'])->orderByDesc('id')->get();
        $expected = $entries->first()?->getKey();

        try {
            foreach ($entries as $entry) {
                abort_unless(Gate::forUser($administrator)->allows('correct', $entry), 403);

                $expected = $ledger->void(new VoidRequest(
                    entryId: $entry->getKey(),
                    postedBy: (int) $administrator->getKey(),
                    operationId: (string) Str::uuid(),
                    reason: CorrectionReason::Undo,
                    expectedLastEntryId: $expected,
                ))->last()->entryId;
            }
        } catch (AccountChangedMeanwhile) {
            $this->posted = ['message' => __('accounts::module.entry.undo_refused'), 'entries' => [], 'undoable' => false];
            $this->dispatch('overlay-show', id: 'entry-toast');

            return;
        } catch (EntryAlreadyVoided) {
            // Pressed twice: the first press undid it.
        }

        $this->posted = ['message' => __('accounts::module.entry.undone'), 'entries' => [], 'undoable' => false];
        $this->dispatch('entry-posted');
        $this->dispatch('overlay-show', id: 'entry-toast');
    }

    /**
     * A balance in the counter's words: «عليه 1,250.00», «له 50.00», «خالص».
     */
    public function inWords(Money $balance): string
    {
        return match (true) {
            $balance->isPositive() => __('accounts::module.balance.owes').' '.$balance->toDisplayString(),
            $balance->isNegative() => __('accounts::module.balance.in_credit').' '.$balance->negated()->toDisplayString(),
            default => __('accounts::module.balance.settled'),
        };
    }

    /**
     * The last digits of a phone, enough to tell two «محمد أحمد» apart.
     */
    public function phoneTail(?string $phone): ?string
    {
        return $phone === null ? null : '…'.substr($phone, -4);
    }

    /**
     * The same amount, of the same kind, for the same customer, recorded in
     * the last moments and still standing — or null.
     *
     * @return array{by: string, entry: string, ago: string}|null
     */
    private function recentTwin(): ?array
    {
        $amount = $this->recorded;
        $kind = $this->form->isCharge() ? EntryKind::Charge : EntryKind::Payment;

        if ($amount === null || $this->account === null) {
            return null;
        }

        $twin = AccountTransaction::query()
            ->where('account_id', $this->account->getKey())
            ->where('kind', $kind)
            ->where('amount', $amount->minorUnits)
            ->where('created_at', '>=', CarbonImmutable::now()->subSeconds(app(AccountSettings::class)->duplicateWarningSeconds()))
            ->whereDoesntHave('reversal')
            ->latest('id')
            ->first();

        if ($twin === null) {
            return null;
        }

        return [
            'by' => AdminUser::query()->find($twin->created_by)?->name ?? '—',
            'entry' => $kind->label().' '.$amount->toDisplayString(),
            'ago' => CarbonImmutable::instance($twin->created_at)->locale(app()->getLocale())->diffForHumans(['options' => CarbonInterface::JUST_NOW]),
        ];
    }

    private function announce(PostedOperation $operation): void
    {
        $customer = $this->customer;
        $first = $operation->first();

        $this->posted = [
            'message' => __($first->kind === EntryKind::Payment ? 'accounts::module.entry.posted_payment' : 'accounts::module.entry.posted_charge', [
                'name' => $customer->name ?? '',
                'amount' => $first->amount->toDisplayString(),
                'balance' => $this->inWords($operation->outstandingAfter()),
            ]),
            'entries' => array_map(static fn ($entry): int => $entry->entryId, $operation->entries),
            'undoable' => true,
        ];

        $this->form->reset();
        $this->reset('search', 'twin', 'twinConfirmed', 'problem');
        $this->operationId = (string) Str::uuid();

        $this->dispatch('overlay-hide', id: 'entry-drawer');
        $this->dispatch('entry-posted');
        $this->dispatch('overlay-show', id: 'entry-toast');
    }

    /**
     * @return Collection<int, CategoryData>
     */
    private function categories(): Collection
    {
        return app(CategoryRepositoryInterface::class)->getAll();
    }

    private function authorizePosting(): AdminUser
    {
        $administrator = Area::Admin->user();

        abort_unless($administrator instanceof AdminUser && $administrator->can(AccountPermission::Post->value), 403);

        return $administrator;
    }
}; ?>

<div>
    <div
        class="offcanvas offcanvas-end offcanvas-wide"
        tabindex="-1"
        id="entry-drawer"
        aria-labelledby="entry-drawer-title"
        data-ctrl-enter
        wire:ignore.self
    >
        <div class="offcanvas-header entry-header {{ $this->flow }}">
            <h2 class="offcanvas-title h5 d-flex align-items-center gap-2" id="entry-drawer-title">
                <span class="entry-title-icon" aria-hidden="true"><i @class(['bi', 'bi-bag-plus' => $form->isCharge(), 'bi-cash-coin' => ! $form->isCharge()])></i></span>
                @if ($correcting)
                    {{ __('accounts::module.entry.title_correct') }}
                @else
                    {{ $form->isCharge() ? __('accounts::module.entry.title_charge') : __('accounts::module.entry.title_payment') }}
                @endif
            </h2>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('shell.actions.close') }}"></button>
        </div>

        @if ($this->customer && $this->account)
            <form
                id="entry-form"
                class="offcanvas-body entry-form d-flex flex-column gap-3"
                wire:submit="save"
                novalidate
                @if ($form->lines !== []) data-confirm-close="{{ trans_choice('accounts::module.entry.confirm_close', count($form->lines), ['count' => count($form->lines)]) }}" @endif
            >
                {{-- Who this is for, and where they stand now: in view the whole time. --}}
                <div class="entry-customer">
                    <x-admin::ui.avatar :name="$this->customer->name" size="sm" />
                    <div class="min-w-0">
                        <div class="fw-semibold text-break" dir="auto">{{ $this->customer->name }}</div>
                        <div class="small text-body-secondary d-flex flex-wrap gap-1 align-items-center">
                            @if ($this->customer->phone)
                                <span class="num" dir="ltr">{{ $this->phoneTail($this->customer->phone) }}</span>
                            @endif
                            @foreach ($this->groups as $member)
                                <x-accounts::admin.group-chip :group="$member" wire:key="drawer-group-{{ $member->id }}" />
                            @endforeach
                        </div>
                    </div>
                    <div class="ms-auto text-end">
                        <div class="small text-body-secondary">{{ __('accounts::module.entry.now') }}</div>
                        <x-accounts::admin.balance :amount="$this->account->outstanding" />
                    </div>
                </div>

                @if ($correcting)
                    {{-- What is being put right. --}}
                    <div class="p-2 rounded-3 border border-primary-subtle bg-primary-subtle text-primary-emphasis">
                        <div class="small fw-semibold">{{ __('accounts::module.entry.correcting') }}</div>
                        <div dir="auto">{{ $correcting['label'] }}</div>
                    </div>
                @else
                    {{-- What happened: two big choices, in words and in their colours. --}}
                    <fieldset>
                        <legend class="visually-hidden">{{ __('accounts::module.entry.kind') }}</legend>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="radio" class="btn-check" id="entry-kind-charge" value="charge" wire:model.live="form.kind">
                                <label class="btn btn-flow flow-debit w-100 py-2 entry-kind" for="entry-kind-charge">
                                    <span class="d-block fs-5"><i class="bi bi-check2 chip-tick me-1" aria-hidden="true"></i><i class="bi bi-bag-plus me-1" aria-hidden="true"></i>{{ __('accounts::module.entry.kind_charge') }}</span>
                                    <span class="d-block small fw-normal">{{ __('accounts::module.entry.kind_charge_hint') }}</span>
                                </label>
                            </div>
                            <div class="col-6">
                                <input type="radio" class="btn-check" id="entry-kind-payment" value="payment" wire:model.live="form.kind">
                                <label class="btn btn-flow flow-credit w-100 py-2 entry-kind" for="entry-kind-payment">
                                    <span class="d-block fs-5"><i class="bi bi-check2 chip-tick me-1" aria-hidden="true"></i><i class="bi bi-cash-coin me-1" aria-hidden="true"></i>{{ __('accounts::module.entry.kind_payment') }}</span>
                                    <span class="d-block small fw-normal">{{ __('accounts::module.entry.kind_payment_hint') }}</span>
                                </label>
                            </div>
                        </div>
                    </fieldset>
                @endif

                @if ($form->isCharge())
                    <div>
                        <label for="entry-search" class="entry-label"><i class="bi bi-search" aria-hidden="true"></i>{{ __('accounts::module.entry.search') }}</label>
                        {{-- Searched in the browser by resources/js/admin/modules/search-picker.js,
                             over what pickerCatalog() hands it; Enter chooses, or adds what was typed. --}}
                        <div class="picker">
                            <input
                                id="entry-search"
                                type="text"
                                class="form-control form-control-lg"
                                wire:model="search"
                                wire:ignore.self
                                placeholder="{{ __('accounts::module.entry.search_placeholder') }}"
                                autocomplete="off"
                                dir="auto"
                                data-autofocus
                                data-search-picker
                                data-picker-source="pickerCatalog"
                                data-picker-select="addProduct"
                                data-picker-submit="addFromSearch"
                                data-picker-listbox="entry-search-results"
                                data-picker-boost-from="entry-search-boost"
                                data-picker-text="{{ json_encode([
                                    'amount' => __('accounts::module.entry.picker_amount'),
                                    'free' => __('accounts::module.entry.picker_free'),
                                    'group' => __('accounts::module.entry.picker_group'),
                                    'filtered' => __('accounts::module.entry.picker_filtered'),
                                ], JSON_UNESCAPED_UNICODE) }}"
                                role="combobox"
                                aria-autocomplete="list"
                                aria-controls="entry-search-results"
                                aria-expanded="false"
                                aria-describedby="entry-search-hint"
                            >
                            <div id="entry-search-results" class="picker-results" role="listbox" aria-label="{{ __('accounts::module.entry.results') }}" wire:ignore hidden></div>
                        </div>
                        <div id="entry-search-hint" class="form-text kbd-hint">{{ __('accounts::module.entry.search_hint') }}</div>
                        <span id="entry-search-boost" data-ids="{{ json_encode(array_map(fn ($product) => $product->id, $this->usual['products'])) }}" hidden></span>
                    </div>

                    @if ($this->usual['products'] !== [] && ! $correcting)
                        <div>
                            <h3 class="entry-section-title">
                                <i class="bi bi-clock-history" aria-hidden="true"></i>
                                {{ $this->usual['fromShop'] ? __('accounts::module.entry.popular') : __('accounts::module.entry.usual', ['name' => $this->customer->name]) }}
                            </h3>
                            <div class="entry-strip">
                                @foreach ($this->usual['products'] as $product)
                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addProduct({{ $product->id }})" wire:key="usual-{{ $product->id }}">
                                        <span dir="auto">{{ $product->name }}</span>
                                        <span class="num text-body-secondary ms-1" dir="ltr">{{ $product->price->toDisplayString() }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($form->lines === [] && $this->shelves->isNotEmpty() && ! $correcting)
                        <div>
                            <h3 class="entry-section-title">
                                <i class="bi bi-grid" aria-hidden="true"></i>
                                {{ __('accounts::module.entry.shelves') }}
                            </h3>
                            {{-- One row that scrolls sideways, so the shelves never push the lines down. --}}
                            <div class="entry-strip">
                                @foreach ($this->shelves as $shelf)
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-picker-group="{{ $shelf->id }}" data-picker-for="entry-search" wire:key="shelf-{{ $shelf->id }}">
                                        @if ($shelf->icon)<i class="bi bi-{{ $shelf->icon }} me-1" aria-hidden="true"></i>@endif
                                        <span dir="auto">{{ $shelf->name }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <section aria-labelledby="entry-lines-title">
                        <h3 class="entry-label" id="entry-lines-title">
                            <i class="bi bi-list-ol" aria-hidden="true"></i>
                            {{ __('accounts::module.entry.lines') }}
                            @if ($form->lines !== [])
                                <span class="badge rounded-pill text-bg-secondary num">{{ count($form->lines) }}</span>
                            @endif
                        </h3>

                        @if ($form->lines === [])
                            <p class="border border-dashed rounded-3 p-3 text-center small text-body-secondary mb-2">{{ __('accounts::module.entry.no_lines') }}</p>
                            @error('form.lines')<div class="text-danger-emphasis small mb-2" role="alert">{{ $message }}</div>@enderror
                        @else
                            <ol class="entry-lines {{ $this->flow }}" role="list">
                                @foreach ($form->lines as $index => $line)
                                    <li class="entry-line" wire:key="line-{{ $line['key'] }}">
                                        <span class="entry-line-number num" aria-hidden="true">{{ $loop->iteration }}</span>

                                        @if ($line['product'] === null)
                                            @include('accounts::admin.entry-free-line', [
                                                'line' => $line,
                                                'index' => $index,
                                                'total' => $form->lineTotal($line),
                                                'placeholder' => $this->freeLineName,
                                            ])
                                        @else
                                            @include('accounts::admin.entry-product-line', [
                                                'line' => $line,
                                                'index' => $index,
                                                'total' => $form->lineTotal($line),
                                                'changed' => $form->priceChanged($line),
                                                'loose' => $this->isLoose($line),
                                                'unit' => $this->unitLabel($line['unit']),
                                                'approx' => $this->approxQuantity($line),
                                            ])
                                        @endif

                                        <button type="button" class="btn btn-sm entry-line-remove" wire:click="removeLine('{{ $line['key'] }}')" aria-label="{{ __('accounts::module.entry.remove_line', ['name' => $line['name'] !== '' ? $line['name'] : $this->freeLineName]) }}">
                                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                                        </button>
                                    </li>
                                @endforeach
                            </ol>
                        @endif

                        <button type="button" class="btn btn-sm btn-outline-secondary mt-2" wire:click="addFreeLine">
                            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('accounts::module.entry.add_free_line') }}
                        </button>
                    </section>

                    <div @class(['d-none' => $correcting])>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="entry-paying-now" wire:model.live="form.payingNow">
                            <label class="form-check-label" for="entry-paying-now">{{ __('accounts::module.entry.paying_now') }}</label>
                        </div>
                        @if ($form->payingNow)
                            <label for="entry-paid-now" class="visually-hidden">{{ __('accounts::module.entry.paid_now') }}</label>
                            <div class="input-group mt-2">
                                <input
                                    id="entry-paid-now"
                                    type="text"
                                    inputmode="decimal"
                                    dir="ltr"
                                    autocomplete="off"
                                    wire:model.live.debounce.250ms="form.paidNow"
                                    x-on:keydown.enter.prevent
                                    @if ($errors->has('form.paidNow')) aria-invalid="true" aria-describedby="entry-paid-now-error" @endif
                                    @class(['form-control num', 'is-invalid' => $errors->has('form.paidNow')])
                                >
                                <span class="input-group-text">{{ currency_symbol() }}</span>
                            </div>
                            @error('form.paidNow')<div id="entry-paid-now-error" class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @endif
                    </div>
                @else
                    <div>
                        <label for="entry-amount" class="entry-label">{{ $correcting ? __('accounts::module.entry.right_amount') : __('accounts::module.entry.amount') }}</label>
                        <div class="input-group input-group-lg">
                            <input
                                id="entry-amount"
                                type="text"
                                inputmode="decimal"
                                dir="ltr"
                                wire:model.live.debounce.250ms="form.amount"
                                autocomplete="off"
                                data-autofocus
                                aria-describedby="entry-amount-hint @if ($errors->has('form.amount')) entry-amount-error @endif"
                                @if ($errors->has('form.amount')) aria-invalid="true" @endif
                                @class(['form-control num fs-3', 'is-invalid' => $errors->has('form.amount')])
                            >
                            <span class="input-group-text">{{ currency_symbol() }}</span>
                        </div>
                        @error('form.amount')<div id="entry-amount-error" class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <div id="entry-amount-hint" class="form-text">{{ __('accounts::module.entry.amount_hint') }}</div>

                        @if ($this->owed->isPositive() && ! $correcting)
                            <button type="button" class="btn btn-sm btn-flow flow-credit mt-2" wire:click="payAll">
                                <i class="bi bi-check2-all me-1" aria-hidden="true"></i>{{ __('accounts::module.entry.pay_all', ['amount' => $this->owed->toDisplayString()]) }}
                            </button>
                        @endif
                    </div>

                    @if ($this->overpaidBy)
                        <fieldset class="p-3 rounded-3 border border-warning-subtle bg-warning-subtle text-warning-emphasis">
                            <legend class="fs-6 fw-semibold mb-2">{{ __('accounts::module.entry.overpaid', ['amount' => $this->overpaidBy->toDisplayString()]) }}</legend>

                            @if ($this->owed->isPositive())
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" id="overpay-change" value="owed_only" wire:model.live="form.overpayment">
                                    <label class="form-check-label text-body" for="overpay-change">
                                        {{ __('accounts::module.entry.owed_only', ['owed' => $this->owed->toDisplayString(), 'rest' => $this->overpaidBy->toDisplayString()]) }}
                                        <span class="d-block small text-body-secondary">{{ __('accounts::module.entry.owed_only_result') }}</span>
                                    </label>
                                </div>
                            @endif

                            @if ($this->keepsCredit)
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" id="overpay-credit" value="keep_as_credit" wire:model.live="form.overpayment">
                                    <label class="form-check-label text-body" for="overpay-credit">
                                        {{ __('accounts::module.entry.keep_credit', ['amount' => $form->paymentAmount()?->toDisplayString(), 'rest' => $this->overpaidBy->toDisplayString()]) }}
                                        <span class="d-block small text-body-secondary">{{ __('accounts::module.entry.keep_credit_result') }}</span>
                                    </label>
                                </div>
                            @endif
                        </fieldset>
                    @endif
                @endif

                <div>
                    <label for="entry-note" class="entry-label"><i class="bi bi-chat-left-text" aria-hidden="true"></i>{{ __('accounts::module.entry.note') }}</label>
                    <input
                        id="entry-note"
                        type="text"
                        class="form-control"
                        wire:model.blur="form.note"
                        dir="auto"
                        maxlength="500"
                        autocomplete="off"
                        placeholder="{{ $form->isCharge() ? __('accounts::module.entry.note_hint_charge') : __('accounts::module.entry.note_hint_payment') }}"
                        x-on:keydown.enter.prevent
                    >
                </div>

                @if ($correcting)
                    <fieldset>
                        <legend class="entry-label">{{ __('accounts::module.details.reason') }}</legend>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach ($this->correctionReasons as $value => $label)
                                <input type="radio" class="btn-check" id="correction-reason-{{ $value }}" value="{{ $value }}" wire:model="correctionReason" wire:key="correction-reason-input-{{ $value }}">
                                <label class="btn btn-sm btn-outline-secondary" for="correction-reason-{{ $value }}" wire:key="correction-reason-label-{{ $value }}">{{ $label }}</label>
                            @endforeach
                        </div>
                        @error('correctionReason')<div class="text-danger-emphasis small mt-1" role="alert">{{ $message }}</div>@enderror
                    </fieldset>
                @endif

                @if ($twin)
                    <div class="p-3 rounded-3 border border-warning-subtle bg-warning-subtle text-warning-emphasis" role="alert">
                        <p class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>{{ __('accounts::module.entry.duplicate', $twin) }}</p>
                        <p class="mb-2">{{ __('accounts::module.entry.duplicate_question') }}</p>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-warning" wire:click="confirmTwin" wire:loading.attr="disabled">{{ __('accounts::module.entry.duplicate_new') }}</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="dropTwin">{{ __('accounts::module.entry.duplicate_same') }}</button>
                        </div>
                    </div>
                @endif

                @if ($problem)
                    <div class="alert alert-danger mb-0 py-2" role="alert">{{ $problem }}</div>
                @endif

                {{-- The figures this save is about, pinned to the bottom of the
                     drawer however far it scrolls: the total, and what the balance
                     is now and becomes. --}}
                <div class="entry-summary mt-auto {{ $this->flow }}" aria-live="polite">
                    <div class="entry-summary-total">
                        <span class="fw-semibold">
                            @if ($correcting)
                                {{ __('accounts::module.entry.right_amount') }}
                            @else
                                {{ $form->isCharge() ? __('accounts::module.entry.total_charge') : __('accounts::module.entry.total_payment') }}
                            @endif
                        </span>
                        <span class="entry-summary-figure num" dir="ltr">{{ $this->recorded?->toDisplayString() ?? '—' }}</span>
                    </div>

                    @if ($form->isCharge() && $form->paidNowAmount() && ! $correcting)
                        <div class="entry-summary-paid flow-credit">
                            <span>{{ __('accounts::module.entry.paid_now') }}</span>
                            <span class="num fw-semibold flow-text" dir="ltr">{{ $form->paidNowAmount()->toDisplayString() }}</span>
                        </div>
                    @endif

                    <div class="entry-summary-change">
                        <div>
                            <div class="small text-body-secondary">{{ __('accounts::module.entry.now') }}</div>
                            <x-accounts::admin.balance :amount="$this->account->outstanding" />
                        </div>
                        <i class="bi bi-arrow-{{ is_rtl() ? 'left' : 'right' }} entry-summary-arrow" aria-hidden="true"></i>
                        <div class="text-end">
                            <div class="small text-body-secondary">{{ __('accounts::module.entry.after') }}</div>
                            @if ($this->after)
                                <x-accounts::admin.balance :amount="$this->after" class="fw-bold" />
                            @else
                                <span class="text-body-secondary">—</span>
                            @endif
                        </div>
                    </div>
                </div>
            </form>

            <div class="border-top p-3 d-flex gap-2 {{ $this->flow }}">
                <button
                    type="submit"
                    form="entry-form"
                    class="btn btn-lg btn-flow-solid flex-grow-1"
                    wire:loading.attr="disabled"
                    wire:target="save,confirmTwin"
                    data-ctrl-enter-submit
                    aria-keyshortcuts="Control+Enter"
                >
                    <span class="spinner-border spinner-border-sm me-1" aria-hidden="true" wire:loading wire:target="save,confirmTwin"></span>
                    @if ($correcting)
                        {{ __('accounts::module.entry.save_correction') }}
                    @elseif ($form->isCharge())
                        {{ $this->recorded ? __('accounts::module.entry.save_charge', ['amount' => $this->recorded->toDisplayString()]) : __('accounts::module.entry.save_charge_empty') }}
                    @else
                        {{ $this->recorded ? __('accounts::module.entry.save_payment', ['amount' => $this->recorded->toDisplayString()]) : __('accounts::module.entry.save_payment_empty') }}
                    @endif
                    <kbd class="ms-1 d-none d-md-inline kbd-hint" aria-hidden="true">{{ $form->isCharge() ? 'Ctrl Enter' : 'Enter' }}</kbd>
                </button>
                <button type="button" class="btn btn-lg btn-outline-secondary" data-bs-dismiss="offcanvas">{{ __('shell.actions.cancel') }}</button>
            </div>
        @endif
    </div>

    {{-- The save, said once, with ten seconds to take it back. --}}
    <div class="toast-container position-fixed bottom-0 start-50 translate-middle-x p-3">
        <div id="entry-toast" class="toast align-items-center text-bg-dark border-0" role="status" aria-live="polite" aria-atomic="true" data-bs-delay="10000" wire:ignore.self>
            <div class="d-flex align-items-center gap-2 p-2">
                <div class="toast-body flex-grow-1" dir="auto">{{ $posted['message'] ?? '' }}</div>
                @if ($posted['undoable'] ?? false)
                    <button type="button" class="btn btn-sm btn-light" wire:click="undo" wire:loading.attr="disabled">
                        <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>{{ __('accounts::module.entry.undo') }}
                    </button>
                @endif
                <button type="button" class="btn-close btn-close-white me-1" data-bs-dismiss="toast" aria-label="{{ __('shell.actions.close') }}"></button>
            </div>
        </div>
    </div>
</div>

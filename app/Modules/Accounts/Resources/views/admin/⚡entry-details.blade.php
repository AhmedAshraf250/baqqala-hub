<?php

use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Money\Money;
use App\Modules\Accounts\Areas\Admin\Forms\EntryForm;
use App\Modules\Accounts\Authorization\AccountPermission;
use App\Modules\Accounts\Authorization\AccountTransactionPolicy;
use App\Modules\Accounts\Contracts\AccountLedgerInterface;
use App\Modules\Accounts\Contracts\Data\VoidRequest;
use App\Modules\Accounts\Contracts\Enums\CorrectionReason;
use App\Modules\Accounts\Contracts\Enums\EntryKind;
use App\Modules\Accounts\Contracts\Exceptions\AccountChangedMeanwhile;
use App\Modules\Accounts\Contracts\Exceptions\EntryAlreadyVoided;
use App\Modules\Accounts\Contracts\Exceptions\ReversalCannotBeVoided;
use App\Modules\Accounts\Database\Models\AccountTransaction;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use App\Modules\Accounts\Domain\Actions\CorrectEntry;
use App\Modules\Accounts\Domain\Services\EntryLines;
use App\Modules\Catalog\Contracts\Enums\SaleUnit;
use App\Modules\Customers\Contracts\CustomerRepositoryInterface;
use App\Modules\Customers\Contracts\Data\CustomerData;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * One entry, in full, in a drawer beside the statement: what it was made of,
 * the balance either side of it, who recorded it and when, what became of it
 * since — and from here, voiding it, correcting it, or moving it to the
 * customer it belonged to.
 *
 * Nothing is edited. A void posts a reversal with its reason; a move posts a
 * reversal on this account and the same entry on the other, together. Each
 * carries the account's latest entry as it was when the drawer opened, and is
 * refused if a colleague posted since. What may not be done is shown
 * disabled with the reason, never hidden — or the counter would think the
 * feature missing.
 */
new class extends Component {
    public ?int $entryId = null;

    /** The account's latest entry when the drawer opened: what the person saw. */
    public ?int $seenLastEntryId = null;

    /** '', 'void', or 'move': which confirmation is open. */
    public string $mode = '';

    public string $reason = '';

    public string $note = '';

    public string $moveSearch = '';

    public ?int $moveTo = null;

    public string $operationId = '';

    public ?string $problem = null;

    public ?string $done = null;

    #[On('open-entry-details')]
    public function open(int $entryId): void
    {
        abort_unless(Area::Admin->user()?->can(AccountPermission::View->value) === true, 403);

        $entry = AccountTransaction::query()->find($entryId);

        abort_if($entry === null, 404);

        $this->reset('mode', 'reason', 'note', 'moveSearch', 'moveTo', 'problem', 'done');
        $this->resetErrorBag();
        unset($this->entry, $this->account, $this->customer);

        $this->entryId = $entryId;
        $this->seenLastEntryId = CustomerAccount::query()->whereKey($entry->account_id)->value('last_entry_id');
        $this->operationId = (string) Str::uuid();

        $this->dispatch('overlay-show', id: 'entry-details');
    }

    #[Computed]
    public function entry(): ?AccountTransaction
    {
        return $this->entryId === null ? null : AccountTransaction::query()
            ->with(['items', 'reversal', 'reversed', 'replaced', 'replacement'])
            ->find($this->entryId);
    }

    #[Computed]
    public function account(): ?CustomerAccount
    {
        return $this->entry === null ? null : CustomerAccount::query()->find($this->entry->account_id);
    }

    #[Computed]
    public function customer(): ?CustomerData
    {
        return $this->account === null ? null : app(CustomerRepositoryInterface::class)->find($this->account->customer_id);
    }

    /**
     * Who recorded the entry, and who voided it, by id.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function names(): array
    {
        $ids = array_filter([$this->entry?->created_by, $this->entry?->reversal?->created_by]);

        return AdminUser::query()->whereKey($ids)->pluck('name', 'id')->map(fn (mixed $name): string => (string) $name)->all();
    }

    /**
     * Whether the person may void, correct, or move this entry.
     */
    #[Computed]
    public function mayCorrect(): bool
    {
        $user = Area::Admin->user();

        // A void is never voided, whoever asks — the ledger's rule, which the
        // owner's every-permission does not lift.
        return $this->entry !== null
            && $this->entry->kind->isCorrectable()
            && $this->entry->reversal === null
            && $user !== null
            && Gate::forUser($user)->allows('correct', $this->entry);
    }

    /**
     * Why the corrections are closed, when they are.
     */
    #[Computed]
    public function whyNot(): ?string
    {
        return match (true) {
            $this->entry === null, $this->mayCorrect => null,
            $this->entry->kind === EntryKind::Reversal => __('accounts::module.details.why_reversal'),
            $this->entry->reversal !== null => __('accounts::module.details.why_voided'),
            default => __('accounts::module.details.why_permission'),
        };
    }

    /**
     * Until when whoever recorded the entry may still correct it alone — said
     * to them, so they know the door closes.
     */
    #[Computed]
    public function windowEnds(): ?CarbonImmutable
    {
        $user = Area::Admin->user();

        if ($this->entry === null || $user === null || $user->getKey() !== $this->entry->created_by || $user->can(AccountPermission::Correct->value)) {
            return null;
        }

        $ends = CarbonImmutable::instance($this->entry->created_at)->addMinutes(AccountTransactionPolicy::window());

        return $ends->isFuture() ? $ends : null;
    }

    /**
     * Who the entry might be moved to: the customers the search finds, this
     * one left out.
     *
     * @return Collection<int, CustomerData>
     */
    #[Computed]
    public function candidates(): Collection
    {
        if (trim($this->moveSearch) === '' || $this->account === null) {
            return collect();
        }

        return app(CustomerRepositoryInterface::class)->search($this->moveSearch, 7)
            ->reject(fn (CustomerData $customer): bool => $customer->id === $this->account->customer_id)
            ->take(6)
            ->values();
    }

    /**
     * The reasons a void may give — not «undone at once», which only the
     * toast's undo gives.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function reasons(): array
    {
        return collect(CorrectionReason::cases())
            ->reject(fn (CorrectionReason $reason): bool => $reason === CorrectionReason::Undo)
            ->mapWithKeys(fn (CorrectionReason $reason): array => [$reason->value => $reason->label()])
            ->all();
    }

    public function startVoid(): void
    {
        $this->reset('reason', 'note', 'problem', 'done');
        $this->mode = 'void';
    }

    public function startMove(): void
    {
        $this->reset('reason', 'note', 'moveSearch', 'moveTo', 'problem', 'done');
        $this->mode = 'move';
    }

    public function back(): void
    {
        $this->reset('mode', 'reason', 'note', 'moveSearch', 'moveTo', 'problem');
        $this->resetErrorBag();
    }

    public function chooseMoveTo(int $customerId): void
    {
        $this->moveTo = $customerId;
    }

    /**
     * Correct it: the recording drawer opens with what it was, to say what it
     * should have been.
     */
    public function edit(): void
    {
        $this->authorizeCorrecting();

        $this->dispatch('overlay-hide', id: 'entry-details');
        $this->dispatch('open-correction', entryId: $this->entryId, expectedLastEntryId: $this->seenLastEntryId);
    }

    public function void(AccountLedgerInterface $ledger): void
    {
        $administrator = $this->authorizeCorrecting();
        $this->resetErrorBag();
        $this->problem = null;

        $this->validate([
            'reason' => ['required', Rule::in(array_keys($this->reasons))],
            'note' => ['nullable', 'string', 'max:500'],
        ], attributes: ['reason' => __('accounts::module.details.reason'), 'note' => __('accounts::module.entry.note')]);

        $amount = $this->entry->amount;

        try {
            $ledger->void(new VoidRequest(
                entryId: (int) $this->entryId,
                postedBy: (int) $administrator->getKey(),
                operationId: $this->operationId,
                reason: CorrectionReason::from($this->reason),
                note: filled($this->note) ? trim($this->note) : null,
                expectedLastEntryId: $this->seenLastEntryId,
            ));
        } catch (AccountChangedMeanwhile $changed) {
            $this->refuse($changed);

            return;
        } catch (EntryAlreadyVoided|ReversalCannotBeVoided) {
            $this->problem = __('accounts::module.details.why_voided');

            return;
        }

        $this->finish(__('accounts::module.details.voided', ['amount' => $amount->toDisplayString()]));
    }

    public function move(CorrectEntry $correct, EntryLines $lines, CustomerRepositoryInterface $customers): void
    {
        $administrator = $this->authorizeCorrecting();
        $this->resetErrorBag();
        $this->problem = null;

        $target = $this->moveTo === null ? null : $customers->find($this->moveTo);

        if ($target === null || $target->id === $this->account?->customer_id) {
            $this->addError('moveTo', __('accounts::module.details.choose_customer'));

            return;
        }

        $entry = $this->entry;
        $charge = $entry->kind === EntryKind::Charge;

        try {
            $correct->handle(
                entryId: $entry->getKey(),
                postedBy: (int) $administrator->getKey(),
                operationId: $this->operationId,
                reason: CorrectionReason::WrongCustomer,
                toCustomerId: (int) $target->id,
                amount: $charge ? null : $entry->amount,
                lines: $charge ? $lines->chargeLines($entry) : [],
                note: filled($this->note) ? trim($this->note) : null,
                expectedLastEntryId: $this->seenLastEntryId,
            );
        } catch (AccountChangedMeanwhile $changed) {
            $this->refuse($changed);

            return;
        } catch (EntryAlreadyVoided|ReversalCannotBeVoided) {
            $this->problem = __('accounts::module.details.why_voided');

            return;
        }

        $this->finish(__('accounts::module.details.moved', ['name' => $target->name]));
    }

    public function inWords(Money $balance): string
    {
        return match (true) {
            $balance->isPositive() => __('accounts::module.balance.owes').' '.$balance->toDisplayString(),
            $balance->isNegative() => __('accounts::module.balance.in_credit').' '.$balance->negated()->toDisplayString(),
            default => __('accounts::module.balance.settled'),
        };
    }

    /**
     * What the balance becomes when this entry is voided: today's, less what
     * the entry added — or plus what it took off.
     */
    public function afterVoid(): ?Money
    {
        if ($this->entry === null || $this->account === null) {
            return null;
        }

        return $this->entry->type->applyTo($this->account->outstanding, $this->entry->amount->negated());
    }

    /**
     * How long ago, as said: «منذ 12 دقيقة», or «الآن».
     */
    public function ago(CarbonInterface $moment): string
    {
        return local_time($moment)->locale(app()->getLocale())->diffForHumans(['options' => CarbonInterface::JUST_NOW]);
    }

    /**
     * A line's quantity as it is read: «2», «0.25 كجم».
     */
    public function quantity(int $thousandths, ?string $unit): string
    {
        $label = SaleUnit::tryFrom((string) $unit)?->label();

        return trim(EntryForm::quantityText($thousandths).' '.($unit === null || $unit === 'piece' ? '' : (string) $label));
    }

    private function refuse(AccountChangedMeanwhile $changed): void
    {
        $this->seenLastEntryId = $changed->lastEntryId;
        unset($this->account);
        $this->problem = __('accounts::module.entry.changed', ['balance' => $this->inWords($changed->outstanding)]);
    }

    private function finish(string $done): void
    {
        $this->reset('mode', 'reason', 'note', 'moveSearch', 'moveTo', 'problem');
        $this->done = $done;
        $this->operationId = (string) Str::uuid();
        unset($this->entry, $this->account, $this->names, $this->mayCorrect, $this->whyNot);

        $this->dispatch('entry-posted');
    }

    private function authorizeCorrecting(): AdminUser
    {
        $administrator = Area::Admin->user();

        abort_unless($administrator instanceof AdminUser && $this->mayCorrect, 403);

        return $administrator;
    }
}; ?>

<div>
    <div class="offcanvas offcanvas-end offcanvas-wide" tabindex="-1" id="entry-details" aria-labelledby="entry-details-title" wire:ignore.self>
        <div class="offcanvas-header border-bottom">
            <h2 class="offcanvas-title h5" id="entry-details-title">{{ __('accounts::module.details.title') }}</h2>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('shell.actions.close') }}"></button>
        </div>

        @if ($this->entry && $this->account && $this->customer)
            @php($entry = $this->entry)
            <div class="offcanvas-body d-flex flex-column gap-3 receipt">
                <div class="print-only">
                    <p class="fw-bold mb-0">{{ __('shell.brand.name') }}</p>
                    <p class="mb-0" dir="auto">{{ $this->customer->name }}</p>
                </div>

                {{-- What it is, and whether it stands. --}}
                <div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="fs-5 fw-bold">{{ $entry->kind->label() }}</span>
                        @if ($entry->reversal)
                            <span class="badge bg-secondary-subtle text-body-emphasis border border-secondary-subtle">{{ $entry->replacement ? __('accounts::module.statement.voided_corrected') : __('accounts::module.statement.voided') }}</span>
                        @elseif ($entry->kind->value === 'reversal')
                            <span class="badge bg-secondary-subtle text-body-emphasis border border-secondary-subtle">{{ __('accounts::module.details.a_void') }}</span>
                        @else
                            <span class="badge bg-body-tertiary text-body-emphasis border">{{ __('accounts::module.details.standing') }}</span>
                        @endif
                    </div>
                    <div class="fs-2 num" dir="ltr">{{ $entry->amount->toDisplayString() }} <span class="fs-6 text-body-secondary">{{ currency_symbol() }}</span></div>
                    <p class="mb-0">
                        {{ __('accounts::module.details.moved_balance', ['before' => $this->inWords($entry->outstandingBefore()), 'after' => $this->inWords($entry->outstanding_after)]) }}
                    </p>
                </div>

                @if ($done)
                    <div class="alert alert-success py-2 mb-0" role="status">{{ $done }}</div>
                @endif

                {{-- What became of it, or what it concerns. --}}
                @if ($entry->reversal)
                    <p class="small mb-0 p-2 rounded-3 bg-body-tertiary">
                        {{ __('accounts::module.details.voided_on', ['date' => local_time($entry->reversal->created_at)->locale(app()->getLocale())->isoFormat('LLLL'), 'name' => $this->names[$entry->reversal->created_by] ?? '—', 'reason' => $entry->reversal->reason?->label() ?? '—']) }}
                    </p>
                @endif
                @if ($entry->reversed)
                    <p class="small mb-0 p-2 rounded-3 bg-body-tertiary">
                        {{ __('accounts::module.details.cancels', ['entry' => $entry->reversed->kind->label().' '.$entry->reversed->amount->toDisplayString(), 'date' => local_time($entry->reversed->created_at)->locale(app()->getLocale())->isoFormat('LLL')]) }}
                    </p>
                @endif
                @if ($entry->replaced)
                    <p class="small mb-0 p-2 rounded-3 bg-body-tertiary">
                        {{ __('accounts::module.details.corrects', ['entry' => $entry->replaced->kind->label().' '.$entry->replaced->amount->toDisplayString(), 'date' => local_time($entry->replaced->created_at)->locale(app()->getLocale())->isoFormat('LLL')]) }}
                    </p>
                @endif

                @if ($entry->items->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <x-admin::table.heading>{{ __('accounts::module.details.item') }}</x-admin::table.heading>
                                    <x-admin::table.heading align="end">{{ __('accounts::module.entry.quantity') }}</x-admin::table.heading>
                                    <x-admin::table.heading align="end">{{ __('accounts::module.details.price') }}</x-admin::table.heading>
                                    <x-admin::table.heading align="end">{{ __('accounts::module.details.line_total') }}</x-admin::table.heading>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($entry->items as $item)
                                    <tr wire:key="item-{{ $item->id }}">
                                        <td dir="auto">{{ $item->name }}</td>
                                        <td class="text-end num text-nowrap" dir="ltr">{{ $this->quantity($item->quantity, $item->unit) }}</td>
                                        <td class="text-end num text-nowrap" dir="ltr">
                                            {{ $item->unit_price->toDisplayString() }}
                                            @if ($item->list_price && ! $item->list_price->equals($item->unit_price))
                                                <span class="d-block small text-warning-emphasis">{{ __('accounts::module.entry.original_price', ['price' => $item->list_price->toDisplayString()]) }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end num" dir="ltr">{{ $item->line_total->toDisplayString() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <dl class="account-facts small mb-0">
                    <div><dt>{{ __('accounts::module.details.recorded_by') }}</dt><dd>{{ $this->names[$entry->created_by] ?? '—' }}</dd></div>
                    <div><dt>{{ __('accounts::module.details.at') }}</dt><dd>{{ local_time($entry->created_at)->locale(app()->getLocale())->isoFormat('LLLL') }} <span class="text-body-secondary">({{ $this->ago($entry->created_at) }})</span></dd></div>
                    <div><dt>{{ __('accounts::module.details.reference') }}</dt><dd class="num" dir="ltr">{{ $entry->reference }}</dd></div>
                    @if ($entry->description)
                        <div><dt>{{ __('accounts::module.entry.note') }}</dt><dd dir="auto">{{ $entry->description }}</dd></div>
                    @endif
                </dl>

                @if ($this->windowEnds)
                    <p class="small mb-0 text-body-secondary no-print">
                        <i class="bi bi-clock me-1" aria-hidden="true"></i>{{ __('accounts::module.details.window', ['time' => local_time($this->windowEnds)->locale(app()->getLocale())->isoFormat('LT')]) }}
                    </p>
                @endif

                @if ($problem)
                    <div class="alert alert-danger py-2 mb-0" role="alert">{{ $problem }}</div>
                @endif

                {{-- Void: what will happen, in figures, and why — required. --}}
                @if ($mode === 'void')
                    <form wire:submit="void" class="p-3 rounded-3 border border-danger-subtle no-print" novalidate>
                        <h3 class="h6">{{ __('accounts::module.details.void_title') }}</h3>
                        <p class="small">{{ __('accounts::module.details.void_explained', ['amount' => $entry->amount->toDisplayString(), 'name' => $this->customer->name, 'balance' => $this->inWords($this->afterVoid() ?? $this->account->outstanding)]) }}</p>
                        <p class="small text-body-secondary">{{ __('accounts::module.details.stays_struck') }}</p>

                        <fieldset class="mb-2">
                            <legend class="form-label fs-6">{{ __('accounts::module.details.reason') }}</legend>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach ($this->reasons as $value => $label)
                                    <input type="radio" class="btn-check" id="void-reason-{{ $value }}" value="{{ $value }}" wire:model="reason" wire:key="void-reason-input-{{ $value }}">
                                    <label class="btn btn-sm btn-outline-secondary" for="void-reason-{{ $value }}" wire:key="void-reason-label-{{ $value }}">{{ $label }}</label>
                                @endforeach
                            </div>
                            @error('reason')<div class="text-danger-emphasis small mt-1" role="alert">{{ $message }}</div>@enderror
                        </fieldset>

                        <label for="void-note" class="form-label small">{{ __('accounts::module.entry.note') }}</label>
                        <input id="void-note" type="text" class="form-control form-control-sm mb-3" wire:model="note" dir="auto" maxlength="500" placeholder="{{ __('accounts::module.details.void_note_hint') }}">

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-danger" wire:loading.attr="disabled" wire:target="void">{{ __('accounts::module.details.confirm_void') }}</button>
                            <button type="button" class="btn btn-outline-secondary" wire:click="back">{{ __('shell.actions.back') }}</button>
                        </div>
                    </form>
                @endif

                {{-- Move: to the customer it belonged to. --}}
                @if ($mode === 'move')
                    <form wire:submit="move" class="p-3 rounded-3 border no-print" novalidate>
                        <h3 class="h6">{{ __('accounts::module.details.move_title') }}</h3>

                        <label for="move-search" class="form-label small">{{ __('accounts::module.details.move_to') }}</label>
                        <input id="move-search" type="search" class="form-control form-control-sm mb-2" wire:model.live.debounce.200ms="moveSearch" dir="auto" autocomplete="off" placeholder="{{ __('accounts::module.list.search_placeholder') }}">

                        <div class="list-group mb-2" role="radiogroup" aria-label="{{ __('accounts::module.details.move_to') }}">
                            @foreach ($this->candidates as $candidate)
                                <button
                                    type="button"
                                    role="radio"
                                    aria-checked="{{ $moveTo === $candidate->id ? 'true' : 'false' }}"
                                    @class(['list-group-item list-group-item-action d-flex justify-content-between', 'active' => $moveTo === $candidate->id])
                                    wire:click="chooseMoveTo({{ $candidate->id }})"
                                    wire:key="move-candidate-{{ $candidate->id }}"
                                >
                                    <span dir="auto">{{ $candidate->name }}</span>
                                    @if ($candidate->phone)<span class="num small" dir="ltr">{{ $candidate->phone }}</span>@endif
                                </button>
                            @endforeach
                        </div>
                        @error('moveTo')<div class="text-danger-emphasis small mb-2" role="alert">{{ $message }}</div>@enderror

                        <label for="move-note" class="form-label small">{{ __('accounts::module.entry.note') }}</label>
                        <input id="move-note" type="text" class="form-control form-control-sm mb-3" wire:model="note" dir="auto" maxlength="500">

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="move" @disabled($moveTo === null)>{{ __('accounts::module.details.confirm_move') }}</button>
                            <button type="button" class="btn btn-outline-secondary" wire:click="back">{{ __('shell.actions.back') }}</button>
                        </div>
                    </form>
                @endif
            </div>

            {{-- What can be done, the closed ones closed with the reason. --}}
            @if ($mode === '')
                <div class="border-top p-3 no-print">
                    @if ($this->whyNot)
                        <p class="small text-body-secondary mb-2"><i class="bi bi-lock me-1" aria-hidden="true"></i>{{ $this->whyNot }}</p>
                    @endif
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-secondary" wire:click="edit" @disabled(! $this->mayCorrect)>
                            <i class="bi bi-pencil me-1" aria-hidden="true"></i>{{ __('accounts::module.details.edit') }}
                        </button>
                        <button type="button" class="btn btn-outline-secondary" wire:click="startMove" @disabled(! $this->mayCorrect)>
                            <i class="bi bi-arrow-left-right me-1" aria-hidden="true"></i>{{ __('accounts::module.details.move') }}
                        </button>
                        <button type="button" class="btn btn-outline-secondary" data-print="receipt">
                            <i class="bi bi-receipt me-1" aria-hidden="true"></i>{{ __('accounts::module.details.receipt') }}
                        </button>
                        <button type="button" class="btn btn-outline-danger ms-auto" wire:click="startVoid" @disabled(! $this->mayCorrect)>
                            <i class="bi bi-x-circle me-1" aria-hidden="true"></i>{{ __('accounts::module.details.void') }}
                        </button>
                    </div>
                </div>
            @endif
        @endif
    </div>
</div>

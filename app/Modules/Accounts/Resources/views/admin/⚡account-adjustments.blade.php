<?php

use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Money\Money;
use App\Modules\Accounts\Areas\Admin\Forms\EntryForm;
use App\Modules\Accounts\Authorization\AccountPermission;
use App\Modules\Accounts\Contracts\Enums\TransactionType;
use App\Modules\Accounts\Contracts\Exceptions\AccountChangedMeanwhile;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use App\Modules\Accounts\Domain\Actions\RecordDiscount;
use App\Modules\Accounts\Domain\Actions\RecordOpeningBalance;
use App\Modules\Accounts\Domain\Actions\SetCreditLimit;
use App\Modules\Accounts\Domain\Exceptions\DiscountExceedsBalance;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * What only `accounts.adjust` may do to an account: bring in the balance the
 * paper book had, forgive part of what is owed, and set how much may be owed
 * at most.
 *
 * The first two are entries like any other — posted, never edited. A discount
 * never takes the balance below nothing, and is turned back when a colleague
 * posted since the dialog opened. The limit is the account's own setting.
 */
new class extends Component {
    public ?int $customerId = null;

    /** 'opening', 'discount', or 'limit'. */
    public string $mode = 'opening';

    public string $amount = '';

    /** Which way an opening balance goes: owed by them, or held for them. */
    public string $direction = 'debit';

    public string $note = '';

    public ?int $seenLastEntryId = null;

    public string $operationId = '';

    public ?string $problem = null;

    #[On('adjust-account')]
    public function open(int $customerId, string $mode): void
    {
        $this->authorizeAdjusting();

        $account = CustomerAccount::query()->where('customer_id', $customerId)->first();

        abort_if($account === null, 404);

        $this->reset('amount', 'direction', 'note', 'problem');
        $this->resetErrorBag();
        unset($this->account);

        $this->customerId = $customerId;
        $this->mode = in_array($mode, ['opening', 'discount', 'limit'], true) ? $mode : 'opening';
        $this->seenLastEntryId = $account->last_entry_id;
        $this->operationId = (string) Str::uuid();

        if ($this->mode === 'limit' && ! $account->credit_limit->isZero()) {
            $this->amount = $account->credit_limit->toDecimalString();
        }

        $this->dispatch('overlay-show', id: 'account-adjustments');
    }

    #[Computed]
    public function account(): ?CustomerAccount
    {
        return $this->customerId === null ? null : CustomerAccount::query()->where('customer_id', $this->customerId)->first();
    }

    /**
     * The balance once this is saved, or null while the amount is not one.
     */
    #[Computed]
    public function after(): ?Money
    {
        $amount = EntryForm::money($this->amount);

        if ($amount === null || $this->account === null || $this->mode === 'limit') {
            return null;
        }

        return match (true) {
            $this->mode === 'discount' => $this->account->outstanding->minus($amount),
            $this->direction === 'credit' => $this->account->outstanding->minus($amount),
            default => $this->account->outstanding->plus($amount),
        };
    }

    public function save(RecordOpeningBalance $opening, RecordDiscount $discount, SetCreditLimit $limit): void
    {
        $administrator = $this->authorizeAdjusting();
        $this->problem = null;
        $this->resetErrorBag();

        $amount = EntryForm::money($this->amount);

        if ($amount === null || ($this->mode !== 'limit' && ! $amount->isPositive()) || $amount->isGreaterThan(Money::fromDecimalString(EntryForm::MAX_AMOUNT))) {
            throw ValidationException::withMessages(['amount' => __('accounts::module.entry.errors.amount')]);
        }

        $note = filled($this->note) ? trim($this->note) : null;

        try {
            match ($this->mode) {
                'opening' => $opening->handle((int) $this->customerId, (int) $administrator->getKey(), $this->operationId, $amount, $this->direction === 'credit' ? TransactionType::Credit : TransactionType::Debit, $note),
                'discount' => $discount->handle((int) $this->customerId, (int) $administrator->getKey(), $this->operationId, $amount, $note, $this->seenLastEntryId),
                default => $limit->handle((int) $this->customerId, $amount),
            };
        } catch (AccountChangedMeanwhile $changed) {
            $this->seenLastEntryId = $changed->lastEntryId;
            unset($this->account);
            $this->problem = __('accounts::module.adjust.changed');

            return;
        } catch (DiscountExceedsBalance) {
            $this->problem = __('accounts::module.adjust.discount_too_much', ['owed' => ($this->account?->outstanding ?? Money::zero())->toDisplayString()]);

            return;
        }

        $this->dispatch('overlay-hide', id: 'account-adjustments');
        $this->dispatch('account-changed');
    }

    private function authorizeAdjusting(): AdminUser
    {
        $administrator = Area::Admin->user();

        abort_unless($administrator instanceof AdminUser && $administrator->can(AccountPermission::Adjust->value), 403);

        return $administrator;
    }
}; ?>

<div class="modal fade" id="account-adjustments" tabindex="-1" aria-labelledby="account-adjustments-title" aria-hidden="true" wire:ignore.self>
    <div class="modal-dialog">
        <form class="modal-content" wire:submit="save" novalidate>
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="account-adjustments-title">{{ __('accounts::module.adjust.'.$mode.'_title') }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('shell.actions.close') }}"></button>
            </div>

            <div class="modal-body">
                <p class="small text-body-secondary">{{ __('accounts::module.adjust.'.$mode.'_help') }}</p>

                @if ($mode === 'opening')
                    <fieldset class="mb-3">
                        <legend class="form-label fs-6">{{ __('accounts::module.adjust.direction') }}</legend>
                        <div class="d-flex gap-2">
                            <input type="radio" class="btn-check" id="opening-debit" value="debit" wire:model.live="direction">
                            <label class="btn btn-outline-primary" for="opening-debit">{{ __('accounts::module.adjust.owes_them') }}</label>
                            <input type="radio" class="btn-check" id="opening-credit" value="credit" wire:model.live="direction">
                            <label class="btn btn-outline-primary" for="opening-credit">{{ __('accounts::module.adjust.held_for_them') }}</label>
                        </div>
                    </fieldset>
                @endif

                <label for="adjust-amount" class="form-label">{{ __('accounts::module.adjust.'.$mode.'_amount') }}</label>
                <div class="input-group mb-1">
                    <input
                        id="adjust-amount"
                        type="text"
                        inputmode="decimal"
                        dir="ltr"
                        wire:model.live.debounce.250ms="amount"
                        data-autofocus
                        @if ($errors->has('amount')) aria-invalid="true" aria-describedby="adjust-amount-error" @endif
                        @class(['form-control form-control-lg num', 'is-invalid' => $errors->has('amount')])
                    >
                    <span class="input-group-text">{{ currency_symbol() }}</span>
                </div>
                @error('amount')<div id="adjust-amount-error" class="invalid-feedback d-block">{{ $message }}</div>@enderror
                @if ($mode === 'limit')
                    <div class="form-text">{{ __('accounts::module.adjust.limit_zero') }}</div>
                @endif

                @if ($mode !== 'limit')
                    <label for="adjust-note" class="form-label mt-3">{{ __('accounts::module.entry.note') }}</label>
                    <input id="adjust-note" type="text" class="form-control" wire:model="note" dir="auto" maxlength="500">
                @endif

                @if ($this->after && $this->account)
                    <p class="mt-3 mb-0" aria-live="polite">
                        {{ __('accounts::module.entry.after') }}: <x-accounts::admin.balance :amount="$this->after" />
                    </p>
                @endif

                @if ($problem)
                    <div class="alert alert-danger py-2 mt-3 mb-0" role="alert">{{ $problem }}</div>
                @endif
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('shell.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">{{ __('accounts::module.adjust.save') }}</button>
            </div>
        </form>
    </div>
</div>

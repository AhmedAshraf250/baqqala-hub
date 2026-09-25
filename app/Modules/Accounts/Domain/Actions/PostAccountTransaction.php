<?php

namespace App\Modules\Accounts\Domain\Actions;

use App\Foundation\Money\Money;
use App\Modules\Accounts\Contracts\Data\AccountStanding;
use App\Modules\Accounts\Contracts\Enums\TransactionType;
use App\Modules\Accounts\Contracts\Events\AccountEntryPosted;
use App\Modules\Accounts\Contracts\Exceptions\CreditLimitExceeded;
use App\Modules\Accounts\Contracts\Exceptions\OverpaymentNotAllowed;
use App\Modules\Accounts\Database\Models\AccountTransaction;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The only supported way to move a customer's outstanding balance.
 *
 * `outstanding` is a cached total of the ledger, so it is never written on its
 * own: the row is locked, the entry is recorded, and the new total is stored
 * together inside one database transaction. Anything that changes it without
 * coming through here will eventually disagree with the ledger.
 *
 * The announcement is dispatched inside the transaction and held by the event
 * until the outermost one commits — see {@see AccountEntryPosted}.
 */
final class PostAccountTransaction
{
    public function handle(
        CustomerAccount $account,
        TransactionType $type,
        Money $amount,
        int $postedBy,
        ?string $description = null,
        ?string $reference = null,
    ): AccountTransaction {
        if (! $amount->isPositive()) {
            throw new InvalidArgumentException('A transaction amount must be greater than zero.');
        }

        return DB::transaction(function () use ($account, $type, $amount, $postedBy, $description, $reference): AccountTransaction {
            $locked = CustomerAccount::query()
                ->whereKey($account->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $outstandingAfter = $type->applyTo($locked->outstanding, $amount);

            $this->guardCreditLimit($locked, $type, $amount, $outstandingAfter);
            $this->guardOverpayment($locked, $type, $outstandingAfter);

            $transaction = $locked->transactions()->create([
                'type' => $type,
                'amount' => $amount,
                'outstanding_after' => $outstandingAfter,
                'description' => $description,
                'reference' => $reference ?? $this->generateReference(),
                'created_by' => $postedBy,
            ]);

            $locked->forceFill(['outstanding' => $outstandingAfter])->save();

            $account->setRawAttributes($locked->getAttributes(), sync: true);

            AccountEntryPosted::dispatch(
                $locked->customer_id,
                $transaction->type,
                $transaction->amount,
                $transaction->outstanding_after,
                $transaction->reference,
            );

            return $transaction;
        });
    }

    /**
     * Refuse a debit that would take the account past what it may owe.
     *
     * The rule itself is {@see AccountStanding::wouldBreachLimit()} — the same
     * one another module asks before offering to put a basket on account, so
     * the question and the refusal can never disagree.
     */
    private function guardCreditLimit(CustomerAccount $account, TransactionType $type, Money $amount, Money $outstandingAfter): void
    {
        if ($type === TransactionType::Debit && $account->toStanding()->wouldBreachLimit($amount)) {
            throw new CreditLimitExceeded($account->customer_id, $outstandingAfter, $account->credit_limit);
        }
    }

    /**
     * Refuse a payment larger than the tab, unless the shop allows deposits.
     */
    private function guardOverpayment(CustomerAccount $account, TransactionType $type, Money $outstandingAfter): void
    {
        if ($type !== TransactionType::Credit || ! $outstandingAfter->isNegative()) {
            return;
        }

        if (config('accounts.allow_overpayment')) {
            return;
        }

        throw new OverpaymentNotAllowed($account->customer_id, $outstandingAfter);
    }

    /**
     * Build a human-readable reference for an entry that was not given one.
     */
    private function generateReference(): string
    {
        return config('accounts.transaction_reference_prefix')
            .'-'.now()->format('Ymd')
            .'-'.Str::upper(Str::random(6));
    }
}

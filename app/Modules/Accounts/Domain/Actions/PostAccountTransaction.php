<?php

namespace App\Modules\Accounts\Domain\Actions;

use App\Foundation\Money\Money;
use App\Modules\Accounts\Contracts\Data\AccountStanding;
use App\Modules\Accounts\Contracts\Enums\CorrectionReason;
use App\Modules\Accounts\Contracts\Enums\EntryKind;
use App\Modules\Accounts\Contracts\Enums\TransactionType;
use App\Modules\Accounts\Contracts\Events\AccountEntryPosted;
use App\Modules\Accounts\Contracts\Exceptions\CreditLimitExceeded;
use App\Modules\Accounts\Contracts\Exceptions\OverpaymentNotAllowed;
use App\Modules\Accounts\Database\Models\AccountTransaction;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use App\Modules\Accounts\Domain\Exceptions\DiscountExceedsBalance;
use App\Modules\Accounts\Domain\Services\AccountSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

/**
 * The only supported way to move a customer's outstanding balance.
 *
 * `outstanding` and the summary beside it are a cached view of the ledger,
 * so they are never written on their own: the row is locked, the entry and
 * its lines are recorded, and the new total and summary are stored together
 * inside one database transaction. Anything that changes them without coming
 * through here will eventually disagree with the ledger — which `app:sync`
 * would then report.
 *
 * The rules that belong to the money itself live here, whoever asks: a
 * positive amount, lines that add up to it, the credit limit on a charge, no
 * overpayment unless the shop takes deposits, no discount below zero. The
 * rules of each operation — what a charge is made of, what to do with a
 * payment larger than the debt, whether an entry may still be voided — live
 * in the action for it, which posts through here.
 *
 * The announcement is dispatched inside the transaction and held by the event
 * until the outermost one commits — see {@see AccountEntryPosted}.
 */
final class PostAccountTransaction
{
    /**
     * @param  TransactionType|null  $type  Only for the kinds that go either way —
     *                                      an opening balance, a reversal.
     * @param  list<array{product_id: int|null, name: string, unit: string|null, quantity: int, unit_price: Money, list_price: Money|null, line_total: Money}>  $items
     */
    public function handle(
        CustomerAccount $account,
        EntryKind $kind,
        Money $amount,
        int $postedBy,
        ?TransactionType $type = null,
        array $items = [],
        ?string $description = null,
        ?CorrectionReason $reason = null,
        ?int $reverses = null,
        ?int $replaces = null,
        ?string $operationId = null,
        ?string $reference = null,
    ): AccountTransaction {
        $type = $this->direction($kind, $type);

        if (! $amount->isPositive()) {
            throw new InvalidArgumentException('A transaction amount must be greater than zero.');
        }

        if (($kind === EntryKind::Reversal) !== ($reverses !== null)) {
            throw new LogicException('A reversal names the entry it cancels, and only a reversal does.');
        }

        $this->ensureItemsAddUp($items, $amount);

        return DB::transaction(function () use ($account, $kind, $type, $amount, $postedBy, $items, $description, $reason, $reverses, $replaces, $operationId, $reference): AccountTransaction {
            $locked = CustomerAccount::query()
                ->whereKey($account->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $before = $locked->outstanding;
            $after = $type->applyTo($before, $amount);

            $this->guardCreditLimit($locked, $kind, $amount, $after);
            $this->guardOverpayment($locked, $kind, $after);
            $this->guardDiscount($locked, $kind, $amount, $after);

            $transaction = $locked->transactions()->create([
                'type' => $type,
                'kind' => $kind,
                'amount' => $amount,
                'outstanding_after' => $after,
                'description' => $description,
                'reason' => $reason,
                'reverses_id' => $reverses,
                'replaces_id' => $replaces,
                'operation_id' => $operationId,
                'reference' => $reference ?? $this->generateReference(),
                'created_by' => $postedBy,
            ]);

            foreach ($items as $position => $item) {
                $transaction->items()->create(['position' => $position, ...$item]);
            }

            $this->summarise($locked, $transaction, $before, $after);

            $account->setRawAttributes($locked->getAttributes(), sync: true);

            AccountEntryPosted::dispatch(
                $locked->customer_id,
                $transaction->getKey(),
                $transaction->kind,
                $transaction->type,
                $transaction->amount,
                $transaction->outstanding_after,
                $transaction->reference,
                $reverses,
                $operationId,
                $replaces,
            );

            return $transaction;
        });
    }

    /**
     * The direction a kind moves the balance, checked against what the caller
     * said when the kind can go either way.
     */
    private function direction(EntryKind $kind, ?TransactionType $type): TransactionType
    {
        $fixed = $kind->direction();

        if ($fixed !== null && $type !== null && $type !== $fixed) {
            throw new LogicException("An entry of kind [{$kind->value}] always goes [{$fixed->value}].");
        }

        return $fixed ?? $type ?? throw new InvalidArgumentException("An entry of kind [{$kind->value}] has to say which way it goes.");
    }

    /**
     * Lines are the breakdown of the amount, never a second opinion on it.
     *
     * @param  list<array{line_total: Money}>  $items
     */
    private function ensureItemsAddUp(array $items, Money $amount): void
    {
        if ($items === []) {
            return;
        }

        $total = array_reduce($items, static fn (Money $sum, array $item): Money => $sum->plus($item['line_total']), Money::zero());

        if (! $total->equals($amount)) {
            throw new LogicException("The lines add up to {$total->toDecimalString()}, not {$amount->toDecimalString()}.");
        }
    }

    /**
     * Keep the summary beside the balance in step with the entry just
     * written: the latest entry, the latest payment still standing, and since
     * when the balance has been owed without falling to zero.
     */
    private function summarise(CustomerAccount $account, AccountTransaction $transaction, Money $before, Money $after): void
    {
        $postedAt = CarbonImmutable::instance($transaction->created_at);

        $summary = [
            'outstanding' => $after,
            'last_entry_id' => $transaction->getKey(),
            'last_entry_at' => $postedAt,
        ];

        if ($transaction->kind === EntryKind::Payment) {
            $summary['last_payment_at'] = $postedAt;
        }

        // A voided payment never happened: the latest payment is the one
        // before it, if any still stands.
        if ($transaction->reverses_id !== null && $transaction->reversed?->kind === EntryKind::Payment) {
            $summary['last_payment_at'] = $account->transactions()
                ->where('kind', EntryKind::Payment)
                ->whereDoesntHave('reversal')
                ->max('created_at');
        }

        if (! $after->isPositive()) {
            $summary['in_debt_since'] = null;
        } elseif (! $before->isPositive()) {
            $summary['in_debt_since'] = $postedAt;
        }

        $account->forceFill($summary)->save();
    }

    /**
     * Refuse goods taken past what the account may owe.
     *
     * Only goods: an opening balance is what the paper book already said, and
     * a reversal or a correction puts right what happened. The rule itself is
     * {@see AccountStanding::wouldBreachLimit()} — the same one another
     * module asks before offering to put a basket on account.
     */
    private function guardCreditLimit(CustomerAccount $account, EntryKind $kind, Money $amount, Money $outstandingAfter): void
    {
        if ($kind === EntryKind::Charge && $account->toStanding()->wouldBreachLimit($amount)) {
            throw new CreditLimitExceeded($account->customer_id, $outstandingAfter, $account->credit_limit);
        }
    }

    /**
     * Refuse a payment that leaves the shop holding the customer's money,
     * unless the shop takes deposits.
     */
    private function guardOverpayment(CustomerAccount $account, EntryKind $kind, Money $outstandingAfter): void
    {
        if ($kind !== EntryKind::Payment || ! $outstandingAfter->isNegative()) {
            return;
        }

        if (app(AccountSettings::class)->allowsOverpayment()) {
            return;
        }

        throw new OverpaymentNotAllowed($account->customer_id, $outstandingAfter);
    }

    /**
     * Refuse forgiving more than is owed.
     */
    private function guardDiscount(CustomerAccount $account, EntryKind $kind, Money $amount, Money $outstandingAfter): void
    {
        if ($kind === EntryKind::Discount && $outstandingAfter->isNegative()) {
            throw new DiscountExceedsBalance($account->customer_id, $amount, $account->outstanding);
        }
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

<?php

namespace App\Modules\Accounts\Domain\Actions;

use App\Foundation\Money\Money;
use App\Modules\Accounts\Contracts\Data\ChargeLine;
use App\Modules\Accounts\Contracts\Data\PostedOperation;
use App\Modules\Accounts\Contracts\Enums\CorrectionReason;
use App\Modules\Accounts\Contracts\Enums\EntryKind;
use App\Modules\Accounts\Contracts\Exceptions\AccountChangedMeanwhile;
use App\Modules\Accounts\Contracts\Exceptions\EntryAlreadyVoided;
use App\Modules\Accounts\Contracts\Exceptions\ReversalCannotBeVoided;
use App\Modules\Accounts\Database\Models\AccountTransaction;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use App\Modules\Accounts\Domain\Services\ChargeLinePricer;
use App\Modules\Accounts\Domain\Services\OperationLog;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Puts an entry right, without editing it: in one operation, a reversal that
 * cancels it and a replacement that says what should have been recorded,
 * pointing back at it.
 *
 * The replacement is the same kind of entry — a charge stays a charge, with
 * its lines priced again, a payment stays a payment — on the same account,
 * or on another one when the entry was recorded against the wrong customer.
 * Both accounts are locked in the same order every time, so two corrections
 * crossing between the same two accounts wait for each other rather than
 * deadlock.
 */
final readonly class CorrectEntry
{
    public function __construct(
        private OpenAccount $openAccount,
        private PostAccountTransaction $post,
        private ChargeLinePricer $pricer,
        private OperationLog $operations,
    ) {}

    /**
     * @param  int|null  $toCustomerId  Whose account the corrected entry belongs on; null for the same one.
     * @param  Money|null  $amount  The corrected amount, for anything but a charge.
     * @param  list<ChargeLine>  $lines  The corrected lines, for a charge.
     *
     * @throws ReversalCannotBeVoided
     * @throws EntryAlreadyVoided
     * @throws AccountChangedMeanwhile when someone posted to the entry's account since the person looked
     */
    public function handle(
        int $entryId,
        int $postedBy,
        string $operationId,
        CorrectionReason $reason,
        ?int $toCustomerId = null,
        ?Money $amount = null,
        array $lines = [],
        ?string $note = null,
        ?int $expectedLastEntryId = null,
    ): PostedOperation {
        $entry = AccountTransaction::query()->findOrFail($entryId);
        $to = $toCustomerId === null ? null : $this->openAccount->handle($toCustomerId);

        [$items, $total] = $this->replacement($entry, $amount, $lines);

        return DB::transaction(function () use ($entry, $to, $postedBy, $operationId, $reason, $note, $expectedLastEntryId, $items, $total): PostedOperation {
            $locked = CustomerAccount::query()
                ->whereKey(array_unique([$entry->account_id, $to?->getKey() ?? $entry->account_id]))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $from = $locked[$entry->account_id];
            $target = $locked[$to?->getKey() ?? $entry->account_id];

            if ($done = $this->operations->previously($operationId, ...$locked->values()->all())) {
                return $done;
            }

            $from->assertUnchangedSince($expectedLastEntryId);
            $entry->assertVoidable();

            $reversal = $this->post->handle(
                $from,
                EntryKind::Reversal,
                $entry->amount,
                $postedBy,
                type: $entry->type->inverse(),
                description: $note,
                reason: $reason,
                reverses: $entry->getKey(),
                operationId: $operationId,
            );

            $replacement = $this->post->handle(
                $target,
                $entry->kind,
                $total,
                $postedBy,
                type: $entry->type,
                items: $items,
                description: $note ?? $entry->description,
                replaces: $entry->getKey(),
                operationId: $operationId,
            );

            return $this->operations->posted($operationId, [$reversal, $replacement], ...$locked->values()->all());
        });
    }

    /**
     * What the corrected entry is made of: lines priced again for a charge,
     * an amount for anything else.
     *
     * @param  list<ChargeLine>  $lines
     * @return array{0: list<array{product_id: int|null, name: string, unit: string|null, quantity: int, unit_price: Money, list_price: Money|null, line_total: Money}>, 1: Money}
     */
    private function replacement(AccountTransaction $entry, ?Money $amount, array $lines): array
    {
        if ($entry->kind === EntryKind::Charge) {
            ['items' => $items, 'total' => $total] = $this->pricer->price($lines);

            return [$items, $total];
        }

        if ($amount === null || $lines !== []) {
            throw new InvalidArgumentException("A corrected [{$entry->kind->value}] is an amount, not lines.");
        }

        return [[], $amount];
    }
}

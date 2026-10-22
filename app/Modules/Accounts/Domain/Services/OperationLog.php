<?php

namespace App\Modules\Accounts\Domain\Services;

use App\Modules\Accounts\Contracts\Data\PostedOperation;
use App\Modules\Accounts\Database\Models\AccountTransaction;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use Illuminate\Support\Collection;
use LogicException;

/**
 * What an operation already posted, so a save that arrives twice posts once.
 *
 * Every operation asks here on its locked account rows, inside its
 * transaction, before it writes: the same operation id coming back — a double
 * click, a retry after the network dropped — gets what the first one posted,
 * marked as replayed, and nothing new is written.
 */
final class OperationLog
{
    /**
     * The operation as it was posted, or null if it was not.
     */
    public function previously(string $operationId, CustomerAccount ...$accounts): ?PostedOperation
    {
        $entries = AccountTransaction::query()
            ->where('operation_id', $operationId)
            ->orderBy('id')
            ->get();

        if ($entries->isEmpty()) {
            return null;
        }

        $customers = collect($accounts)->mapWithKeys(static fn (CustomerAccount $account): array => [$account->getKey() => $account->customer_id]);

        // An id is made once per form; one turning up on another account is a
        // bug in the caller, never a reason to post or to hide it.
        if ($entries->contains(static fn (AccountTransaction $entry): bool => ! $customers->has($entry->account_id))) {
            throw new LogicException("Operation [{$operationId}] was posted to another account.");
        }

        return $this->operation($operationId, $entries, $customers, replayed: true);
    }

    /**
     * An operation from the entries it just posted.
     *
     * @param  list<AccountTransaction>  $entries
     */
    public function posted(string $operationId, array $entries, CustomerAccount ...$accounts): PostedOperation
    {
        $customers = collect($accounts)->mapWithKeys(static fn (CustomerAccount $account): array => [$account->getKey() => $account->customer_id]);

        return $this->operation($operationId, collect($entries), $customers, replayed: false);
    }

    /**
     * @param  Collection<int, AccountTransaction>  $entries
     * @param  Collection<int, int>  $customers  Customer ids, keyed by account id.
     */
    private function operation(string $operationId, Collection $entries, Collection $customers, bool $replayed): PostedOperation
    {
        return new PostedOperation(
            $operationId,
            array_values($entries->map(static fn (AccountTransaction $entry) => $entry->toPosted((int) $customers[$entry->account_id]))->all()),
            $replayed,
        );
    }
}

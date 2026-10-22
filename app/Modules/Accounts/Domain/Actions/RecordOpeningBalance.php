<?php

namespace App\Modules\Accounts\Domain\Actions;

use App\Foundation\Money\Money;
use App\Modules\Accounts\Contracts\Data\PostedOperation;
use App\Modules\Accounts\Contracts\Enums\EntryKind;
use App\Modules\Accounts\Contracts\Enums\TransactionType;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use App\Modules\Accounts\Domain\Services\OperationLog;
use Illuminate\Support\Facades\DB;

/**
 * What the paper book said, when an account moves into the ledger — «رصيد
 * سابق». Owed by the customer (`Debit`), or held for them (`Credit`).
 *
 * One number, dated when it is recorded: the book's history stays in the
 * book. It is not held to the credit limit — it already happened.
 */
final readonly class RecordOpeningBalance
{
    public function __construct(
        private OpenAccount $openAccount,
        private PostAccountTransaction $post,
        private OperationLog $operations,
    ) {}

    public function handle(
        int $customerId,
        int $postedBy,
        string $operationId,
        Money $amount,
        TransactionType $direction,
        ?string $note = null,
    ): PostedOperation {
        $account = $this->openAccount->handle($customerId);

        return DB::transaction(function () use ($account, $postedBy, $operationId, $amount, $direction, $note): PostedOperation {
            $locked = CustomerAccount::query()->whereKey($account->getKey())->lockForUpdate()->firstOrFail();

            if ($done = $this->operations->previously($operationId, $locked)) {
                return $done;
            }

            $entry = $this->post->handle(
                $locked,
                EntryKind::OpeningBalance,
                $amount,
                $postedBy,
                type: $direction,
                description: $note,
                operationId: $operationId,
            );

            return $this->operations->posted($operationId, [$entry], $locked);
        });
    }
}

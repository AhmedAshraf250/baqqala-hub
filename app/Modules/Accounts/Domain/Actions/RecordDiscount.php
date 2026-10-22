<?php

namespace App\Modules\Accounts\Domain\Actions;

use App\Foundation\Money\Money;
use App\Modules\Accounts\Contracts\Data\PostedOperation;
use App\Modules\Accounts\Contracts\Enums\EntryKind;
use App\Modules\Accounts\Contracts\Exceptions\AccountChangedMeanwhile;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use App\Modules\Accounts\Domain\Exceptions\DiscountExceedsBalance;
use App\Modules\Accounts\Domain\Services\OperationLog;
use Illuminate\Support\Facades\DB;

/**
 * Part of a debt forgiven — «سامحته في الباقي».
 *
 * Without it the counter records a payment larger than the money received,
 * or leaves the last few piastres owed for ever. It lowers what is owed and
 * never below zero, and like a payment it is decided against a balance, so
 * that balance is checked on the locked row.
 */
final readonly class RecordDiscount
{
    public function __construct(
        private OpenAccount $openAccount,
        private PostAccountTransaction $post,
        private OperationLog $operations,
    ) {}

    /**
     * @throws AccountChangedMeanwhile when someone posted since the person looked
     * @throws DiscountExceedsBalance when it is more than is owed
     */
    public function handle(
        int $customerId,
        int $postedBy,
        string $operationId,
        Money $amount,
        ?string $note = null,
        ?int $expectedLastEntryId = null,
    ): PostedOperation {
        $account = $this->openAccount->handle($customerId);

        return DB::transaction(function () use ($account, $postedBy, $operationId, $amount, $note, $expectedLastEntryId): PostedOperation {
            $locked = CustomerAccount::query()->whereKey($account->getKey())->lockForUpdate()->firstOrFail();

            if ($done = $this->operations->previously($operationId, $locked)) {
                return $done;
            }

            $locked->assertUnchangedSince($expectedLastEntryId);

            $entry = $this->post->handle(
                $locked,
                EntryKind::Discount,
                $amount,
                $postedBy,
                description: $note,
                operationId: $operationId,
            );

            return $this->operations->posted($operationId, [$entry], $locked);
        });
    }
}

<?php

namespace App\Modules\Accounts\Domain\Actions;

use App\Modules\Accounts\Contracts\Data\ChargeRequest;
use App\Modules\Accounts\Contracts\Data\PostedOperation;
use App\Modules\Accounts\Contracts\Enums\EntryKind;
use App\Modules\Accounts\Contracts\Exceptions\CreditLimitExceeded;
use App\Modules\Accounts\Contracts\Exceptions\OverpaymentNotAllowed;
use App\Modules\Accounts\Contracts\Exceptions\PriceChangedMeanwhile;
use App\Modules\Accounts\Contracts\Exceptions\ProductUnavailable;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use App\Modules\Accounts\Domain\Services\ChargeLinePricer;
use App\Modules\Accounts\Domain\Services\OperationLog;
use Illuminate\Support\Facades\DB;

/**
 * Goods taken on account — «أخذ» — with the part paid on the spot, if any.
 *
 * The lines are priced from the catalog, the charge is posted with them, and
 * a payment for `paidNow` follows in the same transaction and the same
 * operation: either both are in the ledger or neither is.
 */
final readonly class RecordCharge
{
    public function __construct(
        private OpenAccount $openAccount,
        private PostAccountTransaction $post,
        private ChargeLinePricer $pricer,
        private OperationLog $operations,
    ) {}

    /**
     * @throws ProductUnavailable when a line names a product no longer for sale
     * @throws PriceChangedMeanwhile when a product's price changed after the person saw it
     * @throws CreditLimitExceeded when the charge takes the account past its limit
     * @throws OverpaymentNotAllowed when the part paid now is more than is owed and the shop takes no deposits
     */
    public function handle(ChargeRequest $request): PostedOperation
    {
        ['items' => $items, 'total' => $total] = $this->pricer->price($request->lines);

        $account = $this->openAccount->handle($request->customerId);

        return DB::transaction(function () use ($request, $account, $items, $total): PostedOperation {
            $locked = CustomerAccount::query()->whereKey($account->getKey())->lockForUpdate()->firstOrFail();

            if ($done = $this->operations->previously($request->operationId, $locked)) {
                return $done;
            }

            $entries = [$this->post->handle(
                $locked,
                EntryKind::Charge,
                $total,
                $request->postedBy,
                items: $items,
                description: $request->note,
                operationId: $request->operationId,
            )];

            if ($request->paidNow?->isPositive() === true) {
                $entries[] = $this->post->handle(
                    $locked,
                    EntryKind::Payment,
                    $request->paidNow,
                    $request->postedBy,
                    operationId: $request->operationId,
                );
            }

            return $this->operations->posted($request->operationId, $entries, $locked);
        });
    }
}

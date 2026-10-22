<?php

namespace App\Modules\Accounts\Domain\Actions;

use App\Foundation\Money\Money;
use App\Modules\Accounts\Contracts\Data\PaymentRequest;
use App\Modules\Accounts\Contracts\Data\PostedOperation;
use App\Modules\Accounts\Contracts\Enums\EntryKind;
use App\Modules\Accounts\Contracts\Enums\OverpaymentChoice;
use App\Modules\Accounts\Contracts\Exceptions\AccountChangedMeanwhile;
use App\Modules\Accounts\Contracts\Exceptions\NothingOwed;
use App\Modules\Accounts\Contracts\Exceptions\OverpaymentNotAllowed;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use App\Modules\Accounts\Domain\Services\OperationLog;
use Illuminate\Support\Facades\DB;

/**
 * Money handed over — «دفع».
 *
 * A payment larger than what is owed is settled by the choice the person
 * made at the counter: refused, recorded as only what was owed (the rest went
 * back as change), or kept as credit when the shop takes deposits. The
 * balance it was decided on is checked on the locked row, so «everything he
 * owes» is never paid against a balance a colleague has just changed.
 */
final readonly class RecordPayment
{
    public function __construct(
        private OpenAccount $openAccount,
        private PostAccountTransaction $post,
        private OperationLog $operations,
    ) {}

    /**
     * @throws AccountChangedMeanwhile when someone posted since the person looked
     * @throws OverpaymentNotAllowed when it is more than is owed and may not be kept
     * @throws NothingOwed when only what is owed is to be recorded, and nothing is
     */
    public function handle(PaymentRequest $request): PostedOperation
    {
        $account = $this->openAccount->handle($request->customerId);

        return DB::transaction(function () use ($request, $account): PostedOperation {
            $locked = CustomerAccount::query()->whereKey($account->getKey())->lockForUpdate()->firstOrFail();

            if ($done = $this->operations->previously($request->operationId, $locked)) {
                return $done;
            }

            $locked->assertUnchangedSince($request->expectedLastEntryId);

            $entry = $this->post->handle(
                $locked,
                EntryKind::Payment,
                $this->amountToRecord($locked, $request),
                $request->postedBy,
                description: $request->note,
                operationId: $request->operationId,
            );

            return $this->operations->posted($request->operationId, [$entry], $locked);
        });
    }

    /**
     * What is recorded of what was handed over.
     */
    private function amountToRecord(CustomerAccount $account, PaymentRequest $request): Money
    {
        $owed = $account->outstanding;

        if (! $request->amount->isGreaterThan($owed)) {
            return $request->amount;
        }

        return match ($request->overpayment) {
            OverpaymentChoice::Refuse => throw new OverpaymentNotAllowed($account->customer_id, $owed->minus($request->amount)),
            OverpaymentChoice::RecordOwedOnly => $owed->isPositive() ? $owed : throw new NothingOwed($account->customer_id, $owed),
            // The ledger refuses it there, when the shop takes no deposits.
            OverpaymentChoice::KeepAsCredit => $request->amount,
        };
    }
}

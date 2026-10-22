<?php

namespace App\Modules\Accounts\Domain\Services;

use App\Modules\Accounts\Contracts\AccountLedgerInterface;
use App\Modules\Accounts\Contracts\Data\ChargeRequest;
use App\Modules\Accounts\Contracts\Data\PaymentRequest;
use App\Modules\Accounts\Contracts\Data\PostedOperation;
use App\Modules\Accounts\Contracts\Data\VoidRequest;
use App\Modules\Accounts\Domain\Actions\RecordCharge;
use App\Modules\Accounts\Domain\Actions\RecordPayment;
use App\Modules\Accounts\Domain\Actions\VoidEntry;
use Illuminate\Container\Attributes\Scoped;

/**
 * The published ledger, implemented over this module's own actions.
 *
 * Deliberately thin. Its job is to be the seam: other modules depend on the
 * interface, and the actions behind it stay free to change.
 */
#[Scoped]
final readonly class AccountLedger implements AccountLedgerInterface
{
    public function __construct(
        private RecordCharge $charge,
        private RecordPayment $payment,
        private VoidEntry $void,
    ) {}

    public function charge(ChargeRequest $request): PostedOperation
    {
        return $this->charge->handle($request);
    }

    public function payment(PaymentRequest $request): PostedOperation
    {
        return $this->payment->handle($request);
    }

    public function void(VoidRequest $request): PostedOperation
    {
        return $this->void->handle($request);
    }
}

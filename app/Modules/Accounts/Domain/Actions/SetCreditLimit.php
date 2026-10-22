<?php

namespace App\Modules\Accounts\Domain\Actions;

use App\Foundation\Audit\AuditAction;
use App\Foundation\Audit\AuditChange;
use App\Foundation\Audit\AuditTrail;
use App\Foundation\Money\Money;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use App\Modules\Customers\Contracts\CustomerRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Sets the most a customer may owe — zero for no ceiling — and writes the
 * change into the change log, since a limit raised is money lent.
 */
final readonly class SetCreditLimit
{
    public function __construct(
        private CustomerRepositoryInterface $customers,
        private AuditTrail $audit,
    ) {}

    public function handle(int $customerId, Money $limit): void
    {
        DB::transaction(function () use ($customerId, $limit): void {
            $account = CustomerAccount::query()->where('customer_id', $customerId)->lockForUpdate()->firstOrFail();
            $before = $account->credit_limit;

            if ($before->equals($limit)) {
                return;
            }

            $account->update(['credit_limit' => $limit]);

            $this->audit->record('accounts.account', $customerId, $this->customers->find($customerId)->name ?? '—', 'accounts::module.audit.account', AuditAction::Updated, [
                new AuditChange('accounts::module.account.credit_limit', $before->toDisplayString(), $limit->toDisplayString()),
            ]);
        });
    }
}

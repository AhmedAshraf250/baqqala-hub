<?php

namespace App\Modules\Customers\Domain\Actions;

use App\Foundation\Audit\AuditAction;
use App\Foundation\Audit\AuditTrail;
use App\Modules\Customers\Contracts\CustomerRepositoryInterface;
use App\Modules\Customers\Contracts\Data\CustomerData;
use App\Modules\Customers\Contracts\Exceptions\PhoneAlreadyUsed;
use App\Modules\Customers\Database\Models\Customer;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Changes what the shop knows about a customer: their name, phone, address,
 * or notes.
 *
 * Under the same rule as adding them — a phone another customer has is
 * refused — and never touching their login, which the repository does not
 * write. What changed goes into the change log, field by field.
 */
final readonly class UpdateCustomer
{
    /** What the change log calls each field. */
    private const array LABELS = [
        'name' => 'customers::module.audit.fields.name',
        'phone' => 'customers::module.audit.fields.phone',
        'address' => 'customers::module.audit.fields.address',
        'notes' => 'customers::module.audit.fields.notes',
    ];

    public function __construct(
        private CustomerRepositoryInterface $customers,
        private AuditTrail $audit,
    ) {}

    /**
     * @throws PhoneAlreadyUsed
     */
    public function handle(CustomerData $customer): CustomerData
    {
        if ($customer->id === null) {
            throw new LogicException('Only a stored customer can be updated; a new one is added.');
        }

        return DB::transaction(function () use ($customer): CustomerData {
            Customer::assertPhoneFree($customer->phone, $customer->id);

            $before = $this->customers->find($customer->id);
            $saved = $this->customers->save($customer);

            $this->audit->record('customers.customer', $saved->id, $saved->name, 'customers::module.audit.customer', AuditAction::Updated, AuditTrail::differences(
                self::fields($before),
                self::fields($saved),
                self::LABELS,
            ));

            return $saved;
        });
    }

    /**
     * @return array<string, string|null>
     */
    private static function fields(?CustomerData $customer): array
    {
        return [
            'name' => $customer?->name,
            'phone' => $customer?->phone,
            'address' => $customer?->address,
            'notes' => $customer?->notes,
        ];
    }
}

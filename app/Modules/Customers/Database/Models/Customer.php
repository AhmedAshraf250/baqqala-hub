<?php

namespace App\Modules\Customers\Database\Models;

use App\Foundation\Identity\Models\User;
use App\Modules\Customers\Contracts\Data\CustomerData;
use App\Modules\Customers\Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person the shop does business with.
 *
 * Most customers never sign in: they are names in the ledger, and `user_id`
 * stays null. Private to this module — other modules see a customer as a
 * {@see CustomerData}, through `CustomerRepositoryInterface`, and hold only the
 * id.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $notes
 */
#[UseFactory(CustomerFactory::class)]
#[Fillable(['user_id', 'name', 'phone', 'address', 'notes'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    /**
     * The login this customer signs in to the portal with, if the shop gave
     * them one.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * This customer, as other modules may see them.
     */
    public function toData(): CustomerData
    {
        return new CustomerData(
            id: $this->getKey(),
            name: $this->name,
            phone: $this->phone,
            address: $this->address,
            notes: $this->notes,
            userId: $this->user_id,
        );
    }
}

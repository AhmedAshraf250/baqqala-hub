<?php

namespace App\Modules\Customers\Database\Models;

use App\Foundation\Identity\Models\User;
use App\Foundation\Text\PhoneNumber;
use App\Foundation\Text\SearchText;
use App\Modules\Customers\Contracts\Data\CustomerData;
use App\Modules\Customers\Contracts\Exceptions\PhoneAlreadyUsed;
use App\Modules\Customers\Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A person the shop does business with.
 *
 * Most customers never sign in: they are names in the ledger, and `user_id`
 * stays null. It is not fillable: only CreateCustomer and GrantPortalAccess
 * link a login, each in its own query. Private to this module — other modules see a customer as a
 * {@see CustomerData}, through `CustomerRepositoryInterface`, and hold only the
 * id.
 *
 * Every save writes `search_name` from the name, folded by {@see SearchText},
 * and stores the phone as {@see PhoneNumber} gives it; nothing else writes
 * either.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string $search_name
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $notes
 */
#[UseFactory(CustomerFactory::class)]
#[Fillable(['name', 'phone', 'address', 'notes'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected $attributes = [
        'search_name' => '',
    ];

    protected static function booted(): void
    {
        static::saving(static function (Customer $customer): void {
            $customer->search_name = SearchText::normalize($customer->name);
            $customer->phone = PhoneNumber::normalize($customer->phone);
        });
    }

    /**
     * The words a search term is matched by, folded as `search_name` is: each
     * must be in the name — or, made of digits, in the phone. A number typed
     * with spaces or dashes between its digits is one number.
     *
     * @return list<string>
     */
    public static function searchWords(string $term): array
    {
        $folded = SearchText::normalize($term);

        if ($folded === '') {
            return [];
        }

        if (preg_match('/^[\d ]+$/', $folded) === 1) {
            return [str_replace(' ', '', $folded)];
        }

        return explode(' ', $folded);
    }

    /**
     * Refuse a phone number another customer already has.
     *
     * @throws PhoneAlreadyUsed
     */
    public static function assertPhoneFree(?string $phone, ?int $exceptCustomerId = null): void
    {
        $phone = PhoneNumber::normalize($phone);

        if ($phone === null) {
            return;
        }

        $holder = self::query()
            ->where('phone', $phone)
            ->when($exceptCustomerId !== null, static fn (Builder $query) => $query->whereKeyNot($exceptCustomerId))
            ->value('id');

        if ($holder !== null) {
            throw new PhoneAlreadyUsed($phone, (int) $holder);
        }
    }

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
     * The groups the owner put this customer in, archived ones included.
     *
     * @return BelongsToMany<CustomerGroup, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(CustomerGroup::class, 'customer_group_members', 'customer_id', 'group_id');
    }

    /**
     * Customers every word of the term matches: in the name, or — for a word
     * of digits — in the phone too.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeMatching(Builder $query, string $term): Builder
    {
        foreach (self::searchWords($term) as $word) {
            $query->where(static function (Builder $query) use ($word): void {
                $query->where('search_name', 'like', "%{$word}%");

                if (ctype_digit($word)) {
                    $query->orWhere('phone', 'like', "%{$word}%");
                }
            });
        }

        return $query;
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

<?php

namespace App\Modules\Customers\Database\Models;

use App\Modules\Customers\Contracts\Data\CustomerGroupData;
use App\Modules\Customers\Contracts\Enums\GroupColor;
use App\Modules\Customers\Database\Factories\CustomerGroupFactory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A group the owner put customers in: «جملة», «قريبين». A customer may be in
 * several.
 *
 * Archived rather than deleted while anyone is in it: `archived_at` hides it
 * and keeps its memberships. `created_by` is not fillable — the repository
 * writes it once, when the group is made.
 *
 * @property int $id
 * @property string $name
 * @property GroupColor $color
 * @property string|null $icon
 * @property int $sort_order
 * @property CarbonImmutable|null $archived_at
 * @property int|null $created_by
 * @property CarbonImmutable|null $deleted_at
 */
#[UseFactory(CustomerGroupFactory::class)]
#[Fillable(['name', 'color', 'icon', 'sort_order', 'archived_at'])]
class CustomerGroup extends Model
{
    /** @use HasFactory<CustomerGroupFactory> */
    use HasFactory;

    // Deleted, it goes to the trash: out of every list, kept until it is
    // brought back or let go of for good.
    use SoftDeletes;

    protected $attributes = [
        'sort_order' => 0,
    ];

    /**
     * @return BelongsToMany<Customer, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'customer_group_members', 'group_id', 'customer_id');
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->whereNull($query->qualifyColumn('archived_at'));
    }

    /**
     * In the order the owner put them, then by name.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeInOrder(Builder $query): Builder
    {
        return $query->orderBy($query->qualifyColumn('sort_order'))->orderBy($query->qualifyColumn('name'));
    }

    /**
     * This group, as other modules may see it — with its member count when
     * it was read with one.
     */
    public function toData(): CustomerGroupData
    {
        $memberCount = $this->getAttributes()['members_count'] ?? null;

        return new CustomerGroupData(
            id: $this->getKey(),
            name: $this->name,
            color: $this->color,
            icon: $this->icon,
            sortOrder: $this->sort_order,
            archivedAt: $this->archived_at,
            createdBy: $this->created_by,
            memberCount: $memberCount === null ? null : (int) $memberCount,
            deletedAt: $this->deleted_at,
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'color' => GroupColor::class,
            'sort_order' => 'integer',
            'archived_at' => 'immutable_datetime',
        ];
    }
}

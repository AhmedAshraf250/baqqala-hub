<?php

namespace App\Modules\Customers\Contracts\Data;

use App\Modules\Customers\Contracts\Enums\GroupColor;
use Carbon\CarbonImmutable;

/**
 * A group of customers, as the owner names it: «جملة», «قريبين», «موظفين
 * الشركة» — what the shop knows about its relation with someone, kept apart
 * from what they owe or how they pay, which the system works out itself.
 *
 * `id` is null for a group not stored yet. `icon` is a Bootstrap Icons name
 * without its `bi-` prefix. `memberCount` is how many customers are in it when
 * the group was read with its count, and null otherwise; saving ignores it, as
 * it does `createdBy` for a group that is already stored.
 */
final readonly class CustomerGroupData
{
    public function __construct(
        public ?int $id,
        public string $name,
        public GroupColor $color,
        public ?string $icon = null,
        public int $sortOrder = 0,
        public ?CarbonImmutable $archivedAt = null,
        public ?int $createdBy = null,
        public ?int $memberCount = null,
        public ?CarbonImmutable $deletedAt = null,
    ) {}

    /**
     * Whether the group is hidden from the chips and the choices.
     */
    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }
}

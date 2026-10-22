<?php

namespace App\Modules\Accounts\Areas\Admin\Trash;

use App\Admin\Contracts\Trash\TrashSourceInterface;
use App\Admin\Trash\TrashItem;
use App\Foundation\Audit\AuditAction;
use App\Foundation\Audit\AuditTrail;
use App\Modules\Customers\Contracts\CustomerGroupRepositoryInterface;
use App\Modules\Customers\Contracts\Data\CustomerGroupData;
use App\Modules\Customers\Contracts\Enums\CustomerPermission;

/**
 * Customer groups in the trash: empty when deleted from the groups dialog
 * here, and kept to be brought back — with their name, colour, and icon.
 */
final readonly class GroupTrash implements TrashSourceInterface
{
    public function __construct(
        private CustomerGroupRepositoryInterface $groups,
        private AuditTrail $audit,
    ) {}

    public function label(): string
    {
        return 'accounts::module.trash.groups';
    }

    public function permission(): CustomerPermission
    {
        return CustomerPermission::Manage;
    }

    public function items(): array
    {
        $groups = $this->groups->findTrashed();
        $by = $this->audit->lastBy('accounts.group', AuditAction::Deleted, array_values(array_map(intval(...), $groups->keys()->all())));

        return array_values($groups->map(static fn (CustomerGroupData $group): TrashItem => new TrashItem(
            id: (int) $group->id,
            title: $group->name,
            subtitle: $group->color->label(),
            deletedAt: $group->deletedAt ?? now()->toImmutable(),
            deletedBy: $by[(int) $group->id] ?? null,
            icon: $group->icon ?? 'tags',
        ))->all());
    }

    public function restore(int $id): bool
    {
        $group = $this->groups->findTrashed()->get($id);

        if ($group === null) {
            return false;
        }

        $this->groups->save(new CustomerGroupData($group->id, $group->name, $group->color, $group->icon, $group->sortOrder, $group->archivedAt));
        $this->audit->record('accounts.group', $id, $group->name, 'accounts::module.audit.group', AuditAction::Restored);

        return true;
    }

    public function purge(int $id): bool
    {
        $group = $this->groups->findTrashed()->get($id);

        if ($group === null || ! $this->groups->deleteForever($id)) {
            return false;
        }

        $this->audit->record('accounts.group', $id, $group->name, 'accounts::module.audit.group', AuditAction::Purged);

        return true;
    }
}

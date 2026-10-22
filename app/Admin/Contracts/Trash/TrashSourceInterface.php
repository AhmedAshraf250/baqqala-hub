<?php

namespace App\Admin\Contracts\Trash;

use App\Admin\Contracts\Authorization\PermissionInterface;
use App\Admin\Trash\TrashItem;

/**
 * One kind of thing a module deletes into the trash — products, groups,
 * notes — and how it is brought back or let go of.
 *
 * Resolved from the container when the trash is opened, never while
 * booting, so it may read the database. Each restore and each purge is the
 * module's own step, with its own rules: it says no, rather than throwing,
 * when something stops it.
 */
interface TrashSourceInterface
{
    /**
     * The group's name over its items: a translation key.
     */
    public function label(): string;

    /**
     * What one must hold to see, bring back, or let go of these.
     */
    public function permission(): ?PermissionInterface;

    /**
     * What is in the trash, the latest deleted first.
     *
     * @return list<TrashItem>
     */
    public function items(): array;

    /**
     * Bring one back where it was.
     *
     * @return bool whether it came back
     */
    public function restore(int $id): bool;

    /**
     * Let go of one for good.
     *
     * @return bool whether it went; false while something still names it
     */
    public function purge(int $id): bool;
}

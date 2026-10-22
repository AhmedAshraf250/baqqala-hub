<?php

namespace App\Admin\Trash;

use Carbon\CarbonImmutable;

/**
 * One thing in the trash: what it was, a line about it, when it was deleted
 * and by whom — already in words, so the shell draws it without knowing
 * what it is.
 */
final readonly class TrashItem
{
    /**
     * @param  string  $icon  A Bootstrap Icons name, without the `bi-` prefix.
     */
    public function __construct(
        public int $id,
        public string $title,
        public ?string $subtitle,
        public CarbonImmutable $deletedAt,
        public ?string $deletedBy = null,
        public string $icon = 'trash',
    ) {}
}

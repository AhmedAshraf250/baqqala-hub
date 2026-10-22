<?php

namespace App\Admin\Contracts\Search;

use App\Admin\Contracts\Authorization\PermissionInterface;
use App\Admin\Search\SearchResult;

/**
 * One kind of thing the header's quick search finds — customers, products,
 * screens — and how to find it.
 *
 * Resolved from the container when someone searches, never while booting, so
 * it may read the database.
 */
interface SearchSourceInterface
{
    /**
     * The group's name over its results: a translation key.
     */
    public function label(): string;

    /**
     * What one must hold to see this group at all, or null for everyone.
     */
    public function permission(): ?PermissionInterface;

    /**
     * What matches the words typed, the best first, at most `$limit`.
     *
     * @return list<SearchResult>
     */
    public function search(string $term, int $limit): array;

    /**
     * What to offer before anything is typed — today's customers — or nothing.
     *
     * @return list<SearchResult>
     */
    public function suggestions(int $limit): array;

    /**
     * What to offer when nothing matched — «add «محمود الجزار» as a customer» —
     * or null.
     */
    public function fallback(string $term): ?SearchResult;
}

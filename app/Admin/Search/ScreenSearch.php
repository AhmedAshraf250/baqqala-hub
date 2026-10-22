<?php

namespace App\Admin\Search;

use App\Admin\Contracts\Authorization\PermissionInterface;
use App\Admin\Contracts\Search\SearchSourceInterface;
use App\Admin\Navigation\AdminNavigation;
use App\Admin\Navigation\NavigationItem;
use App\Foundation\Text\SearchText;

/**
 * The screens of the panel, found by their names in the sidebar — «منتجات»
 * goes to the products — among those this administrator may open.
 */
final class ScreenSearch implements SearchSourceInterface
{
    public function __construct(private readonly AdminNavigation $navigation) {}

    public function label(): string
    {
        return 'shell.search.screens';
    }

    public function permission(): ?PermissionInterface
    {
        return null;
    }

    /**
     * @return list<SearchResult>
     */
    public function search(string $term, int $limit): array
    {
        $words = array_filter(explode(' ', SearchText::normalize($term)));
        $found = [];

        foreach ($this->navigation->sections() as $section) {
            foreach ($section->items as $item) {
                foreach ([$item, ...$item->visibleChildren()] as $screen) {
                    $name = SearchText::normalize($screen->title());

                    if ($screen->route !== null && $words !== [] && array_all($words, static fn (string $word): bool => str_contains($name, $word))) {
                        $found[] = $this->result($screen, $section->label);
                    }
                }
            }
        }

        return array_slice($found, 0, $limit);
    }

    /**
     * @return list<SearchResult>
     */
    public function suggestions(int $limit): array
    {
        return [];
    }

    public function fallback(string $term): ?SearchResult
    {
        return null;
    }

    private function result(NavigationItem $screen, string $section): SearchResult
    {
        return new SearchResult(
            title: $screen->title(),
            url: $screen->url(),
            subtitle: __($section),
            icon: $screen->icon,
        );
    }
}

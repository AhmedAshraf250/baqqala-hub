<?php

namespace App\Admin\Search;

/**
 * One thing the quick search found: what it is called, a line about it, a
 * figure beside it, where Enter takes the person, and what the other keys do.
 *
 * Built by a module's search source with everything already in words, so the
 * shell draws it without knowing what it is.
 */
final readonly class SearchResult
{
    /**
     * @param  string|null  $badge  A figure in words: «عليه 1,250.00», «75.00».
     * @param  string|null  $tone  The badge's direction: `debit`, `credit`, or null for neither.
     * @param  string  $icon  A Bootstrap Icons name, without the `bi-` prefix.
     * @param  list<SearchAction>  $actions  What + and − (or another key) do on this result.
     */
    public function __construct(
        public string $title,
        public string $url,
        public ?string $subtitle = null,
        public ?string $badge = null,
        public ?string $tone = null,
        public string $icon = 'search',
        public array $actions = [],
    ) {}
}

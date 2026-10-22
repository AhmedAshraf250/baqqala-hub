<?php

namespace App\Admin\Search;

/**
 * Something to do with a search result besides opening it, one key away:
 * «+» takes «أخذ» for that customer, «−» «دفع».
 */
final readonly class SearchAction
{
    /**
     * @param  string  $key  The character that does it: `+` or `-`.
     * @param  string  $label  What it does, in words.
     * @param  string|null  $tone  `debit`, `credit`, or null.
     * @param  string  $icon  A Bootstrap Icons name, without the `bi-` prefix.
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $url,
        public ?string $tone = null,
        public string $icon = 'arrow-return-left',
    ) {}
}

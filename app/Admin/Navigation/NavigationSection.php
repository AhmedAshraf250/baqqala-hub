<?php

namespace App\Admin\Navigation;

/**
 * A titled group of sidebar items, rendered as an AdminLTE `.nav-header`.
 */
final readonly class NavigationSection
{
    /**
     * @param  string  $label  A translation key, resolved at render time.
     * @param  list<NavigationItem>  $items
     */
    public function __construct(
        public string $label,
        public array $items,
    ) {}
}

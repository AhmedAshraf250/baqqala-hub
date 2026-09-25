<?php

namespace App\Admin\Navigation;

use App\Admin\Contracts\Authorization\PermissionInterface;
use App\Foundation\Area\Area;

/**
 * One node in the admin navigation tree.
 *
 * The tree is the single source for both the sidebar and the breadcrumb trail,
 * so a screen's place in the hierarchy is stated once. Hand-written
 * breadcrumbs are how `Settings` ended up looking like a child of `Dashboard`,
 * which it never was.
 */
final readonly class NavigationItem
{
    /**
     * @param  string  $label  A translation key, resolved at render time.
     * @param  string|null  $route  A named route, or null for a branch that only groups.
     * @param  string  $icon  A Bootstrap Icons name, without the `bi-` prefix.
     * @param  list<string>  $activeWhen  Route-name patterns that light this item up.
     * @param  list<self>  $children  Nested items, rendered as an AdminLTE treeview.
     * @param  PermissionInterface|null  $permission  What a administrator needs to see this.
     */
    public function __construct(
        public string $label,
        public ?string $route = null,
        public string $icon = 'circle',
        public array $activeWhen = [],
        public array $children = [],
        public ?PermissionInterface $permission = null,
    ) {}

    /**
     * Whether the signed-in administrator may see this item.
     *
     * An item with no permission is open to anyone already in the admin area.
     * A branch survives if anything under it does, so a group never shows an
     * empty submenu.
     */
    public function isVisible(): bool
    {
        if ($this->hasChildren()) {
            return $this->visibleChildren() !== [];
        }

        return $this->permission === null
            || auth(Area::Admin->guard())->user()?->can($this->permission->value) === true;
    }

    /**
     * The children this administrator may see.
     *
     * @return list<self>
     */
    public function visibleChildren(): array
    {
        return array_values(array_filter(
            $this->children,
            static fn (self $child): bool => $child->isVisible(),
        ));
    }

    /**
     * The resolved URL for this item, or `#` when it only opens a submenu.
     */
    public function url(): string
    {
        return $this->route === null ? '#' : route($this->route);
    }

    public function title(): string
    {
        return __($this->label);
    }

    /**
     * Whether this item, or anything under it, matches the current request.
     */
    public function isActive(): bool
    {
        return $this->matchesCurrentRoute() || $this->hasActiveChild();
    }

    /**
     * Whether this item itself matches the current request.
     */
    public function matchesCurrentRoute(): bool
    {
        $patterns = $this->activeWhen ?: array_filter([$this->route]);

        return $patterns !== [] && request()->routeIs(...$patterns);
    }

    public function hasActiveChild(): bool
    {
        foreach ($this->children as $child) {
            if ($child->isActive()) {
                return true;
            }
        }

        return false;
    }

    public function hasChildren(): bool
    {
        return $this->children !== [];
    }

    /**
     * Walk this item and its descendants looking for the given route.
     *
     * Returns the trail from this item down to the match, or an empty list.
     *
     * @return list<self>
     */
    public function trailTo(string $routeName): array
    {
        if ($this->route === $routeName) {
            return [$this];
        }

        foreach ($this->children as $child) {
            $trail = $child->trailTo($routeName);

            if ($trail !== []) {
                return [$this, ...$trail];
            }
        }

        return [];
    }
}

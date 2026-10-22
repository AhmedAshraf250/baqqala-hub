<?php

namespace App\Admin\View\Components\Layout;

use App\Admin\Navigation\AdminNavigation;
use App\Admin\Navigation\NavigationItem;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-admin::layout.breadcrumbs />`: the trail to the current screen, from
 * the navigation tree. A screen the tree does not list has none.
 *
 * A screen reached from a listed one — named in that item's `activeWhen` —
 * has the item's trail, and then itself, by the title it gives: `$here`.
 */
final class Breadcrumbs extends Component
{
    /**
     * @var list<NavigationItem>
     */
    public readonly array $trail;

    /** The current screen's own name, when the trail ends at the item above it. */
    public readonly ?string $here;

    /**
     * Whether the trail is only home and this screen — which the page's
     * title already says, so a phone, short of room, leaves it out.
     */
    public readonly bool $onlyHere;

    public function __construct(AdminNavigation $navigation, ?string $current = null)
    {
        $this->trail = $navigation->currentTrail();

        $last = $this->trail === [] ? null : $this->trail[array_key_last($this->trail)];
        $route = request()->route()?->getName();

        $this->here = $last !== null && $last->route !== $route && filled($current) && $current !== $last->title() ? $current : null;
        $this->onlyHere = count($this->trail) === 1 && $this->here === null;
    }

    public function shouldRender(): bool
    {
        return $this->trail !== [];
    }

    public function render(): View
    {
        return view('admin::layout.breadcrumbs');
    }
}

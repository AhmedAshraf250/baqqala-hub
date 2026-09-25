<?php

namespace App\Admin\Navigation;

use App\Admin\Authorization\AdminPermission;
use App\Admin\Contracts\Navigation\ProvidesAdminNavigationInterface;
use App\Foundation\Modules\ModuleRegistry;
use Illuminate\Container\Attributes\Scoped;

/**
 * The admin panel's sidebar, assembled from the shell and the running modules.
 *
 * The shell contributes what exists whatever the business is — the dashboard at
 * the top, access control and settings at the bottom. Everything between comes
 * from the modules, in the order `config/modules.php` lists them.
 *
 * This is also the source the breadcrumb trail is derived from, so a screen's
 * place in the hierarchy is stated once.
 *
 * One instance per request, and it memoises: the sidebar, the content header,
 * the breadcrumbs, and the tab title all read the same tree. Never a singleton —
 * under a long-lived worker it would hand one administrator's filtered menu to
 * the next. Hand-written breadcrumbs are how
 * `Settings` once read as a child of `Dashboard`, which it never was.
 */
#[Scoped]
final class AdminNavigation
{
    /**
     * The tree, built once per request.
     *
     * Three things read it while a page renders — the sidebar, the content
     * header, and the breadcrumbs — and every item may ask the Gate whether
     * this administrator can see it. Rebuilding it each time cost thirty
     * permission checks to draw one screen.
     *
     * @var list<NavigationSection>|null
     */
    private ?array $sections = null;

    /**
     * Trails already resolved this request, keyed by route name.
     *
     * @var array<string, list<NavigationItem>>
     */
    private array $trails = [];

    public function __construct(private readonly ModuleRegistry $modules) {}

    /**
     * @return list<NavigationSection>
     */
    public function sections(): array
    {
        return $this->sections ??= $this->build();
    }

    /**
     * Assemble the tree and drop what this administrator may not reach.
     *
     * @return list<NavigationSection>
     */
    private function build(): array
    {
        $sections = [
            $this->overview(),
            ...$this->fromModules(),
            $this->system(),
        ];

        // Drop what this administrator may not see, then drop any section left
        // empty by that. A group heading with nothing under it is worse than
        // no group at all.
        $visible = array_map(
            static fn (NavigationSection $section): NavigationSection => new NavigationSection(
                $section->label,
                array_values(array_filter(
                    $section->items,
                    static fn (NavigationItem $item): bool => $item->isVisible(),
                )),
            ),
            $sections,
        );

        return array_values(array_filter(
            $visible,
            static fn (NavigationSection $section): bool => $section->items !== [],
        ));
    }

    /**
     * The modules' sections, with any that share a heading merged.
     *
     * Several modules may belong under one heading — Purchases, Sales, and
     * Reports are all Operations — and three headings reading "Operations" in a
     * row is the sidebar telling on the implementation. Order follows
     * `config/modules.php`, and a heading takes the position of the first
     * module that asked for it.
     *
     * @return list<NavigationSection>
     */
    private function fromModules(): array
    {
        $merged = [];

        foreach ($this->modules->providing(ProvidesAdminNavigationInterface::class) as $module) {
            $section = $module->adminNavigation();

            $merged[$section->label] = isset($merged[$section->label])
                ? new NavigationSection($section->label, [...$merged[$section->label]->items, ...$section->items])
                : $section;
        }

        return array_values($merged);
    }

    /**
     * The shell's section above the modules.
     */
    private function overview(): NavigationSection
    {
        return new NavigationSection('shell.navigation.group.overview', [
            new NavigationItem(
                label: 'shell.navigation.dashboard',
                route: 'admin.dashboard',
                icon: 'speedometer2',
            ),
        ]);
    }

    /**
     * The shell's section below the modules.
     */
    private function system(): NavigationSection
    {
        return new NavigationSection('shell.navigation.group.system', [
            new NavigationItem(
                label: 'shell.navigation.access',
                icon: 'shield-lock',
                permission: AdminPermission::ViewAccessControl,
                children: [
                    new NavigationItem(
                        label: 'shell.navigation.administrators',
                        route: 'admin.access.administrators.index',
                        icon: 'person-badge',
                        permission: AdminPermission::ManageAdministrators,
                    ),
                    new NavigationItem(
                        label: 'shell.navigation.roles',
                        route: 'admin.access.roles.index',
                        icon: 'key',
                        permission: AdminPermission::ManageRoles,
                    ),
                ],
            ),
            new NavigationItem(
                label: 'shell.navigation.settings',
                route: 'admin.settings',
                icon: 'gear',
                // The profile screen is reached from the user menu, but it
                // belongs under Settings in the hierarchy.
                activeWhen: ['admin.settings', 'admin.profile'],
            ),
        ]);
    }

    /**
     * The trail from the top of the tree down to the given route.
     *
     * @return list<NavigationItem>
     */
    public function trailTo(string $routeName): array
    {
        if (array_key_exists($routeName, $this->trails)) {
            return $this->trails[$routeName];
        }

        return $this->trails[$routeName] = $this->resolveTrail($routeName);
    }

    /**
     * @return list<NavigationItem>
     */
    private function resolveTrail(string $routeName): array
    {
        foreach ($this->sections() as $section) {
            foreach ($section->items as $item) {
                $trail = $item->trailTo($routeName);

                if ($trail !== []) {
                    return $trail;
                }
            }
        }

        return [];
    }

    /**
     * The trail for the current request.
     *
     * @return list<NavigationItem>
     */
    public function currentTrail(): array
    {
        $routeName = request()->route()?->getName();

        return $routeName === null ? [] : $this->trailTo($routeName);
    }

    /**
     * What the tree calls the current screen, or null for a screen it does
     * not list. The content header and the browser tab both show it.
     */
    public function currentTitle(): ?string
    {
        $trail = $this->currentTrail();

        return $trail === [] ? null : $trail[array_key_last($trail)]->title();
    }

    /**
     * Drop the memoised tree.
     *
     * Only needed when something changes mid-request that the tree depends on
     * — an administrator's roles, or the locale.
     */
    public function forget(): void
    {
        $this->sections = null;
        $this->trails = [];
    }
}

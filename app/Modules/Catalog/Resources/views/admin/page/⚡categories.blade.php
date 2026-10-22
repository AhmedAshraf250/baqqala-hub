<?php

use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\User;
use App\Modules\Catalog\Authorization\CatalogPermission;
use App\Modules\Catalog\Database\Models\Category;
use App\Modules\Catalog\Database\Models\Product;
use App\Modules\Catalog\Domain\Actions\DeleteCategory;
use App\Modules\Catalog\Domain\Actions\MoveCategory;
use App\Modules\Catalog\Domain\Actions\SetCategoryArchived;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The shop's shelves, in the order the picker shows their tiles: what each
 * is called, its icon, what is on it, and the shelves under it — two levels
 * at most.
 *
 * Moving one up or down, archiving an empty one, removing one that never held
 * anything: each asks for `catalog.manage` again.
 */
new #[Layout('admin::layout.app')] class extends Component {
    /** What the last action did, said once. */
    public ?string $notice = null;

    /** Why the last action could not be done. */
    public ?string $problem = null;

    public function rendering(View $view): void
    {
        $view->layoutData(['title' => __('catalog::module.navigation.categories')]);
    }

    /**
     * Every shelf, each sub-shelf after the one above it, with where it may
     * move and what it holds.
     *
     * @return list<array{category: Category, depth: int, first: bool, last: bool, holdsShelves: bool}>
     */
    #[Computed]
    public function shelves(): array
    {
        $categories = Category::query()
            ->inOrder()
            ->withCount(['products', 'products as on_sale_count' => fn ($query) => $query->where('is_active', true)])
            ->get();

        $rows = [];
        $tops = $categories->whereNull('parent_id')->values();

        foreach ($tops as $position => $top) {
            $below = $categories->where('parent_id', $top->id)->values();

            $rows[] = ['category' => $top, 'depth' => 0, 'first' => $position === 0, 'last' => $position === $tops->count() - 1, 'holdsShelves' => $below->isNotEmpty()];

            foreach ($below as $place => $shelf) {
                $rows[] = ['category' => $shelf, 'depth' => 1, 'first' => $place === 0, 'last' => $place === $below->count() - 1, 'holdsShelves' => false];
            }
        }

        return $rows;
    }

    /**
     * The shelves as cards: each top shelf with the ones under it.
     *
     * @return list<array{top: array{category: Category, depth: int, first: bool, last: bool, holdsShelves: bool}, below: list<array{category: Category, depth: int, first: bool, last: bool, holdsShelves: bool}>}>
     */
    #[Computed]
    public function cards(): array
    {
        $cards = [];

        foreach ($this->shelves as $row) {
            if ($row['depth'] === 0) {
                $cards[] = ['top' => $row, 'below' => []];
            } elseif ($cards !== []) {
                $cards[array_key_last($cards)]['below'][] = $row;
            }
        }

        return $cards;
    }

    /**
     * The figures over the cards, counted in the database.
     *
     * @return array{shelves: int, archivedShelves: int, onSale: int, archived: int}
     */
    #[Computed]
    public function totals(): array
    {
        $shelves = Category::query()->toBase()->selectRaw('COALESCE(SUM(CASE WHEN is_active THEN 1 ELSE 0 END), 0) AS active, COUNT(*) AS total')->first();
        $products = Product::query()->toBase()->selectRaw('COALESCE(SUM(CASE WHEN is_active THEN 1 ELSE 0 END), 0) AS active, COUNT(*) AS total')->first();

        return [
            'shelves' => (int) ($shelves->active ?? 0),
            'archivedShelves' => (int) ($shelves->total ?? 0) - (int) ($shelves->active ?? 0),
            'onSale' => (int) ($products->active ?? 0),
            'archived' => (int) ($products->total ?? 0) - (int) ($products->active ?? 0),
        ];
    }

    /**
     * A shelf's own colour for its tile, the same every time it is drawn, so
     * the eye finds it again by colour as well as by name.
     */
    public function toneOf(Category $category): string
    {
        $tones = ['blue', 'green', 'amber', 'purple', 'teal', 'pink', 'red', 'gray'];

        return 'tone-'.$tones[$category->id % count($tones)];
    }

    #[Computed]
    public function canManage(): bool
    {
        return Area::Admin->user()?->can(CatalogPermission::Manage->value) === true;
    }

    public function move(int $categoryId, int $step, MoveCategory $move): void
    {
        $this->authorizeManaging();
        $this->reset('notice', 'problem');

        $move->handle($categoryId, $step <=> 0);

        unset($this->shelves, $this->cards);
    }

    public function archive(int $categoryId, SetCategoryArchived $setArchived): void
    {
        $category = $this->category($categoryId);

        if (! $setArchived->handle($category->id, archived: true)) {
            $this->problem = __('catalog::module.categories.archive_refused', ['name' => $category->name]);

            return;
        }

        $this->notice = __('catalog::module.categories.archived_notice', ['name' => $category->name]);
    }

    public function restore(int $categoryId, SetCategoryArchived $setArchived): void
    {
        $category = $this->category($categoryId);

        if (! $setArchived->handle($category->id, archived: false)) {
            $this->problem = __('catalog::module.categories.restore_refused', ['name' => $category->name]);

            return;
        }

        $this->notice = __('catalog::module.categories.restored_notice', ['name' => $category->name]);
    }

    public function delete(int $categoryId, DeleteCategory $delete): void
    {
        $category = $this->category($categoryId);

        if (! $delete->handle($category->id)) {
            $this->problem = __('catalog::module.categories.delete_refused', ['name' => $category->name]);

            return;
        }

        $this->notice = __('catalog::module.categories.deleted_notice', ['name' => $category->name]);
    }

    #[On('category-saved')]
    public function showSaved(string $notice): void
    {
        $this->problem = null;
        $this->notice = $notice;
        unset($this->shelves, $this->cards, $this->totals);
    }

    private function category(int $categoryId): Category
    {
        $this->authorizeManaging();
        $this->reset('notice', 'problem');
        unset($this->shelves, $this->cards, $this->totals);

        return Category::query()->findOrFail($categoryId);
    }

    private function authorizeManaging(): User
    {
        $administrator = Area::Admin->user();

        abort_unless($administrator?->can(CatalogPermission::Manage->value) === true, 403);

        return $administrator;
    }
}; ?>

<div class="d-grid gap-3">
    {{-- The catalog at a glance. --}}
    <div class="row g-3" role="group" aria-label="{{ __('catalog::module.categories.overview') }}">
        <div class="col-6 col-sm-4">
            <x-admin::ui.stat
                icon="tags"
                variant="primary"
                :label="__('catalog::module.categories.stat_shelves')"
                :value="$this->totals['shelves']"
                :caption="trans_choice('catalog::module.categories.stat_shelves_caption', $this->totals['archivedShelves'], ['count' => $this->totals['archivedShelves']])"
            />
        </div>
        <div class="col-6 col-sm-4">
            <x-admin::ui.stat
                icon="box-seam"
                variant="success"
                :label="__('catalog::module.categories.stat_on_sale')"
                :value="$this->totals['onSale']"
                :caption="__('catalog::module.categories.stat_on_sale_caption')"
                :href="route('admin.catalog.products.index')"
            />
        </div>
        <div class="col-6 col-sm-4">
            <x-admin::ui.stat
                icon="archive"
                variant="secondary"
                :label="__('catalog::module.categories.stat_archived')"
                :value="$this->totals['archived']"
                :caption="__('catalog::module.categories.stat_archived_caption')"
                :href="route('admin.catalog.products.index', ['archived' => 1])"
            />
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 align-items-center">
        <p class="text-body-secondary mb-0 flex-grow-1">{{ __('catalog::module.categories.intro') }}</p>

        @if ($this->canManage)
            <button
                type="button"
                class="btn btn-primary d-inline-flex align-items-center gap-2"
                wire:click="$dispatch('edit-category', { categoryId: null })"
                data-shortcut-click="KeyN"
                aria-keyshortcuts="N"
            >
                <i class="bi bi-plus-lg" aria-hidden="true"></i>
                {{ __('catalog::module.categories.new') }}
                <kbd class="d-none d-md-inline kbd-hint" aria-hidden="true">N</kbd>
            </button>
        @endif
    </div>

    @if ($notice)
        <x-admin::ui.alert variant="success" icon="check-circle" dismissible wire:key="notice-{{ md5($notice) }}">{{ $notice }}</x-admin::ui.alert>
    @endif

    @if ($problem)
        <x-admin::ui.alert variant="warning" icon="exclamation-triangle" dismissible wire:key="problem-{{ md5($problem) }}">{{ $problem }}</x-admin::ui.alert>
    @endif

    @if ($this->cards === [])
        <x-admin::ui.card>
            <x-admin::ui.empty-state icon="tags" :title="__('catalog::module.categories.empty_title')" :description="__('catalog::module.categories.empty_description')" />
        </x-admin::ui.card>
    @else
        {{-- A card a shelf, in the order the picker shows them; ↑ ↓ move between them. --}}
        <div class="row g-3" id="category-rows" data-row-list>
            @foreach ($this->cards as $card)
                @php($category = $card['top']['category'])
                <div class="col-md-6 col-xxl-4" wire:key="category-card-{{ $category->id }}">
                    <article
                        id="category-row-{{ $category->id }}"
                        @class(['card h-100 category-card', 'category-archived' => ! $category->is_active])
                        data-row
                        tabindex="-1"
                        aria-labelledby="category-name-{{ $category->id }}"
                    >
                        <div class="card-body d-flex gap-3">
                            <span class="category-tile {{ $this->toneOf($category) }}" aria-hidden="true"><i class="bi bi-{{ $category->icon ?: 'tag' }}"></i></span>

                            <div class="min-w-0 flex-grow-1">
                                <h2 class="h6 mb-1" id="category-name-{{ $category->id }}">
                                    <a
                                        href="{{ route('admin.catalog.products.index', ['category' => $category->id]) }}"
                                        class="link-body-emphasis link-underline-opacity-0 link-underline-opacity-75-hover"
                                        dir="auto"
                                        @unless ($this->canManage) data-row-key="Enter" @endunless
                                    >{{ $category->name }}</a>
                                    @unless ($category->is_active)
                                        <span class="badge bg-body-tertiary text-body-emphasis border ms-1">{{ __('catalog::module.categories.archived') }}</span>
                                    @endunless
                                </h2>

                                <div class="small text-body-secondary">
                                    {{ trans_choice('catalog::module.categories.on_sale', $category->on_sale_count, ['count' => $category->on_sale_count]) }}
                                    @if ($category->products_count > $category->on_sale_count)
                                        {{ trans_choice('catalog::module.categories.archived_products', $category->products_count - $category->on_sale_count, ['count' => $category->products_count - $category->on_sale_count]) }}
                                    @endif
                                </div>

                                @if ($card['below'] !== [])
                                    <ul class="category-below-list" aria-label="{{ __('catalog::module.categories.below', ['name' => $category->name]) }}">
                                        @foreach ($card['below'] as $row)
                                            <li @class(['category-archived' => ! $row['category']->is_active]) wire:key="category-below-{{ $row['category']->id }}">
                                                <i class="bi bi-arrow-return-{{ is_rtl() ? 'left' : 'right' }} text-body-secondary" aria-hidden="true"></i>
                                                <div class="min-w-0 flex-grow-1">
                                                    <a href="{{ route('admin.catalog.products.index', ['category' => $row['category']->id]) }}" class="link-body-emphasis" dir="auto">{{ $row['category']->name }}</a>
                                                    <span class="small text-body-secondary">· {{ trans_choice('catalog::module.categories.on_sale', $row['category']->on_sale_count, ['count' => $row['category']->on_sale_count]) }}</span>
                                                    @unless ($row['category']->is_active)
                                                        <span class="badge bg-body-tertiary text-body-emphasis border ms-1">{{ __('catalog::module.categories.archived') }}</span>
                                                    @endunless
                                                </div>
                                                @if ($this->canManage)
                                                    @include('catalog::admin.category-actions', ['row' => $row, 'compact' => true])
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>

                        @if ($this->canManage)
                            <div class="card-footer bg-transparent">
                                @include('catalog::admin.category-actions', ['row' => $card['top'], 'compact' => false])
                            </div>
                        @endif
                    </article>
                </div>
            @endforeach
        </div>

        <p class="kbd-hints kbd-hint small text-body-secondary mb-0 d-none d-lg-flex flex-wrap gap-3">
            <span class="visually-hidden">{{ __('catalog::module.products.keys') }}:</span>
            <span><kbd dir="ltr">↑ ↓</kbd> {{ __('catalog::module.products.key_move') }}</span>
            <span><kbd>Enter</kbd> {{ $this->canManage ? __('catalog::module.categories.key_edit') : __('catalog::module.categories.key_open') }}</span>
            @if ($this->canManage)
                <span><kbd>N</kbd> {{ __('catalog::module.categories.key_new') }}</span>
            @endif
        </p>
    @endif

    @if ($this->canManage)
        <livewire:catalog::admin.category-form wire:key="category-form" />
    @endif
</div>

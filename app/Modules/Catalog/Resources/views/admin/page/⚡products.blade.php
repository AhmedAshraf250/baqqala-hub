<?php

use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\User;
use App\Foundation\Money\Money;
use App\Modules\Catalog\Areas\Admin\Forms\ProductForm;
use App\Modules\Catalog\Authorization\CatalogPermission;
use App\Modules\Catalog\Database\Models\Category;
use App\Modules\Catalog\Database\Models\Product;
use App\Modules\Catalog\Domain\Actions\ChangeProductPrice;
use App\Modules\Catalog\Domain\Actions\SetProductArchived;
use App\Modules\Catalog\Domain\Queries\ProductList;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every product, found by what the counter calls it or by one of its codes,
 * narrowed to a shelf — and its price changed where it stands, because
 * prices here change often: Enter on a row, the new price, Enter, ↓.
 *
 * The filters are in the page's address, so a narrowed list is a link. Each
 * change asks for `catalog.manage` again: a Livewire call reaches the
 * component directly, whatever the page showed.
 */
new #[Layout('admin::layout.app')] class extends Component {
    use WithPagination;

    /** The filters this list reads from its address. */
    private const array FILTERS = ['q', 'category', 'archived'];

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: null)]
    public ?int $category = null;

    #[Url(except: false)]
    public bool $archived = false;

    /** The product whose price is being typed, if one is. */
    public ?int $editing = null;

    public string $price = '';

    /**
     * The last price changed here — shown beside the product, and offered
     * back from the status line in case it was a slip.
     *
     * @var array{product: int, name: string, before: int, after: int}|null
     */
    public ?array $changed = null;

    /** What the drawer last did, said once. */
    public ?string $notice = null;

    /** Why the last action could not be done. */
    public ?string $problem = null;

    public function rendering(View $view): void
    {
        $view->layoutData(['title' => __('catalog::module.navigation.products')]);
    }

    public function updated(string $property): void
    {
        if (in_array($property, self::FILTERS, true)) {
            $this->resetPage();
            $this->cancelPrice();
        }
    }

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        return app(ProductList::class)->page($this->q, $this->category, $this->archived);
    }

    /**
     * @return array{active: int, archived: int}
     */
    #[Computed]
    public function counts(): array
    {
        return app(ProductList::class)->counts($this->q, $this->category);
    }

    /**
     * Every shelf for the filter, each sub-shelf after the one above it.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function shelves(): array
    {
        $categories = Category::query()->inOrder()->get();
        $shelves = [];

        foreach ($categories->whereNull('parent_id') as $top) {
            $shelves[$top->id] = $this->shelfLabel($top);

            foreach ($categories->where('parent_id', $top->id) as $below) {
                $shelves[$below->id] = $this->shelfLabel($below, $top);
            }
        }

        return $shelves;
    }

    #[Computed]
    public function canManage(): bool
    {
        return Area::Admin->user()?->can(CatalogPermission::Manage->value) === true;
    }

    /**
     * The shelf a product sits on, with the one above it if there is one.
     */
    public function shelfOf(Product $product): string
    {
        return $product->category === null ? '' : $this->shelfLabel($product->category, $product->category->parent, archived: false);
    }

    public function newProduct(): void
    {
        $this->authorizeManaging();

        $this->dispatch('edit-product', productId: null, name: trim($this->q), categoryId: $this->category);
    }

    public function editPrice(int $productId): void
    {
        $this->authorizeManaging();

        $product = Product::query()->findOrFail($productId);

        $this->resetErrorBag();
        $this->editing = $product->id;
        $this->price = $product->price->toDecimalString();
    }

    public function cancelPrice(): void
    {
        $this->resetErrorBag();
        $this->reset('editing', 'price');
    }

    public function savePrice(ChangeProductPrice $change): void
    {
        $this->authorizeManaging();

        if ($this->editing === null) {
            return;
        }

        $price = ProductForm::money($this->price);

        if ($price === null || ! $price->isPositive()) {
            $this->addError('price', __('catalog::module.product.price_invalid'));

            return;
        }

        $product = Product::query()->findOrFail($this->editing);
        $before = $change->handle($product->id, $price);

        $this->changed = $before->equals($price) ? null : [
            'product' => $product->id,
            'name' => $product->name,
            'before' => $before->minorUnits,
            'after' => $price->minorUnits,
        ];
        $this->notice = null;
        $this->cancelPrice();
    }

    /**
     * Put back the price the last change replaced — unless someone changed it
     * again since, which is then theirs to keep.
     */
    public function revertPrice(ChangeProductPrice $change): void
    {
        $this->authorizeManaging();

        if ($this->changed === null) {
            return;
        }

        $product = Product::query()->findOrFail($this->changed['product']);

        if ($product->price->minorUnits === $this->changed['after']) {
            $change->handle($product->id, Money::fromMinorUnits($this->changed['before']));
        }

        $this->changed = null;
    }

    /**
     * Put an archived product back on sale from its row — unless its shelf is
     * archived too, when it has to be given another first.
     */
    public function restore(int $productId, SetProductArchived $setArchived): void
    {
        $this->authorizeManaging();

        $product = Product::query()->findOrFail($productId);

        $this->reset('notice', 'problem', 'changed');
        unset($this->rows, $this->counts);

        if (! $setArchived->handle($product->id, archived: false)) {
            $this->problem = __('catalog::module.products.restore_refused', ['name' => $product->name]);

            return;
        }

        $this->notice = __('catalog::module.product.restored_notice', ['name' => $product->name]);
    }

    /**
     * The drawer saved, archived, or removed a product: say so, and read the
     * list again.
     */
    #[On('product-saved')]
    public function showSaved(string $notice): void
    {
        $this->notice = $notice;
        $this->problem = null;
        $this->changed = null;
        unset($this->rows, $this->counts);
    }

    public function money(int $minorUnits): string
    {
        return Money::fromMinorUnits($minorUnits)->toDisplayString();
    }

    private function shelfLabel(Category $category, ?Category $above = null, bool $archived = true): string
    {
        $label = $above === null ? $category->name : $above->name.' › '.$category->name;

        return $archived && ! $category->is_active ? __('catalog::module.products.archived_shelf', ['name' => $label]) : $label;
    }

    private function authorizeManaging(): User
    {
        $administrator = Area::Admin->user();

        abort_unless($administrator?->can(CatalogPermission::Manage->value) === true, 403);

        return $administrator;
    }
}; ?>

<div class="d-grid gap-3">
    {{-- Find a product: the biggest thing on the screen, with «new product»
         beside it — on a phone, as its icon. --}}
    <div class="d-flex gap-2 align-items-stretch">
        <div class="flex-grow-1 min-w-0">
            <label for="product-search" class="visually-hidden">{{ __('catalog::module.products.search') }}</label>
            <div class="input-group input-group-lg">
                <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                <input
                    id="product-search"
                    type="search"
                    class="form-control"
                    wire:model.live.debounce.200ms="q"
                    placeholder="{{ __('catalog::module.products.search_placeholder') }}"
                    autocomplete="off"
                    dir="auto"
                    data-shortcut-focus="Slash"
                    data-row-list-input="product-rows"
                    aria-keyshortcuts="/"
                >
                <span class="input-group-text d-none d-md-flex kbd-hint" aria-hidden="true"><kbd>/</kbd></span>
            </div>
        </div>

        @if ($this->canManage)
            <button
                type="button"
                class="btn btn-primary btn-lg d-inline-flex align-items-center gap-2"
                wire:click="newProduct"
                data-shortcut-click="KeyN"
                aria-keyshortcuts="N"
                aria-label="{{ __('catalog::module.products.new') }}"
            >
                <i class="bi bi-plus-lg" aria-hidden="true"></i>
                <span class="d-none d-sm-inline text-nowrap">{{ __('catalog::module.products.new') }}</span>
                <kbd class="d-none d-md-inline kbd-hint" aria-hidden="true">N</kbd>
            </button>
        @endif
    </div>

    {{-- Which shelf, and whether on sale or archived. --}}
    <div class="d-flex flex-wrap gap-3 align-items-end">
        <div>
            <label id="filter-category-label" for="filter-category-toggle" class="form-label small mb-1">{{ __('catalog::module.products.filter_category') }}</label>
            <x-admin::form.search-select
                id="filter-category"
                small
                wire:model.live="category"
                :options="$this->shelves"
                :selected="$category"
                :empty="__('catalog::module.products.all_categories')"
            />
        </div>

        <div class="d-flex flex-wrap gap-2" role="group" aria-label="{{ __('catalog::module.products.status') }}">
            <button type="button" class="btn chip" wire:click="$set('archived', false)" aria-pressed="{{ $archived ? 'false' : 'true' }}">
                <i class="bi bi-check2 chip-tick" aria-hidden="true"></i>
                {{ __('catalog::module.products.on_sale') }}
                <span class="chip-count num" dir="ltr">{{ $this->counts['active'] }}</span>
            </button>
            <button type="button" class="btn chip" wire:click="$set('archived', true)" aria-pressed="{{ $archived ? 'true' : 'false' }}">
                <i class="bi bi-check2 chip-tick" aria-hidden="true"></i>
                <i class="bi bi-archive" aria-hidden="true"></i>
                {{ __('catalog::module.products.archived') }}
                <span class="chip-count num" dir="ltr">{{ $this->counts['archived'] }}</span>
            </button>
        </div>
    </div>

    @if ($notice)
        <x-admin::ui.alert variant="success" icon="check-circle" dismissible wire:key="notice-{{ md5($notice) }}">{{ $notice }}</x-admin::ui.alert>
    @endif

    @if ($problem)
        <x-admin::ui.alert variant="warning" icon="exclamation-triangle" dismissible wire:key="problem-{{ md5($problem) }}">{{ $problem }}</x-admin::ui.alert>
    @endif

    {{-- Unmistakable: this is the archive, not what is on sale. --}}
    @if ($archived)
        <div class="archive-banner" role="status">
            <span class="archive-banner-icon" aria-hidden="true"><i class="bi bi-archive"></i></span>
            <div class="flex-grow-1">
                <div class="fw-semibold">{{ __('catalog::module.products.archived_title') }}</div>
                <div class="small">{{ __('catalog::module.products.archived_banner') }}</div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="$set('archived', false)">
                <i class="bi bi-arrow-return-{{ is_rtl() ? 'right' : 'left' }} me-1" aria-hidden="true"></i>{{ __('catalog::module.products.show_on_sale') }}
            </button>
        </div>
    @endif

    <x-admin::ui.card flush>
        {{-- How many, or what the last price change was, with the way back. --}}
        <div class="d-flex flex-wrap align-items-center gap-2 small px-3 py-2 border-bottom bg-body-tertiary list-status" role="status">
            @if ($changed)
                <i class="bi bi-check-circle" aria-hidden="true"></i>
                <span class="flex-grow-1" dir="auto">
                    {{ __('catalog::module.products.price_changed', ['name' => $changed['name'], 'price' => $this->money($changed['after']), 'before' => $this->money($changed['before'])]) }}
                </span>
                <button type="button" class="btn btn-sm btn-outline-secondary py-0" wire:click="revertPrice">
                    <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>{{ __('catalog::module.products.revert', ['price' => $this->money($changed['before'])]) }}
                </button>
            @else
                <span class="fw-semibold">{{ trans_choice('catalog::module.products.count', $this->rows->total(), ['count' => $this->rows->total()]) }}</span>
            @endif
        </div>

        <div wire:loading.delay.class="is-refreshing" wire:target="q,category,archived,gotoPage,nextPage,previousPage">
            @if ($this->rows->isEmpty())
                @if (trim($q) !== '')
                    <x-admin::ui.empty-state icon="search" :title="__('catalog::module.products.no_match', ['term' => trim($q)])">
                        @if ($this->canManage)
                            <button type="button" class="btn btn-primary" wire:click="newProduct">
                                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>
                                {{ __('catalog::module.products.add_named', ['term' => trim($q)]) }}
                            </button>
                        @endif
                    </x-admin::ui.empty-state>
                @elseif ($archived)
                    <x-admin::ui.empty-state icon="archive" :title="__('catalog::module.products.none_archived')" />
                @else
                    <x-admin::ui.empty-state icon="box-seam" :title="__('catalog::module.products.empty_title')" :description="__('catalog::module.products.empty_description')">
                        @if ($this->canManage)
                            <button type="button" class="btn btn-primary" wire:click="newProduct">
                                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>
                                {{ __('catalog::module.products.new') }}
                            </button>
                        @endif
                    </x-admin::ui.empty-state>
                @endif
            @else
                <x-admin::table.table :striped="false" id="product-rows" data-row-list>
                    <x-slot:head>
                        <x-admin::table.heading>{{ __('catalog::module.products.column_product') }}</x-admin::table.heading>
                        <x-admin::table.heading class="d-none d-md-table-cell">{{ __('catalog::module.products.column_category') }}</x-admin::table.heading>
                        <x-admin::table.heading class="d-none d-sm-table-cell">{{ __('catalog::module.products.column_unit') }}</x-admin::table.heading>
                        <x-admin::table.heading align="end">{{ __('catalog::module.products.column_price') }}</x-admin::table.heading>
                        @if ($this->canManage)
                            <x-admin::table.heading align="end"><span class="visually-hidden">{{ __('catalog::module.products.column_actions') }}</span></x-admin::table.heading>
                        @endif
                    </x-slot:head>

                    @foreach ($this->rows as $product)
                        <tr wire:key="product-{{ $product->id }}" id="product-row-{{ $product->id }}" data-row tabindex="-1" @class(['product-archived' => ! $product->is_active])>
                            <td>
                                <div class="fw-semibold text-break" dir="auto">{{ $product->name }}</div>
                                <div class="small text-body-secondary d-flex flex-wrap column-gap-2">
                                    <span class="d-md-none" dir="auto">{{ $this->shelfOf($product) }}</span>
                                    <span class="d-sm-none">{{ $product->unit->label() }}</span>
                                    @if ($product->search_terms)
                                        <span class="text-break" dir="auto">{{ $product->search_terms }}</span>
                                    @endif
                                    @if ($product->barcode)
                                        <span><i class="bi bi-upc me-1" aria-hidden="true"></i><span class="visually-hidden">{{ __('catalog::module.product.barcode') }}</span><span class="num" dir="ltr">{{ $product->barcode }}</span></span>
                                    @endif
                                </div>
                            </td>

                            <td class="d-none d-md-table-cell" dir="auto">{{ $this->shelfOf($product) }}</td>

                            <td class="d-none d-sm-table-cell">{{ $product->unit->label() }}</td>

                            <td class="text-end">
                                @if ($editing === $product->id)
                                    <div class="price-edit ms-auto">
                                        <label for="price-edit" class="visually-hidden">{{ __('catalog::module.products.new_price_for', ['name' => $product->name]) }}</label>
                                        <div class="input-group input-group-sm">
                                            <input
                                                id="price-edit"
                                                type="text"
                                                inputmode="decimal"
                                                dir="ltr"
                                                autocomplete="off"
                                                wire:model="price"
                                                wire:keydown.enter.prevent="savePrice"
                                                wire:keydown.escape.prevent="cancelPrice"
                                                data-inline-edit
                                                aria-describedby="price-edit-help{{ $errors->has('price') ? ' price-edit-error' : '' }}"
                                                @if ($errors->has('price')) aria-invalid="true" @endif
                                                @class(['form-control num text-end', 'is-invalid' => $errors->has('price')])
                                            >
                                            <span class="input-group-text">{{ currency_symbol() }}</span>
                                        </div>
                                        <div id="price-edit-help" class="small text-body-secondary text-nowrap mt-1">
                                            <kbd>Enter</kbd> {{ __('catalog::module.products.key_save') }} · <kbd>Esc</kbd> {{ __('catalog::module.products.key_cancel') }}
                                        </div>
                                        @error('price')<div id="price-edit-error" class="invalid-feedback d-block text-start">{{ $message }}</div>@enderror
                                    </div>
                                @elseif ($this->canManage)
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-secondary num price-button"
                                        dir="ltr"
                                        wire:click="editPrice({{ $product->id }})"
                                        data-row-key="Enter"
                                        aria-label="{{ __('catalog::module.products.change_price', ['name' => $product->name, 'price' => $product->price->toDisplayString()]) }}"
                                    >{{ $product->price->toDisplayString() }}</button>
                                @else
                                    <span class="num" dir="ltr">{{ $product->price->toDisplayString() }}</span>
                                @endif

                                @if ($changed && $changed['product'] === $product->id && $editing !== $product->id)
                                    <div class="small text-body-secondary">{{ __('catalog::module.products.was', ['price' => $this->money($changed['before'])]) }}</div>
                                @endif
                            </td>

                            @if ($this->canManage)
                                <td class="text-end text-nowrap">
                                    @unless ($product->is_active)
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-primary me-1"
                                            wire:click="restore({{ $product->id }})"
                                            wire:loading.attr="disabled"
                                            aria-label="{{ __('catalog::module.products.restore_named', ['name' => $product->name]) }}"
                                        >
                                            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                                            <span class="d-none d-md-inline">{{ __('catalog::module.product.restore') }}</span>
                                        </button>
                                    @endunless
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-secondary"
                                        wire:click="$dispatch('edit-product', { productId: {{ $product->id }} })"
                                        aria-label="{{ __('catalog::module.products.edit', ['name' => $product->name]) }}"
                                    >
                                        <i class="bi bi-pencil" aria-hidden="true"></i>
                                        <span class="d-none d-lg-inline">{{ __('shell.actions.edit') }}</span>
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </x-admin::table.table>
            @endif
        </div>

        <x-slot:footer>
            <div class="d-flex flex-wrap align-items-center gap-3">
                <p class="kbd-hints kbd-hint small text-body-secondary mb-0 d-none d-lg-flex flex-wrap gap-3">
                    <span class="visually-hidden">{{ __('catalog::module.products.keys') }}:</span>
                    <span><kbd dir="ltr">↑ ↓</kbd> {{ __('catalog::module.products.key_move') }}</span>
                    @if ($this->canManage)
                        <span><kbd>Enter</kbd> {{ __('catalog::module.products.key_price') }}</span>
                    @endif
                    <span><kbd>/</kbd> {{ __('catalog::module.products.key_search') }}</span>
                    @if ($this->canManage)
                        <span><kbd>N</kbd> {{ __('catalog::module.products.key_new') }}</span>
                    @endif
                </p>

                <div class="ms-auto">
                    {{ $this->rows->links('admin::table.pagination') }}
                </div>
            </div>
        </x-slot:footer>
    </x-admin::ui.card>

    @if ($this->canManage)
        <livewire:catalog::admin.product-drawer wire:key="product-drawer" />
    @endif
</div>

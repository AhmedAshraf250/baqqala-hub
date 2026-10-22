<?php

use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\User;
use App\Modules\Catalog\Areas\Admin\Forms\ProductForm;
use App\Modules\Catalog\Authorization\CatalogPermission;
use App\Modules\Catalog\Contracts\Enums\SaleUnit;
use App\Modules\Catalog\Database\Models\Category;
use App\Modules\Catalog\Database\Models\Product;
use App\Modules\Catalog\Domain\Actions\DeleteProduct;
use App\Modules\Catalog\Domain\Actions\SaveProduct;
use App\Modules\Catalog\Domain\Actions\SetProductArchived;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * A product, new or changed: what the counter calls it, its shelf, how it is
 * sold and for how much, and the other names and codes it is found by. For
 * one that exists, also taking it off sale, putting it back, or — when it
 * was never sold — removing it.
 *
 * Behind `catalog.manage`, asked again by every action.
 */
new class extends Component {
    public ProductForm $form;

    /** Whether the product open here is archived. */
    public bool $archived = false;

    /** Set when a removal was refused because the product was sold. */
    public bool $held = false;

    #[On('edit-product')]
    public function open(?int $productId = null, string $name = '', ?int $categoryId = null): void
    {
        $this->authorizeManaging();

        $product = $productId === null ? null : Product::query()->findOrFail($productId);
        $shelf = $categoryId !== null && array_key_exists($categoryId, $this->shelves) ? $categoryId : null;

        $this->form->load($product, $name, $shelf);
        $this->archived = $product !== null && ! $product->is_active;
        $this->held = false;

        $this->dispatch('overlay-show', id: 'product-drawer');
    }

    /**
     * The shelves a product may be put on — those in use — each sub-shelf
     * after the one above it.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function shelves(): array
    {
        $categories = Category::query()->active()->inOrder()->get();
        $shelves = [];

        foreach ($categories->whereNull('parent_id') as $top) {
            $shelves[$top->id] = $top->name;

            foreach ($categories->where('parent_id', $top->id) as $below) {
                $shelves[$below->id] = $top->name.' › '.$below->name;
            }
        }

        return $shelves;
    }

    /**
     * @return list<SaleUnit>
     */
    #[Computed]
    public function units(): array
    {
        return SaleUnit::cases();
    }

    /**
     * The unit the price is per, as the field's label says it.
     */
    #[Computed]
    public function unitLabel(): string
    {
        return SaleUnit::tryFrom($this->form->unit)?->label() ?? '';
    }

    public function save(SaveProduct $saveProduct): void
    {
        $this->authorizeManaging();

        $this->form->normalise();
        $this->form->validate();

        $isNew = $this->form->productId === null;
        $product = $saveProduct->handle($this->form->details(), $this->form->productId);

        $this->finish(__($isNew ? 'catalog::module.product.added' : 'catalog::module.product.saved', ['name' => $product->name]));
    }

    public function archive(SetProductArchived $setArchived): void
    {
        $product = $this->product();

        $setArchived->handle($product->id, archived: true);

        $this->finish(__('catalog::module.product.archived_notice', ['name' => $product->name]));
    }

    public function restore(SetProductArchived $setArchived): void
    {
        $product = $this->product();

        if (! $setArchived->handle($product->id, archived: false)) {
            $this->addError('form.categoryId', __('catalog::module.product.restore_needs_shelf'));

            return;
        }

        $this->finish(__('catalog::module.product.restored_notice', ['name' => $product->name]));
    }

    public function delete(DeleteProduct $delete): void
    {
        $product = $this->product();

        if (! $delete->handle($product->id)) {
            $this->held = true;

            return;
        }

        $this->finish(__('catalog::module.product.deleted_notice', ['name' => $product->name]));
    }

    private function product(): Product
    {
        $this->authorizeManaging();

        return Product::query()->findOrFail((int) $this->form->productId);
    }

    private function finish(string $notice): void
    {
        $this->dispatch('overlay-hide', id: 'product-drawer');
        $this->dispatch('product-saved', notice: $notice);
    }

    private function authorizeManaging(): User
    {
        $administrator = Area::Admin->user();

        abort_unless($administrator?->can(CatalogPermission::Manage->value) === true, 403);

        return $administrator;
    }
}; ?>

<div
    class="offcanvas offcanvas-end"
    tabindex="-1"
    id="product-drawer"
    aria-labelledby="product-drawer-title"
    data-ctrl-enter
    wire:ignore.self
>
    <div class="offcanvas-header border-bottom">
        <h2 class="offcanvas-title h5 d-flex align-items-center gap-2" id="product-drawer-title">
            <i class="bi bi-box-seam text-body-secondary" aria-hidden="true"></i>
            {{ $form->productId ? __('catalog::module.product.title_edit') : __('catalog::module.product.title_new') }}
        </h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('shell.actions.close') }}"></button>
    </div>

    <form id="product-form" class="offcanvas-body d-flex flex-column gap-3" wire:submit="save" novalidate>
        @if ($archived)
            <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2 mb-0 py-2" role="status">
                <i class="bi bi-archive" aria-hidden="true"></i>
                <span class="flex-grow-1">{{ __('catalog::module.product.is_archived') }}</span>
                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="restore" wire:loading.attr="disabled">
                    {{ __('catalog::module.product.restore') }}
                </button>
            </div>
        @endif

        <div>
            <label for="product-name" class="form-label fw-semibold">{{ __('catalog::module.product.name') }}</label>
            <input
                id="product-name"
                type="text"
                wire:model="form.name"
                dir="auto"
                maxlength="255"
                autocomplete="off"
                data-autofocus
                aria-describedby="product-name-help{{ $errors->has('form.name') ? ' product-name-error' : '' }}"
                @if ($errors->has('form.name')) aria-invalid="true" @endif
                @class(['form-control form-control-lg', 'is-invalid' => $errors->has('form.name')])
            >
            <div id="product-name-help" class="form-text">{{ __('catalog::module.product.name_help') }}</div>
            @error('form.name')<div id="product-name-error" class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div>
            <label id="product-category-label" for="product-category-toggle" class="form-label fw-semibold">{{ __('catalog::module.product.category') }}</label>
            <x-admin::form.search-select
                id="product-category"
                wire:model="form.categoryId"
                :options="$this->shelves"
                :selected="$form->categoryId"
                :placeholder="__('catalog::module.product.choose_category')"
                :invalid="$errors->has('form.categoryId')"
                :describedby="$errors->has('form.categoryId') ? 'product-category-error' : null"
            />
            @error('form.categoryId')<div id="product-category-error" class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <fieldset>
            <legend class="form-label fw-semibold fs-6">{{ __('catalog::module.product.unit') }}</legend>
            <div class="d-flex flex-wrap gap-2">
                @foreach ($this->units as $unit)
                    <input type="radio" class="btn-check" id="product-unit-{{ $unit->value }}" value="{{ $unit->value }}" wire:model.live="form.unit" wire:key="product-unit-input-{{ $unit->value }}">
                    <label class="btn btn-outline-primary" for="product-unit-{{ $unit->value }}" wire:key="product-unit-label-{{ $unit->value }}">
                        {{ __('catalog::module.product.sold_by.'.$unit->value) }}
                    </label>
                @endforeach
            </div>
            @error('form.unit')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </fieldset>

        <div>
            <label for="product-price" class="form-label fw-semibold">{{ __('catalog::module.product.price_per', ['unit' => $this->unitLabel]) }}</label>
            <div class="input-group input-group-lg">
                <input
                    id="product-price"
                    type="text"
                    inputmode="decimal"
                    dir="ltr"
                    autocomplete="off"
                    wire:model="form.price"
                    @if ($errors->has('form.price')) aria-invalid="true" aria-describedby="product-price-error" @endif
                    @class(['form-control num', 'is-invalid' => $errors->has('form.price')])
                >
                <span class="input-group-text">{{ currency_symbol() }}</span>
                @error('form.price')<div id="product-price-error" class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div>
            <label for="product-terms" class="form-label fw-semibold">{{ __('catalog::module.product.search_terms') }}</label>
            <input
                id="product-terms"
                type="text"
                wire:model="form.searchTerms"
                dir="auto"
                maxlength="255"
                autocomplete="off"
                aria-describedby="product-terms-help{{ $errors->has('form.searchTerms') ? ' product-terms-error' : '' }}"
                @if ($errors->has('form.searchTerms')) aria-invalid="true" @endif
                @class(['form-control', 'is-invalid' => $errors->has('form.searchTerms')])
            >
            <div id="product-terms-help" class="form-text">{{ __('catalog::module.product.search_terms_help') }}</div>
            @error('form.searchTerms')<div id="product-terms-error" class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="row g-2">
            <div class="col-sm-6">
                <label for="product-barcode" class="form-label">{{ __('catalog::module.product.barcode') }} <span class="text-body-secondary small">{{ __('catalog::module.product.optional') }}</span></label>
                <input
                    id="product-barcode"
                    type="text"
                    dir="ltr"
                    autocomplete="off"
                    wire:model="form.barcode"
                    @if ($errors->has('form.barcode')) aria-invalid="true" aria-describedby="product-barcode-error" @endif
                    @class(['form-control num', 'is-invalid' => $errors->has('form.barcode')])
                >
                @error('form.barcode')<div id="product-barcode-error" class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-6">
                <label for="product-sku" class="form-label">{{ __('catalog::module.product.sku') }} <span class="text-body-secondary small">{{ __('catalog::module.product.optional') }}</span></label>
                <input
                    id="product-sku"
                    type="text"
                    dir="ltr"
                    autocomplete="off"
                    wire:model="form.sku"
                    @if ($errors->has('form.sku')) aria-invalid="true" aria-describedby="product-sku-error" @endif
                    @class(['form-control num', 'is-invalid' => $errors->has('form.sku')])
                >
                @error('form.sku')<div id="product-sku-error" class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        {{-- Off sale, or gone: kept apart from the fields, and quiet. --}}
        @if ($form->productId)
            <div class="border-top pt-3 mt-auto">
                @if ($held)
                    <div class="alert alert-warning mb-2 py-2" role="alert">{{ __('catalog::module.product.delete_refused') }}</div>
                @endif

                <div class="d-flex flex-wrap gap-2">
                    @unless ($archived)
                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            wire:click="archive"
                            data-confirm="{{ __('catalog::module.product.archive_confirm', ['name' => $form->name]) }}"
                            data-confirm-tone="warning"
                            data-confirm-ok="{{ __('catalog::module.product.archive_ok') }}"
                            wire:loading.attr="disabled"
                        >
                            <i class="bi bi-archive me-1" aria-hidden="true"></i>{{ __('catalog::module.product.archive') }}
                        </button>
                    @endunless
                    @unless ($held)
                        <button
                            type="button"
                            class="btn btn-outline-danger"
                            wire:click="delete"
                            data-confirm="{{ __('catalog::module.product.delete_confirm', ['name' => $form->name]) }}"
                            data-confirm-ok="{{ __('catalog::module.product.delete_ok') }}"
                            wire:loading.attr="disabled"
                        >
                            <i class="bi bi-trash me-1" aria-hidden="true"></i>{{ __('catalog::module.product.delete') }}
                        </button>
                    @endunless
                </div>
                <p class="small text-body-secondary mt-2 mb-0">{{ __('catalog::module.product.archive_help') }}</p>
            </div>
        @endif
    </form>

    <div class="border-top p-3 d-flex gap-2">
        <button
            type="submit"
            form="product-form"
            class="btn btn-lg btn-primary flex-grow-1"
            wire:loading.attr="disabled"
            wire:target="save"
            data-ctrl-enter-submit
            aria-keyshortcuts="Control+Enter"
        >
            <span class="spinner-border spinner-border-sm me-1" aria-hidden="true" wire:loading wire:target="save"></span>
            {{ $form->productId ? __('catalog::module.product.save') : __('catalog::module.product.add') }}
            <kbd class="ms-1 d-none d-md-inline kbd-hint" aria-hidden="true">Ctrl Enter</kbd>
        </button>
        <button type="button" class="btn btn-lg btn-outline-secondary" data-bs-dismiss="offcanvas">{{ __('shell.actions.cancel') }}</button>
    </div>
</div>

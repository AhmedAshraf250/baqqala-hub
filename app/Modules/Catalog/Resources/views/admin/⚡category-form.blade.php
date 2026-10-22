<?php

use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\User;
use App\Modules\Catalog\Authorization\CatalogPermission;
use App\Modules\Catalog\Database\Models\Category;
use App\Modules\Catalog\Domain\Actions\SaveCategory;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * A shelf, new or changed: its name, the icon on its tile, and the shelf it
 * sits under, if any — two levels at most, so a shelf with shelves under it
 * stays at the top, and only a top shelf can hold others.
 */
new class extends Component {
    public ?int $categoryId = null;

    public string $name = '';

    public ?int $parentId = null;

    public string $icon = '';

    /** Whether the shelf open here holds shelves of its own. */
    public bool $holdsShelves = false;

    #[On('edit-category')]
    public function open(?int $categoryId = null): void
    {
        $this->authorizeManaging();
        $this->resetErrorBag();

        $category = $categoryId === null ? null : Category::query()->withCount('children')->findOrFail($categoryId);

        $this->categoryId = $category?->id;
        $this->name = $category->name ?? '';
        $this->parentId = $category?->parent_id;
        $this->icon = $category->icon ?? '';
        $this->holdsShelves = ($category->children_count ?? 0) > 0;

        $this->dispatch('overlay-show', id: 'category-form');
    }

    /**
     * The shelves this one may sit under: top shelves in use, itself aside.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function parents(): array
    {
        return Category::query()
            ->active()
            ->whereNull('parent_id')
            ->when($this->categoryId !== null, fn ($query) => $query->whereKeyNot($this->categoryId))
            ->inOrder()
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function icons(): array
    {
        return Category::ICONS;
    }

    public function save(SaveCategory $saveCategory): void
    {
        $this->authorizeManaging();

        $this->name = trim($this->name);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($this->categoryId)],
            'parentId' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->whereNull('parent_id')->where('is_active', true)->whereNull('deleted_at'),
                Rule::notIn(array_filter([$this->categoryId])),
                Rule::prohibitedIf($this->holdsShelves),
            ],
            'icon' => ['nullable', 'string', Rule::in(Category::ICONS)],
        ], [
            'parentId.prohibited' => __('catalog::module.category.holds_shelves'),
        ], [
            'name' => __('catalog::module.category.name'),
            'parentId' => __('catalog::module.category.parent'),
            'icon' => __('catalog::module.category.icon'),
        ]);

        $isNew = $this->categoryId === null;

        $category = $saveCategory->handle([
            'name' => $validated['name'],
            'parent_id' => $validated['parentId'] ?? null,
            'icon' => ($validated['icon'] ?? '') === '' ? null : $validated['icon'],
        ], $this->categoryId);

        $this->dispatch('overlay-hide', id: 'category-form');
        $this->dispatch('category-saved', notice: __($isNew ? 'catalog::module.category.added' : 'catalog::module.category.saved', ['name' => $category->name]));
    }

    private function authorizeManaging(): User
    {
        $administrator = Area::Admin->user();

        abort_unless($administrator?->can(CatalogPermission::Manage->value) === true, 403);

        return $administrator;
    }
}; ?>

<div class="modal fade" id="category-form" tabindex="-1" aria-labelledby="category-form-title" aria-hidden="true" wire:ignore.self>
    <div class="modal-dialog modal-dialog-scrollable">
        <form class="modal-content" wire:submit="save" novalidate>
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="category-form-title">
                    {{ $categoryId ? __('catalog::module.category.title_edit') : __('catalog::module.category.title_new') }}
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('shell.actions.close') }}"></button>
            </div>

            <div class="modal-body d-grid gap-3">
                <div>
                    <label for="category-name" class="form-label fw-semibold">{{ __('catalog::module.category.name') }}</label>
                    <input
                        id="category-name"
                        type="text"
                        wire:model="name"
                        dir="auto"
                        maxlength="255"
                        autocomplete="off"
                        data-autofocus
                        @if ($errors->has('name')) aria-invalid="true" aria-describedby="category-name-error" @endif
                        @class(['form-control', 'is-invalid' => $errors->has('name')])
                    >
                    @error('name')<div id="category-name-error" class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label id="category-parent-label" for="category-parent-toggle" class="form-label fw-semibold">{{ __('catalog::module.category.parent') }}</label>
                    @if ($holdsShelves)
                        <input id="category-parent-toggle" type="text" class="form-control" value="{{ __('catalog::module.category.no_parent') }}" aria-describedby="category-parent-help" readonly>
                    @else
                        <x-admin::form.search-select
                            id="category-parent"
                            wire:model="parentId"
                            :options="$this->parents"
                            :selected="$parentId"
                            :empty="__('catalog::module.category.no_parent')"
                            :invalid="$errors->has('parentId')"
                            :describedby="'category-parent-help'.($errors->has('parentId') ? ' category-parent-error' : '')"
                        />
                    @endif
                    <div id="category-parent-help" class="form-text">
                        {{ $holdsShelves ? __('catalog::module.category.holds_shelves') : __('catalog::module.category.parent_help') }}
                    </div>
                    @error('parentId')<div id="category-parent-error" class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <fieldset>
                    <legend class="form-label fw-semibold fs-6">{{ __('catalog::module.category.icon') }}</legend>
                    <div class="icon-choices">
                        <input type="radio" class="btn-check" id="category-icon-none" value="" wire:model="icon">
                        <label class="btn btn-outline-secondary icon-choice" for="category-icon-none">
                            <span class="small">{{ __('catalog::module.category.no_icon') }}</span>
                        </label>
                        @foreach ($this->icons as $choice)
                            <input type="radio" class="btn-check" id="category-icon-{{ $choice }}" value="{{ $choice }}" wire:model="icon" wire:key="category-icon-input-{{ $choice }}">
                            <label class="btn btn-outline-secondary icon-choice" for="category-icon-{{ $choice }}" wire:key="category-icon-label-{{ $choice }}" title="{{ __('catalog::module.icons.'.$choice) }}">
                                <i class="bi bi-{{ $choice }}" aria-hidden="true"></i>
                                <span class="visually-hidden">{{ __('catalog::module.icons.'.$choice) }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('icon')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </fieldset>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('shell.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
                    {{ $categoryId ? __('catalog::module.category.save') : __('catalog::module.category.add') }}
                </button>
            </div>
        </form>
    </div>
</div>

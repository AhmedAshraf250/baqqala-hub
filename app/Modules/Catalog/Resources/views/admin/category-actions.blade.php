{{--
    What may be done with one shelf: move it up or down among the ones beside
    it, edit it, archive it or bring it back, and — when it holds nothing —
    delete it. Archiving and deleting ask first.

    Included with `$row` (a shelf as the categories screen lists it) and
    `$compact` (icons only, for a sub-shelf's line inside its card).
--}}
@php($category = $row['category'])

<div class="d-inline-flex flex-wrap align-items-center gap-1">
    <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('catalog::module.categories.order', ['name' => $category->name]) }}">
        <button type="button" class="btn btn-outline-secondary" wire:click="move({{ $category->id }}, -1)" @disabled($row['first']) aria-label="{{ __('catalog::module.categories.move_up', ['name' => $category->name]) }}">
            <i class="bi bi-arrow-up" aria-hidden="true"></i>
        </button>
        <button type="button" class="btn btn-outline-secondary" wire:click="move({{ $category->id }}, 1)" @disabled($row['last']) aria-label="{{ __('catalog::module.categories.move_down', ['name' => $category->name]) }}">
            <i class="bi bi-arrow-down" aria-hidden="true"></i>
        </button>
    </div>

    <button
        type="button"
        class="btn btn-sm btn-outline-secondary"
        wire:click="$dispatch('edit-category', { categoryId: {{ $category->id }} })"
        @unless ($compact) data-row-key="Enter" @endunless
        aria-label="{{ __('catalog::module.categories.edit', ['name' => $category->name]) }}"
    >
        <i class="bi bi-pencil" aria-hidden="true"></i>
        @unless ($compact)<span class="d-none d-md-inline ms-1">{{ __('shell.actions.edit') }}</span>@endunless
    </button>

    @if ($category->is_active)
        <button
            type="button"
            class="btn btn-sm btn-outline-secondary"
            wire:click="archive({{ $category->id }})"
            data-confirm="{{ __('catalog::module.categories.archive_confirm', ['name' => $category->name]) }}"
            data-confirm-tone="warning"
            data-confirm-ok="{{ __('catalog::module.categories.archive_ok') }}"
            aria-label="{{ __('catalog::module.categories.archive', ['name' => $category->name]) }}"
        >
            <i class="bi bi-archive" aria-hidden="true"></i>
        </button>
    @else
        <button type="button" class="btn btn-sm btn-outline-primary" wire:click="restore({{ $category->id }})" aria-label="{{ __('catalog::module.categories.restore', ['name' => $category->name]) }}">
            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
        </button>
    @endif

    @if ($category->products_count === 0 && ! $row['holdsShelves'])
        <button
            type="button"
            class="btn btn-sm btn-outline-danger"
            wire:click="delete({{ $category->id }})"
            data-confirm="{{ __('catalog::module.categories.delete_confirm', ['name' => $category->name]) }}"
            data-confirm-ok="{{ __('catalog::module.categories.delete_ok') }}"
            aria-label="{{ __('catalog::module.categories.delete', ['name' => $category->name]) }}"
        >
            <i class="bi bi-trash" aria-hidden="true"></i>
        </button>
    @endif
</div>

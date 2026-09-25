{{--
    One sidebar entry. Renders a plain link, or an AdminLTE treeview branch when
    the item has children. Recurses, so nesting depth is a data decision.
--}}
@props(['item'])

@php
    $isActive = $item->isActive();
@endphp

<li @class(['nav-item', 'menu-open' => $item->hasChildren() && $isActive])>
    <a
        href="{{ $item->url() }}"
        @class(['nav-link', 'active' => $isActive])
        @if ($isActive && ! $item->hasChildren()) aria-current="page" @endif
    >
        <i class="nav-icon bi bi-{{ $item->icon }}" aria-hidden="true"></i>
        <p>
            {{ __($item->label) }}

            @if ($item->hasChildren())
                <i class="nav-arrow bi bi-chevron-{{ is_rtl() ? 'left' : 'right' }}" aria-hidden="true"></i>
            @endif
        </p>
    </a>

    @if ($item->hasChildren())
        <ul class="nav nav-treeview" role="list" aria-label="{{ __($item->label) }}">
            @foreach ($item->visibleChildren() as $child)
                <x-admin::layout.sidebar.item :item="$child" />
            @endforeach
        </ul>
    @endif
</li>

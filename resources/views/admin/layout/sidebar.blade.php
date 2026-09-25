{{--
    The .app-sidebar region.

    `data-bs-theme="dark"` is AdminLTE's dark-rail-on-light-page pattern, and
    `data-enable-persistence` makes PushMenu remember the collapsed state.

    `$sections` comes from App\Admin\View\Components\Layout\Sidebar.
--}}

<aside
    class="app-sidebar bg-body-secondary shadow"
    data-bs-theme="dark"
    data-enable-persistence="true"
>
    <div class="sidebar-brand">
        <a href="{{ route('admin.dashboard') }}" class="brand-link">
            <span class="brand-image d-inline-flex align-items-center justify-content-center rounded bg-primary text-white shadow">
                {{ str(__('shell.brand.name'))->substr(0, 1) }}
            </span>
            <span class="brand-text fw-light">{{ __('shell.brand.name') }}</span>
        </a>
    </div>

    <div class="sidebar-wrapper">
        <nav class="mt-2">
            <ul
                class="nav sidebar-menu flex-column"
                data-lte-toggle="treeview"
                role="list"
                aria-label="{{ __('foundation.areas.admin') }}"
                data-accordion="false"
            >
                @foreach ($sections as $section)
                    <li class="nav-header">{{ __($section->label) }}</li>

                    @foreach ($section->items as $item)
                        <x-admin::layout.sidebar.item :$item />
                    @endforeach
                @endforeach
            </ul>
        </nav>
    </div>
</aside>

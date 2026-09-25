{{--
    The .app-header region.

    The search field, messages, and notifications are wired markup with no data
    behind them yet: each reads from a component that currently returns nothing,
    so the module that owns it later only has to fill in the source.

    Each list carries its own role and label. AdminLTE's accessibility script
    gives any list without them role="navigation", which stops it being a
    list, and an English "Navigation 1" label on every page.
--}}
<nav class="app-header navbar navbar-expand bg-body">
    <div class="container-fluid">
        <ul class="navbar-nav" role="list" aria-label="{{ __('shell.navigation.toolbar') }}">
            <li class="nav-item">
                <a
                    class="nav-link"
                    data-lte-toggle="sidebar"
                    href="#"
                    role="button"
                    aria-label="{{ __('shell.actions.toggle_sidebar') }}"
                >
                    <i class="bi bi-list" aria-hidden="true"></i>
                </a>
            </li>
        </ul>

        <x-admin::layout.global-search />

        <ul class="navbar-nav ms-auto" role="list" aria-label="{{ __('shell.navigation.toolbar') }}">
            <x-admin::layout.messages-menu />
            <x-admin::layout.notifications-menu />

            <x-admin::layout.locale-toggle />

            <x-admin::layout.color-mode-toggle />

            {{--
                AdminLTE's FullScreen plugin swaps these two icons by toggling
                `d-none`, so both must be present and the minimize one must
                start hidden.
            --}}
            <li class="nav-item">
                <a
                    class="nav-link"
                    href="#"
                    data-lte-toggle="fullscreen"
                    role="button"
                    aria-label="{{ __('shell.actions.toggle_fullscreen') }}"
                >
                    <i data-lte-icon="maximize" class="bi bi-arrows-fullscreen" aria-hidden="true"></i>
                    <i data-lte-icon="minimize" class="bi bi-fullscreen-exit d-none" aria-hidden="true"></i>
                </a>
            </li>

            <x-admin::layout.user-menu />
        </ul>
    </div>
</nav>

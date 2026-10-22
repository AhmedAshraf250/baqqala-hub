{{--
    The header's way into the quick search: a field-shaped button on a wide
    screen, an icon on a narrow one. Either — or Ctrl K from anywhere — opens
    `admin::layout.search-palette`, through
    resources/js/admin/modules/search-palette.js.
--}}
<div class="d-none d-md-flex flex-grow-1 mx-3">
    <button type="button" class="global-search global-search-trigger form-control d-flex align-items-center gap-2" data-search-palette-open aria-keyshortcuts="Control+K">
        <i class="bi bi-search text-body-secondary" aria-hidden="true"></i>
        <span class="flex-grow-1 text-start text-body-secondary">{{ __('shell.search.placeholder') }}</span>
        <kbd class="kbd-hint small" aria-hidden="true">Ctrl K</kbd>
    </button>
</div>

<ul class="navbar-nav d-md-none" role="list" aria-label="{{ __('shell.actions.search') }}">
    <li class="nav-item">
        <button type="button" class="nav-link btn btn-link" data-search-palette-open aria-label="{{ __('shell.actions.search') }}">
            <i class="bi bi-search" aria-hidden="true"></i>
        </button>
    </li>
</ul>

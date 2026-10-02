{{--
    The header search box.

    This is the entry point for the fast customer/product lookup: it posts
    nothing yet and has no results dropdown. The markup, keyboard shortcut hint,
    and ARIA wiring are in place; how modules offer results to it is not built
    yet.
--}}
<form class="d-none d-md-flex flex-grow-1 mx-3" role="search" action="#" method="GET" autocomplete="off">
    <div class="input-group global-search">
        <span class="input-group-text bg-body border-end-0">
            <i class="bi bi-search" aria-hidden="true"></i>
        </span>

        <input
            type="search"
            name="q"
            class="form-control border-start-0"
            placeholder="{{ __('shell.search.placeholder') }}"
            aria-label="{{ __('shell.actions.search') }}"
            minlength="{{ config('admin.search.min_query_length') }}"
            disabled
        >
    </div>
</form>

{{--
    The .app-content-header title strip: page title, breadcrumb, page actions.

    `$heading` comes from App\Admin\View\Components\Layout\ContentHeader:
    the title given, or the one the navigation tree gives this screen.
--}}

@if (filled($heading) || $breadcrumbs || filled($slot))
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center g-2">
                <div class="col-sm-6">
                    @if (filled($heading))
                        <h3 class="mb-0">{{ $heading }}</h3>
                    @endif
                </div>

                <div class="col-sm-6 d-flex justify-content-sm-end align-items-center gap-2">
                    {{ $slot }}

                    @if ($breadcrumbs)
                        <x-admin::layout.breadcrumbs />
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif

{{--
    The settings screen.

    Follows AdminLTE's pages/settings.html: a pill rail beside a tab-content
    pane, switching client-side with no reload. Each pane holds a Livewire
    component, so its form still posts and validates on its own.

    No breadcrumb: the rail already says where you are, and a trail ending in
    the tab you are looking at is noise.

    `$sections` and `$active` come from App\Admin\Http\Controllers\SettingsController.
--}}

<x-admin::layout.app :breadcrumbs="false">
    <div class="row g-3">
        <div class="col-12 col-lg-3">
            <div class="list-group list-group-flush nav nav-pills flex-column" role="tablist" aria-label="{{ __('shell.navigation.settings') }}">
                @foreach ($sections as $section)
                    <a
                        href="#{{ $section->paneId() }}"
                        @class([
                            'list-group-item list-group-item-action d-flex align-items-center gap-2',
                            'active' => $section->key === $active->key,
                            'text-'.$section->variant => $section->variant !== 'body',
                        ])
                        data-bs-toggle="pill"
                        role="tab"
                        aria-controls="{{ $section->paneId() }}"
                        aria-selected="{{ $section->key === $active->key ? 'true' : 'false' }}"
                    >
                        <i class="bi bi-{{ $section->icon }}" aria-hidden="true"></i>
                        {{ $section->title() }}
                    </a>
                @endforeach
            </div>
        </div>

        <div class="col-12 col-lg-9">
            <div class="tab-content">
                @foreach ($sections as $section)
                    <div
                        @class(['tab-pane fade', 'show active' => $section->key === $active->key])
                        id="{{ $section->paneId() }}"
                        role="tabpanel"
                    >
                        @livewire($section->component)
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-admin::layout.app>

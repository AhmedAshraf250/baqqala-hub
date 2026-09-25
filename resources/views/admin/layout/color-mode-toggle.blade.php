{{-- AdminLTE's documented colour-mode dropdown, wired by data-bs-theme-value. --}}
<li class="nav-item dropdown">
    <button
        class="btn btn-link nav-link py-2 px-0 px-lg-2 dropdown-toggle d-flex align-items-center"
        id="theme-toggle"
        type="button"
        data-bs-toggle="dropdown"
        data-bs-display="static"
        aria-expanded="false"
        aria-label="{{ __('shell.actions.toggle_theme') }}"
    >
        <span class="theme-icon-active" aria-hidden="true">
            <i class="bi bi-circle-half my-1"></i>
        </span>
    </button>

    <ul class="dropdown-menu dropdown-menu-end theme-menu" aria-labelledby="theme-toggle">
        @foreach (['light' => 'sun-fill', 'dark' => 'moon-fill', 'auto' => 'circle-half'] as $value => $icon)
            <li>
                <button
                    type="button"
                    class="dropdown-item d-flex align-items-center"
                    data-bs-theme-value="{{ $value }}"
                    aria-pressed="false"
                >
                    <i class="bi bi-{{ $icon }} me-2" aria-hidden="true"></i>
                    {{ __('shell.actions.theme_'.$value) }}
                    <i class="bi bi-check-lg ms-auto d-none" aria-hidden="true"></i>
                </button>
            </li>
        @endforeach
    </ul>
</li>

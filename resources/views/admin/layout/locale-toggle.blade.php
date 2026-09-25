{{--
    The language switcher.

    A POST because it changes stored state; `back()` returns the visitor to the
    page they were reading, now in the other language.
--}}
@php
    $locales = config('foundation.locales.supported');
    $current = app()->getLocale();
@endphp

@if (count($locales) > 1)
    <li class="nav-item dropdown">
        <a
            class="nav-link d-flex align-items-center gap-1"
            href="#"
            data-bs-toggle="dropdown"
            aria-label="{{ __('shell.settings.language') }}"
        >
            <i class="bi bi-translate" aria-hidden="true"></i>
            <span class="d-none d-lg-inline small text-uppercase" dir="ltr">{{ $current }}</span>
        </a>

        <ul class="dropdown-menu dropdown-menu-end">
            @foreach ($locales as $code => $locale)
                <li>
                    <form method="POST" action="{{ route('admin.locale.update') }}">
                        @csrf
                        <input type="hidden" name="locale" value="{{ $code }}">

                        <button type="submit" @class(['dropdown-item d-flex align-items-center gap-2', 'active' => $current === $code])>
                            <i @class(['bi', 'bi-check-lg' => $current === $code, 'bi-dash' => $current !== $code, 'opacity-0' => $current !== $code]) aria-hidden="true"></i>
                            {{ $locale['native_name'] }}
                        </button>
                    </form>
                </li>
            @endforeach
        </ul>
    </li>
@endif

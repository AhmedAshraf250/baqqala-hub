{{--
    The frontend's language switcher: one button for each language the page is
    not already in, each named in its own language.

    A POST because it changes stored state; `back()` returns the reader to the
    page they were reading, now in the other language.
--}}
@php
    $others = collect(config('foundation.locales.supported'))->except(app()->getLocale());
@endphp

@if ($others->isNotEmpty())
    <div {{ $attributes->class('flex flex-wrap items-center gap-1') }} data-test="locale-switcher">
        @foreach ($others as $code => $locale)
            <form method="POST" action="{{ route('frontend.locale.update') }}">
                @csrf
                <input type="hidden" name="locale" value="{{ $code }}">

                <flux:button type="submit" size="sm" variant="ghost" icon="language" lang="{{ $code }}">
                    {{ $locale['native_name'] }}
                </flux:button>
            </form>
        @endforeach
    </div>
@endif

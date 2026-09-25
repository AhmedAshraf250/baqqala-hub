{{--
    The customer-facing shell.

    This area runs on Tailwind + Flux and shares nothing with the admin
    stylesheet or bundle; the two never load on the same page.
--}}
@props(['title' => null])

<x-frontend::layout.shell :title="$title">
    <flux:main>
        {{ $slot }}
    </flux:main>
</x-frontend::layout.shell>

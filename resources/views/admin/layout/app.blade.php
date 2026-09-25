{{--
    The AdminLTE application shell.

    Region classes come straight from the AdminLTE layout blueprint:
    .app-wrapper is the CSS grid root, and .app-header / .app-sidebar /
    .app-main / .app-footer are its grid areas. Behaviour is driven by the
    body-level modifiers, never by custom CSS.
--}}
@props([
    'title' => null,
    // Screens with their own internal navigation — Settings and its tabs —
    // pass false: a trail that ends where the page already says you are is
    // noise.
    'breadcrumbs' => true,
])

@php
    $direction = text_direction();
@endphp

<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ $direction }}"
    class="admin-area"
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="color-scheme" content="light dark">

        <x-admin::layout.page-title :title="$title" />

        <x-admin::layout.color-mode-script />

        <x-admin::layout.assets />
        @stack('head')
    </head>

    <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
        <div class="app-wrapper">
            <x-admin::layout.header />

            <x-admin::layout.sidebar />

            <main class="app-main">
                <x-admin::layout.content-header :title="$title" :breadcrumbs="$breadcrumbs">
                    {{ $actions ?? '' }}
                </x-admin::layout.content-header>

                <div class="app-content">
                    <div class="container-fluid">
                        <x-admin::ui.flash-messages />

                        {{ $slot }}
                    </div>
                </div>
            </main>

            <x-admin::layout.footer />
        </div>

        @stack('scripts')
    </body>
</html>

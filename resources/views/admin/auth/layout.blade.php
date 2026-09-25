{{--
    The shell for the admin area's authentication screens.

    AdminLTE's `login-page` / `login-box` pattern, with no sidebar and no
    header — a signed-out visitor has no navigation to show.
--}}
@props([
    'title' => null,
    'heading' => null,
    'message' => null,
])

@php
    $pageTitle = filled($title) ? $title.' — '.__('shell.brand.name') : __('shell.brand.name');
@endphp

<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ text_direction() }}"
    class="admin-area"
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="color-scheme" content="light dark">
        <meta name="robots" content="noindex, nofollow">

        <title>{{ $pageTitle }}</title>

        <x-admin::layout.color-mode-script />

        <x-admin::layout.assets />
    </head>

    <body class="login-page bg-body-secondary">
        <div class="login-box">
            <div class="card card-outline card-primary">
                <div class="card-header text-center">
                    <h1 class="mb-0 h3">
                        <span class="fw-bold">{{ __('shell.brand.name') }}</span>
                        <small class="d-block fs-6 fw-normal text-body-secondary mt-1">
                            {{ __('foundation.areas.admin') }}
                        </small>
                    </h1>
                </div>

                <div class="card-body login-card-body">
                    @if (filled($heading))
                        <h2 class="h5 text-center mb-1">{{ $heading }}</h2>
                    @endif

                    @if (filled($message))
                        <p class="login-box-msg">{{ $message }}</p>
                    @endif

                    <x-admin::ui.flash-messages />

                    @if ($errors->any())
                        <x-admin::ui.alert variant="danger" icon="exclamation-octagon">
                            <ul class="mb-0 ps-3">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </x-admin::ui.alert>
                    @endif

                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>

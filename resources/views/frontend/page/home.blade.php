<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ text_direction() }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ __('shell.page.home.title') }} - {{ __('shell.brand.name') }}</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    @fonts
    @vite(['resources/css/frontend/app.css', 'resources/js/frontend/app.js'])
    @fluxAppearance
</head>

<body class="min-h-screen bg-white text-zinc-950 antialiased dark:bg-zinc-950 dark:text-white">
    <main class="mx-auto flex min-h-screen w-full max-w-5xl flex-col justify-center px-6 py-12">
        <div class="max-w-2xl space-y-8">
            <x-frontend::ui.app-logo href="{{ route('frontend.home') }}" />

            <div class="space-y-3">
                <h1 class="text-4xl font-semibold tracking-normal">
                    {{ __('shell.page.home.heading') }}
                </h1>
                <p class="max-w-xl text-base leading-7 text-zinc-600 dark:text-zinc-300">
                    {{ __('shell.page.home.intro') }}
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                @if ($signedIn)
                    <flux:button :href="route('frontend.account.dashboard')" variant="primary">
                        {{ __('shell.navigation.dashboard') }}
                    </flux:button>
                @else
                    <flux:button :href="route('login')" wire:navigate variant="primary">
                        {{ __('shell.actions.login') }}
                    </flux:button>
                @endif
            </div>
        </div>
    </main>

    @fluxScripts
</body>

</html>

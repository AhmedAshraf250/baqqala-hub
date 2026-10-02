<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ text_direction() }}" class="dark">
    <head>
        @include('frontend::layout.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-frontend::ui.app-logo :sidebar="true" href="{{ route('frontend.account.dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" :tooltip="__('shell.actions.toggle_sidebar')" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('shell.page.account.title')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('frontend.account.dashboard')" wire:navigate>
                        {{ __('shell.navigation.dashboard') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <x-frontend::ui.locale-switcher class="hidden lg:flex" />

            <x-frontend::ui.user-menu placement="sidebar" class="hidden lg:block" />
        </flux:sidebar>

        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" :aria-label="__('shell.actions.toggle_sidebar')" />

            <flux:spacer />

            <x-frontend::ui.locale-switcher />

            <x-frontend::ui.user-menu placement="header" />
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>

<?php

use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Attributes\Title;

new #[Layout('frontend::layout.app')] #[Title('Appearance settings')] class extends Component {
    //
}; ?>

<section class="w-full">
    @include('frontend::page.account.settings.heading')

    <flux:heading class="sr-only">{{ __('shell.account.appearance_title') }}</flux:heading>

    <x-frontend::page.account.settings.layout :heading="__('shell.settings.appearance')" :subheading="__('shell.account.appearance_description')">
        <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
            <flux:radio value="light" icon="sun">{{ __('shell.actions.theme_light') }}</flux:radio>
            <flux:radio value="dark" icon="moon">{{ __('shell.actions.theme_dark') }}</flux:radio>
            <flux:radio value="system" icon="computer-desktop">{{ __('shell.actions.theme_auto') }}</flux:radio>
        </flux:radio.group>
    </x-frontend::page.account.settings.layout>
</section>

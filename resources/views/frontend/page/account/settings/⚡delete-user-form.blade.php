<?php

use Livewire\Component;

new class extends Component {}; ?>

<section class="mt-10 space-y-6">
    <div class="relative mb-5">
        <flux:heading>{{ __('shell.account.delete') }}</flux:heading>
        <flux:subheading>{{ __('shell.account.delete_description') }}</flux:subheading>
    </div>

    <flux:modal.trigger name="confirm-user-deletion">
        <flux:button variant="danger" data-test="delete-user-button">
            {{ __('shell.account.delete') }}
        </flux:button>
    </flux:modal.trigger>

    <livewire:frontend::page.account.settings.delete-user-modal />
</section>

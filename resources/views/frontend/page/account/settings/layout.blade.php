<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <flux:navlist aria-label="{{ __('shell.navigation.settings') }}">
            <flux:navlist.item :href="route('frontend.account.settings.profile')" wire:navigate>{{ __('shell.settings.profile') }}</flux:navlist.item>
            <flux:navlist.item :href="route('frontend.account.settings.security')" wire:navigate>{{ __('shell.settings.security') }}</flux:navlist.item>
            <flux:navlist.item :href="route('frontend.account.settings.appearance')" wire:navigate>{{ __('shell.settings.appearance') }}</flux:navlist.item>
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <flux:heading>{{ $heading ?? '' }}</flux:heading>
        <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>

        <div class="mt-5 w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>
</div>

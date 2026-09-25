<x-frontend::layout.auth :title="__('shell.auth.forgot_password')">
    <div class="flex flex-col gap-6">
        <x-frontend::ui.auth-header :title="__('shell.auth.forgot_password')" :description="__('shell.auth.forgot_password_message')" />

        <!-- Session Status -->
        <x-frontend::ui.auth-session-status class="text-center" />

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('shell.auth.email')"
                type="email"
                required
                autofocus
                placeholder="email@example.com"
            />

            <flux:button variant="primary" type="submit" class="w-full" data-test="email-password-reset-link-button">
                {{ __('shell.auth.send_reset_link') }}
            </flux:button>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('shell.auth.or_return_to') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('shell.auth.log_in_link') }}</flux:link>
        </div>
    </div>
</x-frontend::layout.auth>

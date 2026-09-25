<x-frontend::layout.auth :title="__('shell.actions.login')">
    <div class="flex flex-col gap-6">
        <x-frontend::ui.auth-header :title="__('shell.auth.log_in_title')" :description="__('shell.auth.log_in_message')" />

        <!-- Session Status -->
        <x-frontend::ui.auth-session-status class="text-center" />

        <x-frontend::ui.passkey-verify />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('shell.auth.email')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('shell.auth.password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('shell.auth.password')"
                    viewable
                />

                @if ($canResetPassword)
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        {{ __('shell.auth.forgot_your_password') }}
                    </flux:link>
                @endif
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('shell.auth.remember_me')" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    {{ __('shell.actions.login') }}
                </flux:button>
            </div>
        </form>

        {{--
            No sign-up link: accounts are issued by the shop, not claimed. Someone
            without one asks the shop, who grants access to the customer record
            they already have.
        --}}
        <div class="text-sm text-center text-zinc-600 dark:text-zinc-400">
            {{ __('shell.auth.customer_account_help') }}
        </div>
    </div>
</x-frontend::layout.auth>

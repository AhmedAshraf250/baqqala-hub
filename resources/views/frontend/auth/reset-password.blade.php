<x-frontend::layout.auth :title="__('shell.auth.reset_password')">
    <div class="flex flex-col gap-6">
        <x-frontend::ui.auth-header :title="__('shell.auth.reset_password')" :description="__('shell.auth.reset_password_message')" />

        <!-- Session Status -->
        <x-frontend::ui.auth-session-status class="text-center" />

        <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-6">
            @csrf
            <!-- Token -->
            <input type="hidden" name="token" value="{{ $token }}">

            <!-- Email Address -->
            <flux:input
                name="email"
                value="{{ $email }}"
                :label="__('shell.auth.email')"
                type="email"
                required
                autocomplete="email"
            />

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('shell.auth.password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('shell.auth.password')"
                passwordrules="{{ $passwordRules }}"
                viewable
            />

            <!-- Confirm Password -->
            <flux:input
                name="password_confirmation"
                :label="__('shell.auth.confirm_password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('shell.auth.confirm_password')"
                passwordrules="{{ $passwordRules }}"
                viewable
            />

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full" data-test="reset-password-button">
                    {{ __('shell.auth.reset_password') }}
                </flux:button>
            </div>
        </form>
    </div>
</x-frontend::layout.auth>

<x-frontend::layout.auth :title="__('shell.auth.confirm_password')">
    <div class="flex flex-col gap-6">
        <x-frontend::ui.auth-header
            :title="__('shell.auth.confirm_password')"
            :description="__('shell.auth.confirm_password_message')"
        />

        <x-frontend::ui.auth-session-status class="text-center" />

        <x-frontend::ui.passkey-verify
            options-route="passkey.confirm-options"
            submit-route="passkey.confirm"
            :label="__('shell.passkeys.confirm_with')"
            :loading-label="__('shell.passkeys.confirming')"
            :separator="__('shell.passkeys.or_confirm_with_password')"
        />

        <form method="POST" action="{{ route('password.confirm.store') }}" class="flex flex-col gap-6">
            @csrf

            <flux:input
                name="password"
                :label="__('shell.auth.password')"
                type="password"
                required
                autocomplete="current-password"
                :placeholder="__('shell.auth.password')"
                viewable
            />

            <flux:button variant="primary" type="submit" class="w-full" data-test="confirm-password-button">
                {{ __('shell.auth.confirm') }}
            </flux:button>
        </form>
    </div>
</x-frontend::layout.auth>

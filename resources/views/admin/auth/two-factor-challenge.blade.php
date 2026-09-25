<x-admin::auth.layout
    :title="__('shell.auth.two_factor')"
    :message="__('shell.auth.two_factor_message')"
>
    <form method="POST" action="{{ route('admin.two-factor.login.store') }}">
        @csrf

        <x-admin::form.floating-input
            name="code"
            :label="__('shell.auth.two_factor_code')"
            icon="shield-lock"
            inputmode="numeric"
            autocomplete="one-time-code"
            autofocus
        />

        <details class="mb-3">
            <summary class="small text-body-secondary">{{ __('shell.auth.use_recovery_code') }}</summary>

            <div class="mt-2">
                <x-admin::form.floating-input
                    name="recovery_code"
                    :label="__('shell.auth.recovery_code')"
                    icon="key"
                    autocomplete="one-time-code"
                />
            </div>
        </details>

        <div class="d-grid">
            <button type="submit" class="btn btn-primary">{{ __('shell.auth.verify') }}</button>
        </div>
    </form>
</x-admin::auth.layout>

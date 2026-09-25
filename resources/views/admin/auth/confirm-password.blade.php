<x-admin::auth.layout
    :title="__('shell.auth.confirm_password')"
    :message="__('shell.auth.confirm_password_message')"
>
    <form method="POST" action="{{ route('admin.password.confirm.store') }}">
        @csrf

        <x-admin::form.floating-input
            name="password"
            type="password"
            :label="__('shell.auth.password')"
            icon="lock-fill"
            autocomplete="current-password"
            autofocus
            required
        />

        <div class="d-grid">
            <button type="submit" class="btn btn-primary">{{ __('shell.auth.confirm') }}</button>
        </div>
    </form>

    <p class="mt-3 mb-0 text-center">
        <a href="{{ route('admin.dashboard') }}">{{ __('shell.actions.cancel') }}</a>
    </p>
</x-admin::auth.layout>

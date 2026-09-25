<x-admin::auth.layout
    :title="__('shell.auth.sign_in')"
    :message="__('shell.auth.admin_sign_in_message')"
>
    <form method="POST" action="{{ route('admin.login.store') }}">
        @csrf

        <x-admin::form.floating-input
            name="email"
            type="email"
            :label="__('shell.auth.email')"
            icon="envelope"
            autocomplete="username"
            autofocus
            required
        />

        <x-admin::form.floating-input
            name="password"
            type="password"
            :label="__('shell.auth.password')"
            icon="lock-fill"
            autocomplete="current-password"
            required
        />

        <div class="row align-items-center">
            <div class="col-7">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1" @checked(old('remember'))>
                    <label class="form-check-label" for="remember">{{ __('shell.auth.remember_me') }}</label>
                </div>
            </div>

            <div class="col-5">
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        {{ __('shell.auth.sign_in') }}
                    </button>
                </div>
            </div>
        </div>
    </form>

    <p class="mt-3 mb-0 text-center text-body-secondary small">
        {{ __('shell.auth.admin_account_help') }}
    </p>
</x-admin::auth.layout>

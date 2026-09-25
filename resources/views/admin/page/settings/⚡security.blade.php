<?php

use App\Foundation\Area\Area;
use App\Foundation\Identity\Validation\PasswordRules;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

new class extends Component {
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function updatePassword(): void
    {
        $this->validate([
            'current_password' => PasswordRules::forCurrentPassword(Area::Admin->guard()),
            'password' => PasswordRules::forNewPassword(),
        ]);

        (Area::Admin->user() ?? abort(403))->update(['password' => Hash::make($this->password)]);

        $this->reset('current_password', 'password', 'password_confirmation');

        session()->flash('success', __('shell.settings.password_saved'));
    }

    public function twoFactorEnabled(): bool
    {
        return Area::Admin->user()?->two_factor_confirmed_at !== null;
    }
}; ?>

<x-admin::ui.card :title="__('shell.settings.security')" icon="shield-lock">
    <p class="text-body-secondary">{{ __('shell.settings.security_description') }}</p>

    <form wire:submit="updatePassword" class="row g-3">
        <div class="col-12">
            <label for="current_password" class="form-label">{{ __('shell.settings.current_password') }}</label>
            <input
                id="current_password"
                type="password"
                wire:model="current_password"
                autocomplete="current-password"
                @class(['form-control', 'is-invalid' => $errors->has('current_password')])
                required
            >
            @error('current_password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>

        <div class="col-12 col-md-6">
            <label for="password" class="form-label">{{ __('shell.auth.new_password') }}</label>
            <input
                id="password"
                type="password"
                wire:model="password"
                autocomplete="new-password"
                @class(['form-control', 'is-invalid' => $errors->has('password')])
                required
            >
            @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>

        <div class="col-12 col-md-6">
            <label for="password_confirmation" class="form-label">{{ __('shell.auth.confirm_new_password') }}</label>
            <input
                id="password_confirmation"
                type="password"
                wire:model="password_confirmation"
                autocomplete="new-password"
                class="form-control"
                required
            >
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                <i class="bi bi-check-lg me-1" aria-hidden="true"></i>
                {{ __('shell.actions.save') }}
            </button>
        </div>
    </form>

    <hr class="my-4">

    <h3 class="h6">{{ __('shell.settings.two_factor') }}</h3>
    <p class="text-body-secondary">{{ __('shell.settings.two_factor_description') }}</p>

    <x-admin::ui.badge
        :variant="$this->twoFactorEnabled() ? 'success' : 'secondary'"
        :icon="$this->twoFactorEnabled() ? 'shield-check' : 'shield-slash'"
    >
        {{ $this->twoFactorEnabled()
            ? __('shell.settings.two_factor_on')
            : __('shell.settings.two_factor_off') }}
    </x-admin::ui.badge>
</x-admin::ui.card>

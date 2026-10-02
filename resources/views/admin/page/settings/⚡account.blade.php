<?php

use App\Foundation\Identity\Validation\ProfileRules;
use App\Foundation\Localization\LocalePreference;
use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The account tab: the details the panel signs you in with.
 *
 * Distinct from the profile page, which is the person's own page. This is
 * where the account is configured; that is where it is displayed.
 */
new class extends Component {
    public string $name = '';

    public string $email = '';

    public string $locale = '';

    public function mount(): void
    {
        $this->name = $this->administrator()->name;
        $this->email = $this->administrator()->email;
        $this->locale = app()->getLocale();
    }

    /**
     * Asked of the admin guard by name: the default guard is the customer's.
     */
    private function administrator(): User
    {
        return Area::Admin->user() ?? abort(403);
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        $administrator = $this->administrator();

        return $administrator instanceof MustVerifyEmail && ! $administrator->hasVerifiedEmail();
    }

    /**
     * @return array<string, array<string, string>>
     */
    #[Computed]
    public function locales(): array
    {
        return config('foundation.locales.supported');
    }

    public function save(): void
    {
        $user = $this->administrator();

        $validated = $this->validate([
            ...ProfileRules::forProfile($user->id),
            'locale' => ['required', 'string', Illuminate\Validation\Rule::in(LocalePreference::supported())],
        ]);

        $user->fill(['name' => $validated['name'], 'email' => $validated['email']]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        LocalePreference::remember(Area::Admin, $validated['locale']);

        session()->flash('success', __('shell.settings.account_saved'));

        // The whole shell — direction, stylesheet, every string — depends on
        // the locale, so a change here has to repaint more than this pane.
        $this->redirectRoute('admin.settings', navigate: false);
    }

    public function resendVerificationEmail(): void
    {
        $this->administrator()->sendEmailVerificationNotification();

        session()->flash('status', __('shell.settings.verification_sent'));
    }
}; ?>

<x-admin::ui.card :title="__('shell.settings.account')" icon="person">
    <p class="text-body-secondary">{{ __('shell.settings.account_description') }}</p>

    <form wire:submit="save" class="row g-3">
        <div class="col-12 col-md-6">
            <label for="account-name" class="form-label">{{ __('shell.settings.name') }}</label>
            <input
                id="account-name"
                type="text"
                wire:model="name"
                autocomplete="name"
                @class(['form-control', 'is-invalid' => $errors->has('name')])
                required
            >
            @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>

        <div class="col-12 col-md-6">
            <label for="account-email" class="form-label">{{ __('shell.settings.email') }}</label>
            <input
                id="account-email"
                type="email"
                dir="ltr"
                wire:model="email"
                autocomplete="email"
                @class(['form-control', 'is-invalid' => $errors->has('email')])
                required
            >
            @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

            @if ($this->hasUnverifiedEmail)
                <div class="form-text d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle text-warning" aria-hidden="true"></i>
                    {{ __('shell.settings.email_unverified') }}
                    <button type="button" class="btn btn-link btn-sm p-0" wire:click="resendVerificationEmail">
                        {{ __('shell.auth.resend_verification') }}
                    </button>
                </div>
            @endif
        </div>

        <div class="col-12 col-md-6">
            <label for="account-locale" class="form-label">{{ __('shell.settings.language') }}</label>
            <select id="account-locale" wire:model="locale" class="form-select">
                @foreach ($this->locales as $code => $locale)
                    <option value="{{ $code }}">{{ $locale['native_name'] }}</option>
                @endforeach
            </select>
            <div class="form-text">{{ __('shell.settings.language_description') }}</div>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                <i class="bi bi-check-lg me-1" aria-hidden="true"></i>
                {{ __('shell.actions.save') }}
            </button>
        </div>
    </form>
</x-admin::ui.card>

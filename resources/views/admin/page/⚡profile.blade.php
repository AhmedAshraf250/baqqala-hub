<?php

use App\Admin\Authorization\RoleDefinitions;
use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\AdminUser;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The signed-in administrator's own page.
 *
 * Distinct from Settings: this displays the person, Settings configures the
 * account. AdminLTE ships both, for the same reason.
 */
new #[Layout('admin::layout.app')] class extends Component {
    public function rendering(View $view): void
    {
        $view->layoutData(['title' => __('shell.navigation.profile')]);
    }

    /**
     * Asked of the admin guard by name: the default guard is the customer's.
     */
    #[Computed]
    public function administrator(): AdminUser
    {
        $user = Area::Admin->user();

        return $user instanceof AdminUser ? $user : abort(403);
    }

    #[Computed]
    public function position(): string
    {
        return app(RoleDefinitions::class)->labelFor($this->administrator);
    }
}; ?>

<div class="row g-3">
    <div class="col-12 col-lg-4">
        <x-admin::ui.card>
            <div class="text-center">
                <x-admin::ui.avatar :name="$this->administrator->name" size="xl" class="mb-3" />

                <h2 class="h5 mb-1">{{ $this->administrator->name }}</h2>
                <p class="text-body-secondary mb-3">{{ $this->position }}</p>

                <a href="{{ route('admin.settings') }}" class="btn btn-primary w-100">
                    <i class="bi bi-pencil me-1" aria-hidden="true"></i>
                    {{ __('shell.profile.edit_account') }}
                </a>
            </div>
        </x-admin::ui.card>
    </div>

    <div class="col-12 col-lg-8">
        <x-admin::ui.card :title="__('shell.profile.about')" icon="info-circle">
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <span class="text-body-secondary">
                        <i class="bi bi-envelope me-1" aria-hidden="true"></i>
                        {{ __('shell.settings.email') }}
                    </span>
                    <span class="fw-semibold text-truncate ms-2" dir="ltr">{{ $this->administrator->email }}</span>
                </li>

                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <span class="text-body-secondary">
                        <i class="bi bi-patch-check me-1" aria-hidden="true"></i>
                        {{ __('shell.profile.email_status') }}
                    </span>
                    <x-admin::ui.badge :variant="$this->administrator->email_verified_at ? 'success' : 'warning'">
                        {{ $this->administrator->email_verified_at
                            ? __('shell.profile.verified')
                            : __('shell.profile.unverified') }}
                    </x-admin::ui.badge>
                </li>

                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <span class="text-body-secondary">
                        <i class="bi bi-shield-lock me-1" aria-hidden="true"></i>
                        {{ __('shell.settings.two_factor') }}
                    </span>
                    <x-admin::ui.badge :variant="$this->administrator->two_factor_confirmed_at ? 'success' : 'secondary'">
                        {{ $this->administrator->two_factor_confirmed_at
                            ? __('shell.settings.two_factor_on')
                            : __('shell.settings.two_factor_off') }}
                    </x-admin::ui.badge>
                </li>

                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <span class="text-body-secondary">
                        <i class="bi bi-calendar-check me-1" aria-hidden="true"></i>
                        {{ __('shell.settings.member_since') }}
                    </span>
                    <span class="fw-semibold">{{ $this->administrator->created_at?->isoFormat('LL') }}</span>
                </li>
            </ul>
        </x-admin::ui.card>
    </div>
</div>

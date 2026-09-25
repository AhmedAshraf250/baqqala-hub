<?php

namespace App\Admin\View\Components\Layout;

use App\Admin\Authorization\RoleDefinitions;
use App\Admin\Settings\AdminSettings;
use App\Admin\Settings\SettingsSection;
use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\AdminUser;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-admin::layout.user-menu />`: the signed-in administrator, the role they
 * hold, since when, and their own settings, as AdminLTE's user menu shows a
 * person.
 */
final class UserMenu extends Component
{
    public readonly ?AdminUser $user;

    public readonly ?string $position;

    public readonly ?string $memberSince;

    /**
     * @var list<SettingsSection>
     */
    public readonly array $personalSettings;

    public function __construct(RoleDefinitions $roles, AdminSettings $settings)
    {
        $user = Area::Admin->user();

        $this->user = $user instanceof AdminUser ? $user : null;
        $this->position = $this->user === null ? null : $roles->labelFor($this->user);
        $this->memberSince = $this->user?->created_at?->isoFormat('MMMM YYYY');
        $this->personalSettings = $settings->personal();
    }

    public function shouldRender(): bool
    {
        return $this->user !== null;
    }

    public function render(): View
    {
        return view('admin::layout.user-menu');
    }
}

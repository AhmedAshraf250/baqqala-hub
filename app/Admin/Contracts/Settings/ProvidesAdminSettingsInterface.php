<?php

namespace App\Admin\Contracts\Settings;

use App\Admin\Settings\SettingsSection;

/**
 * A module that adds a tab to the admin settings screen.
 *
 * For configuration that belongs to the module rather than to the signed-in
 * administrator — tax rules, printing, an integration's credentials.
 */
interface ProvidesAdminSettingsInterface
{
    /**
     * @return list<SettingsSection>
     */
    public function adminSettings(): array;
}

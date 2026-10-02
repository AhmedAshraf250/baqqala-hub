<?php

namespace App\Admin\Contracts\Settings;

use App\Admin\Settings\SettingsSection;

/**
 * A module that adds a tab to the admin settings screen.
 *
 * For configuration that belongs to the module rather than to the signed-in
 * administrator — tax rules, printing, an integration's credentials. The tab
 * is shown only to administrators who hold `settings.manage`.
 *
 * Hiding the tab is not authorizing the pane: a Livewire action reaches its
 * component directly. The pane checks `settings.manage` (or a narrower
 * permission of its own) in every action that changes something.
 */
interface ProvidesAdminSettingsInterface
{
    /**
     * @return list<SettingsSection>
     */
    public function adminSettings(): array;
}

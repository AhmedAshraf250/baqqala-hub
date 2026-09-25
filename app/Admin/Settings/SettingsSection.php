<?php

namespace App\Admin\Settings;

/**
 * One tab on the admin settings screen.
 *
 * Settings is a single destination with its own internal navigation, the way
 * the AdminLTE settings page works: the panes are tabs, not separate pages, so
 * switching between them never reloads the shell.
 */
final readonly class SettingsSection
{
    /**
     * The strings are translation keys named by whoever defines the tab, so a
     * module's tab is described in the module's own `module.php` and never in
     * the shell's file.
     *
     * @param  string  $key  Stable identifier; also the tab's DOM id.
     * @param  string  $component  The Livewire component rendered in this pane.
     * @param  string  $title  A translation key.
     * @param  string  $description  A translation key.
     * @param  string  $icon  A Bootstrap Icons name, without the `bi-` prefix.
     * @param  string  $variant  A Bootstrap contextual colour for the rail entry.
     */
    public function __construct(
        public string $key,
        public string $component,
        public string $title,
        public string $description,
        public string $icon = 'gear',
        public string $variant = 'body',
    ) {}

    public function title(): string
    {
        return __($this->title);
    }

    public function description(): string
    {
        return __($this->description);
    }

    /**
     * The DOM id of this section's tab pane.
     */
    public function paneId(): string
    {
        return "settings-{$this->key}";
    }
}

<?php

namespace App\Admin\Settings;

use App\Admin\Authorization\AdminPermission;
use App\Admin\Contracts\Settings\ProvidesAdminSettingsInterface;
use App\Foundation\Area\Area;
use App\Foundation\Modules\ModuleRegistry;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Support\Collection;

/**
 * The tabs of the admin settings screen.
 *
 * The shell's own — the signed-in account, its security, the interface — and
 * then whatever the running modules add through
 * {@see ProvidesAdminSettingsInterface}: tax rules, printing, an integration's
 * credentials. The settings screen reads from here, so adding a tab changes
 * no Blade file.
 */
#[Scoped]
final class AdminSettings
{
    /**
     * Built once per request; the settings screen asks for it several times.
     *
     * @var Collection<int, SettingsSection>|null
     */
    private ?Collection $sections = null;

    public function __construct(private readonly ModuleRegistry $modules) {}

    /**
     * Every tab, in display order.
     *
     * @return Collection<int, SettingsSection>
     */
    public function sections(): Collection
    {
        return $this->sections ??= collect([
            ...$this->personal(),
            ...$this->business(),
        ]);
    }

    /**
     * The tabs that configure the business rather than the person — tax rules,
     * printing, an integration's credentials — which a module contributes the
     * way it contributes navigation. Only someone who may configure the panel
     * sees them; everyone sees their own.
     *
     * @return list<SettingsSection>
     */
    private function business(): array
    {
        if (Area::Admin->user()?->can(AdminPermission::ManageSettings->value) !== true) {
            return [];
        }

        return array_values($this->modules
            ->providing(ProvidesAdminSettingsInterface::class)
            ->flatMap(static fn (ProvidesAdminSettingsInterface $module): array => $module->adminSettings())
            ->all());
    }

    /**
     * The tabs about the signed-in administrator themselves — their account,
     * how they sign in, how the panel looks to them. The user menu links to
     * these; a module's tabs configure the business, not the person.
     *
     * @return list<SettingsSection>
     */
    public function personal(): array
    {
        return [
            new SettingsSection('account', 'admin::page.settings.account', 'shell.settings.account', 'shell.settings.account_description', 'person'),
            new SettingsSection('security', 'admin::page.settings.security', 'shell.settings.security', 'shell.settings.security_description', 'shield-lock'),
            new SettingsSection('appearance', 'admin::page.settings.appearance', 'shell.settings.appearance', 'shell.settings.appearance_description', 'palette'),
        ];
    }

    /**
     * The tab a request asked for, falling back to the first.
     */
    public function resolveActive(?string $requested): SettingsSection
    {
        return $this->sections()->firstWhere('key', $requested)
            ?? $this->sections()->first();
    }
}

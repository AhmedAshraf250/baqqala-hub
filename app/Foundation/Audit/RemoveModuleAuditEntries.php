<?php

namespace App\Foundation\Audit;

use App\Foundation\Contracts\Modules\UninstallStepInterface;
use App\Foundation\Modules\ModuleServiceProvider;
use Illuminate\Database\Eloquent\Builder;

/**
 * Removes a module's lines from the change log when it is uninstalled.
 *
 * The foundation keeps the log for every layer, so it removes what it kept
 * for the one module going: every subject is namespaced by its owner's key,
 * so `{key}.*` is exactly the module's own. Disabling a module removes
 * nothing.
 */
final class RemoveModuleAuditEntries implements UninstallStepInterface
{
    public function describe(ModuleServiceProvider $module): array
    {
        $count = $this->entriesOf($module)->count();

        return $count === 0 ? [] : [sprintf('%d change-log entries', $count)];
    }

    public function run(ModuleServiceProvider $module): void
    {
        $this->entriesOf($module)->delete();
    }

    /**
     * @return Builder<AuditEntry>
     */
    private function entriesOf(ModuleServiceProvider $module): Builder
    {
        return AuditEntry::query()->where('subject', 'like', $module->key().'.%');
    }
}

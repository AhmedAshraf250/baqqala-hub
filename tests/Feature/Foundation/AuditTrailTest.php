<?php

use App\Foundation\Area\Area;
use App\Foundation\Audit\AuditAction;
use App\Foundation\Audit\AuditChange;
use App\Foundation\Audit\AuditEntry;
use App\Foundation\Audit\AuditTrail;
use App\Foundation\Audit\RemoveModuleAuditEntries;
use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Modules\ModuleRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

/*
 * The change log: written by whoever changes something, never changed
 * itself, removed with the module whose lines they are, and read on the
 * shell's own screen.
 */

beforeEach(function () {
    $this->owner = actingAsAdmin();
    $this->owner->update(['name' => 'سارة']);
});

test('a change is recorded with whoever is signed in, and an update that changed nothing is not', function () {
    $audit = app(AuditTrail::class);

    $audit->record('catalog.product', 7, 'زيت كريستال 1 لتر', 'catalog::module.audit.product', AuditAction::Updated, [
        new AuditChange('catalog::module.product.price', '90.00', '95.00'),
    ]);
    $audit->record('catalog.product', 7, 'زيت كريستال 1 لتر', 'catalog::module.audit.product', AuditAction::Updated);

    $entry = AuditEntry::query()->sole();

    expect($entry->user_id)->toBe($this->owner->getKey())
        ->and($entry->subject)->toBe('catalog.product')
        ->and($entry->action)->toBe(AuditAction::Updated)
        ->and($entry->changeList())->toEqual([new AuditChange('catalog::module.product.price', '90.00', '95.00')]);
});

test('only the fields that differ are changes', function () {
    expect(AuditTrail::differences(
        ['name' => 'أ', 'phone' => '010', 'notes' => null],
        ['name' => 'ب', 'phone' => '010', 'notes' => 'جديد'],
        ['name' => 'n', 'phone' => 'p', 'notes' => 'x'],
    ))->toEqual([new AuditChange('n', 'أ', 'ب'), new AuditChange('x', null, 'جديد')]);
});

test('a line of the log is never changed afterwards', function () {
    app(AuditTrail::class)->record('catalog.product', 1, 'شاي', 'catalog::module.audit.product', AuditAction::Created);

    expect(fn () => AuditEntry::query()->sole()->update(['label' => 'قهوة']))->toThrow(LogicException::class);
});

test('uninstalling a module removes its lines and nobody else\'s', function () {
    $audit = app(AuditTrail::class);
    $audit->record('catalog.product', 1, 'شاي', 'catalog::module.audit.product', AuditAction::Created);
    $audit->record('catalogue.thing', 1, 'Lookalike', 'x', AuditAction::Created);
    $audit->record('accounts.account', 1, 'محمد', 'accounts::module.audit.account', AuditAction::Created);

    $catalog = app(ModuleRegistry::class)->find('catalog');
    $step = new RemoveModuleAuditEntries;

    expect($step->describe($catalog))->toBe(['1 change-log entries']);

    $step->run($catalog);

    expect(AuditEntry::query()->orderBy('id')->pluck('subject')->all())->toBe(['catalogue.thing', 'accounts.account']);
});

test('the change log shows who changed what, a day at a time, field by field', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-03 10:00', 'Africa/Cairo'));
    app(AuditTrail::class)->record('catalog.product', 7, 'زيت كريستال 1 لتر', 'catalog::module.audit.product', AuditAction::Updated, [
        new AuditChange('catalog::module.product.price', '90.00', '95.00'),
        new AuditChange('catalog::module.product.unit', 'catalog::module.unit.piece', 'catalog::module.unit.liter', translated: true),
    ]);

    readingIn('ar');

    $this->get(route('admin.audit'))
        ->assertOk()
        ->assertSeeInOrder([
            __('foundation.audit.action.updated', locale: 'ar'),
            __('catalog::module.audit.product', locale: 'ar'),
            'زيت كريستال 1 لتر',
            __('catalog::module.product.price', locale: 'ar'),
            '90.00', '95.00',
            __('catalog::module.unit.piece', locale: 'ar'), __('catalog::module.unit.liter', locale: 'ar'),
            __('shell.audit.by', ['name' => 'سارة'], 'ar'),
        ]);
});

test('the log narrows to one person, one kind, one action, and one stretch of time', function () {
    $audit = app(AuditTrail::class);
    $omar = AdminUser::factory()->create(['name' => 'عمر']);

    $this->travelTo(CarbonImmutable::now()->subDays(40));
    $audit->record('catalog.product', 1, 'قديم', 'catalog::module.audit.product', AuditAction::Created);
    $this->travelBack();

    $audit->record('catalog.product', 2, 'شاي', 'catalog::module.audit.product', AuditAction::Archived);
    $this->actingAs($omar, Area::Admin->guard());
    $audit->record('accounts.account', 3, 'محمد', 'accounts::module.audit.account', AuditAction::Updated, [new AuditChange('accounts::module.account.credit_limit', '0.00', '500.00')]);
    actingAsAdmin($this->owner);

    $labels = fn (array $filters) => Livewire::test('admin::page.audit')->set($filters)->instance()->entries->getCollection()->pluck('label')->all();

    expect($labels([]))->toBe(['محمد', 'شاي'])
        ->and($labels(['period' => 'all']))->toBe(['محمد', 'شاي', 'قديم'])
        ->and($labels(['person' => $omar->getKey()]))->toBe(['محمد'])
        ->and($labels(['subject' => 'catalog.product']))->toBe(['شاي'])
        ->and($labels(['action' => 'archived']))->toBe(['شاي'])
        ->and($labels(['q' => 'مح']))->toBe(['محمد']);
});

test('whoever may not read the change log does not reach it', function () {
    $cashier = AdminUser::factory()->create();
    $cashier->syncRoles('cashier');
    $this->actingAs($cashier, Area::Admin->guard());

    $this->get(route('admin.audit'))->assertForbidden();

    $manager = AdminUser::factory()->create();
    $manager->syncRoles('manager');
    $this->actingAs($manager, Area::Admin->guard());

    $this->get(route('admin.audit'))->assertOk();
});

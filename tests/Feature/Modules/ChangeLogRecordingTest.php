<?php

use App\Foundation\Audit\AuditAction;
use App\Foundation\Audit\AuditChange;
use App\Foundation\Audit\AuditEntry;
use App\Foundation\Money\Money;
use App\Foundation\Settings\SettingStore;
use App\Modules\Accounts\Domain\Actions\SetCreditLimit;
use App\Modules\Accounts\Domain\Services\AccountSettings;
use App\Modules\Catalog\Contracts\Enums\SaleUnit;
use App\Modules\Catalog\Database\Models\Category;
use App\Modules\Catalog\Database\Models\Product;
use App\Modules\Catalog\Domain\Actions\ChangeProductPrice;
use App\Modules\Catalog\Domain\Actions\DeleteProduct;
use App\Modules\Catalog\Domain\Actions\SaveCategory;
use App\Modules\Catalog\Domain\Actions\SaveProduct;
use App\Modules\Catalog\Domain\Actions\SetProductArchived;
use App\Modules\Customers\Contracts\CustomerDirectoryInterface;
use App\Modules\Customers\Contracts\Data\CustomerData;
use App\Modules\Customers\Database\Models\CustomerGroup;
use App\Modules\Customers\Domain\Actions\CreateCustomer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

/*
 * Every layer writes its own changes into the change log, in the step that
 * makes them: what was there, and what is there now.
 */

beforeEach(function () {
    $this->owner = actingAsAdmin();
});

/**
 * The log, oldest first, as «subject action label».
 *
 * @return list<string>
 */
function logged(): array
{
    return AuditEntry::query()->orderBy('id')->get()->map(fn (AuditEntry $entry) => "{$entry->subject} {$entry->action->value} {$entry->label}")->all();
}

function lastChanges(): array
{
    return AuditEntry::query()->latest('id')->firstOrFail()->changeList();
}

test('a product: added with what it is, a price changed, its details changed, archived, brought back, deleted', function () {
    $oils = Category::factory()->create(['name' => 'زيوت']);
    $dairy = Category::factory()->create(['name' => 'ألبان']);

    $product = app(SaveProduct::class)->handle(['category_id' => $oils->id, 'name' => 'زيت سائب', 'unit' => SaleUnit::Litre, 'price' => Money::fromDecimalString('75.00'), 'search_terms' => null, 'barcode' => null, 'sku' => null]);

    expect(lastChanges())->toContainEqual(new AuditChange('catalog::module.product.price', null, '75.00'))
        ->toContainEqual(new AuditChange('catalog::module.product.unit', null, 'catalog::module.unit.liter', translated: true));

    app(ChangeProductPrice::class)->handle($product->id, Money::fromDecimalString('80.00'));
    app(ChangeProductPrice::class)->handle($product->id, Money::fromDecimalString('80.00'));

    expect(lastChanges())->toEqual([new AuditChange('catalog::module.product.price', '75.00', '80.00')]);

    app(SaveProduct::class)->handle(['category_id' => $dairy->id, 'name' => 'زيت سائب', 'unit' => SaleUnit::Litre, 'price' => Money::fromDecimalString('80.00'), 'search_terms' => 'فرط', 'barcode' => null, 'sku' => null], $product->id);

    expect(lastChanges())->toEqual([
        new AuditChange('catalog::module.product.category', 'زيوت', 'ألبان'),
        new AuditChange('catalog::module.product.search_terms', null, 'فرط'),
    ]);

    app(SetProductArchived::class)->handle($product->id, archived: true);
    app(SetProductArchived::class)->handle($product->id, archived: true);
    app(SetProductArchived::class)->handle($product->id, archived: false);
    app(DeleteProduct::class)->handle($product->id);

    expect(logged())->toBe([
        'catalog.product created زيت سائب',
        'catalog.product updated زيت سائب',
        'catalog.product updated زيت سائب',
        'catalog.product archived زيت سائب',
        'catalog.product restored زيت سائب',
        'catalog.product deleted زيت سائب',
    ]);
});

test('a price changed where it stands on the products screen is logged too', function () {
    $product = Product::factory()->priced('90.00')->create(['name' => 'زيت كريستال']);

    Livewire::test('catalog::admin.page.products')->call('editPrice', $product->id)->set('price', '95')->call('savePrice');

    expect(logged())->toBe(['catalog.product updated زيت كريستال'])
        ->and(lastChanges())->toEqual([new AuditChange('catalog::module.product.price', '90.00', '95.00')]);
});

test('a category: added, renamed, given another icon', function () {
    $category = app(SaveCategory::class)->handle(['name' => 'منظفات', 'parent_id' => null, 'icon' => 'bucket']);
    app(SaveCategory::class)->handle(['name' => 'منظفات ومساحيق', 'parent_id' => null, 'icon' => 'stars'], $category->id);

    expect(logged())->toBe(['catalog.category created منظفات', 'catalog.category updated منظفات ومساحيق'])
        ->and(lastChanges())->toEqual([
            new AuditChange('catalog::module.category.name', 'منظفات', 'منظفات ومساحيق'),
            new AuditChange('catalog::module.category.icon', 'catalog::module.icons.bucket', 'catalog::module.icons.stars', translated: true),
        ]);
});

test('a customer: added, then their name and phone changed — and only what changed', function () {
    $customer = app(CreateCustomer::class)->handle(['name' => 'محمد أحمد', 'phone' => '01001234567']);

    app(CustomerDirectoryInterface::class)->update(new CustomerData($customer->id, 'محمد أحمد علي', '01009998888', null, null));

    expect(logged())->toBe(['customers.customer created محمد أحمد', 'customers.customer updated محمد أحمد علي'])
        ->and(lastChanges())->toEqual([
            new AuditChange('customers::module.audit.fields.name', 'محمد أحمد', 'محمد أحمد علي'),
            new AuditChange('customers::module.audit.fields.phone', '01001234567', '01009998888'),
        ]);
});

test('a credit limit raised is logged under the customer\'s name; the same limit again is not', function () {
    $customer = app(CreateCustomer::class)->handle(['name' => 'نادية سمير']);

    app(SetCreditLimit::class)->handle($customer->id, Money::fromDecimalString('500.00'));
    app(SetCreditLimit::class)->handle($customer->id, Money::fromDecimalString('500.00'));

    expect(logged())->toBe(['customers.customer created نادية سمير', 'accounts.account updated نادية سمير'])
        ->and(lastChanges())->toEqual([new AuditChange('accounts::module.account.credit_limit', '0.00', '500.00')]);
});

test('a customer group: made, recoloured, archived', function () {
    Livewire::test('accounts::admin.customers.groups')
        ->set('name', 'جيران')
        ->set('color', 'blue')
        ->call('save');

    $group = CustomerGroup::query()->sole();

    Livewire::test('accounts::admin.customers.groups')
        ->call('edit', $group->id)
        ->set('color', 'green')
        ->call('save')
        ->call('archive', $group->id);

    expect(logged())->toBe(['accounts.group created جيران', 'accounts.group updated جيران', 'accounts.group archived جيران'])
        ->and(AuditEntry::query()->where('action', AuditAction::Updated)->sole()->changeList())->toEqual([
            new AuditChange('accounts::module.groups.color', 'customers::module.group_color.blue', 'customers::module.group_color.green', translated: true),
        ]);
});

test('a setting changed from its tab is logged under its own name; one left as it was is not', function () {
    $place = (int) collect(AccountSettings::definitions())->search(fn ($setting) => $setting->key === AccountSettings::LATE_AFTER_DAYS);
    $toggle = (int) collect(AccountSettings::definitions())->search(fn ($setting) => $setting->key === AccountSettings::ALLOW_OVERPAYMENT);

    Livewire::test('admin::page.settings.declared', ['section' => 'accounts'])
        ->set("values.{$place}", '45')
        ->set("values.{$toggle}", false)
        ->call('save');

    expect(logged())->toBe([
        'accounts.setting updated '.__('accounts::module.settings.late_after_days'),
        'accounts.setting updated '.__('accounts::module.settings.allow_overpayment'),
    ])
        ->and(AuditEntry::query()->orderBy('id')->get()->map->changeList()->all())->toEqual([
            [new AuditChange('accounts::module.settings.late_after_days', '30', '45')],
            [new AuditChange('accounts::module.settings.allow_overpayment', 'foundation.audit.on', 'foundation.audit.off', translated: true)],
        ]);

    expect(app(SettingStore::class)->has(AccountSettings::DORMANT_AFTER_DAYS))->toBeTrue();
});

<?php

use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Settings\SettingStore;
use App\Modules\Accounts\Database\Models\AccountTransaction;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use App\Modules\Accounts\Domain\Services\AccountSettings;
use App\Modules\Customers\Domain\Actions\CreateCustomer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->owner = actingAsAdmin();
});

/**
 * The «Accounts» tab, as the shell draws it from the module's declarations.
 */
function accountsTab(): mixed
{
    return Livewire::test('admin::page.settings.declared', ['section' => 'accounts']);
}

/**
 * Where each declared value sits on the tab.
 */
function placeOf(string $key): int
{
    return (int) collect(AccountSettings::definitions())->search(fn ($setting) => $setting->key === $key);
}

test('the owner finds an Accounts tab drawn from what the module declares, at its configured values', function () {
    readingIn('ar');
    config(['accounts.late_after_days' => 45]);

    $this->get(route('admin.settings', ['tab' => 'accounts']))
        ->assertOk()
        ->assertSee(__('accounts::module.settings.title', locale: 'ar'))
        ->assertSee(__('accounts::module.settings.late_after_days', locale: 'ar'))
        ->assertSee(__('accounts::module.settings.free_line_name', locale: 'ar'));

    $tab = accountsTab();

    expect($tab->get('values')[placeOf(AccountSettings::LATE_AFTER_DAYS)])->toBe('45')
        ->and($tab->get('values')[placeOf(AccountSettings::FREE_LINE_NAME)])->toBe('')
        ->and($tab->get('values')[placeOf(AccountSettings::ALLOW_OVERPAYMENT)])->toBeTrue();
});

test('what the owner saves is what the module reads, typed on either keyboard', function () {
    accountsTab()
        ->set('values.'.placeOf(AccountSettings::FREE_LINE_NAME), '  طلبات البيت ')
        ->set('values.'.placeOf(AccountSettings::LATE_AFTER_DAYS), '٤٥')
        ->set('values.'.placeOf(AccountSettings::ALLOW_OVERPAYMENT), false)
        ->set('values.'.placeOf(AccountSettings::DEFAULT_CREDIT_LIMIT), '٥٠٠')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('saved', true);

    $settings = new AccountSettings(new SettingStore);

    expect($settings->freeLineName())->toBe('طلبات البيت')
        ->and($settings->lateAfterDays())->toBe(45)
        ->and($settings->allowsOverpayment())->toBeFalse()
        ->and($settings->defaultCreditLimit()->toDecimalString())->toBe('500.00')
        ->and($settings->dormantAfterDays())->toBe((int) config('accounts.dormant_after_days'));
});

test('a value outside its bounds is refused, and nothing is stored', function (string $key, string $typed) {
    accountsTab()
        ->set('values.'.placeOf($key), $typed)
        ->call('save')
        ->assertHasErrors('values.'.placeOf($key))
        ->assertSet('saved', false);

    expect((new SettingStore)->has($key))->toBeFalse();
})->with([
    'no days' => [AccountSettings::LATE_AFTER_DAYS, '0'],
    'more than a year' => [AccountSettings::LATE_AFTER_DAYS, '400'],
    'not a number' => [AccountSettings::DORMANT_AFTER_DAYS, 'ثلاثين'],
    'not an amount' => [AccountSettings::DEFAULT_CREDIT_LIMIT, 'كثير'],
    'a name too long' => [AccountSettings::FREE_LINE_NAME, str_repeat('ط', 61)],
]);

test('the tab is not offered to whoever may not configure the panel, nor saved by them', function () {
    $cashier = AdminUser::factory()->create();
    $cashier->syncRoles('cashier');
    $this->actingAs($cashier, Area::Admin->guard());

    $this->get(route('admin.settings', ['tab' => 'accounts']))
        ->assertOk()
        ->assertDontSee(__('accounts::module.settings.late_after_days'));

    accountsTab()->call('save')->assertForbidden();
});

test('a charge line nobody named is called what the owner calls such lines', function () {
    $customer = app(CreateCustomer::class)->handle(['name' => 'محمد أحمد علي']);

    Livewire::test('accounts::admin.entry-drawer')
        ->dispatch('open-entry', customerId: $customer->id)
        ->set('search', '350')
        ->call('save')
        ->assertHasNoErrors();

    app(SettingStore::class)->put(AccountSettings::FREE_LINE_NAME, 'طلبات البيت');

    Livewire::test('accounts::admin.entry-drawer')
        ->dispatch('open-entry', customerId: $customer->id)
        ->call('addFreeLine')
        ->assertSeeHtml('placeholder="طلبات البيت"')
        ->set('form.lines.0.amount', '40')
        ->call('save')
        ->assertHasNoErrors();

    expect(AccountTransaction::query()->with('items')->orderBy('id')->get()->map(fn ($entry) => $entry->items->sole()->name)->all())
        ->toBe([__('accounts::module.entry.misc'), 'طلبات البيت']);
});

test('a new account opens with the credit limit the owner set', function () {
    app(SettingStore::class)->put(AccountSettings::DEFAULT_CREDIT_LIMIT, '750.00');

    $customer = app(CreateCustomer::class)->handle(['name' => 'نادية سمير']);

    expect(CustomerAccount::query()->where('customer_id', $customer->id)->sole()->credit_limit->toDecimalString())->toBe('750.00');
});

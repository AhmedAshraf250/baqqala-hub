<?php

use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Money\Money;
use App\Modules\Accounts\Contracts\AccountLedgerInterface;
use App\Modules\Accounts\Contracts\Data\ChargeLine;
use App\Modules\Accounts\Contracts\Data\ChargeRequest;
use App\Modules\Accounts\Contracts\Data\PaymentRequest;
use App\Modules\Accounts\Contracts\Enums\CorrectionReason;
use App\Modules\Accounts\Contracts\Enums\EntryKind;
use App\Modules\Accounts\Database\Models\AccountTransaction;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use App\Modules\Catalog\Database\Models\Category;
use App\Modules\Catalog\Database\Models\Product;
use App\Modules\Customers\Contracts\Data\CustomerData;
use App\Modules\Customers\Domain\Actions\CreateCustomer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->admin = actingAsAdmin();
    $this->customer = app(CreateCustomer::class)->handle(['name' => 'محمد أحمد علي']);
    $this->other = app(CreateCustomer::class)->handle(['name' => 'محمد أحمد حسن', 'phone' => '01119876543']);

    $shelf = Category::factory()->create(['name' => 'أرز ومكرونة']);
    $this->rice = Product::factory()->for($shelf)->priced('150.00')->create(['name' => 'أرز الضحى 5 كجم']);
    $this->cheese = Product::factory()->for($shelf)->byWeight()->priced('140.00')->create(['name' => 'جبنة بيضاء سائبة']);
});

function chargedBy(int $by, CustomerData $customer, array $lines): int
{
    return app(AccountLedgerInterface::class)->charge(new ChargeRequest($customer->id, $by, (string) Str::uuid(), $lines))->first()->entryId;
}

function standingOf(CustomerData $customer): string
{
    return CustomerAccount::query()->where('customer_id', $customer->id)->firstOrFail()->outstanding->toDecimalString();
}

function detailsOf(int $entryId): Testable
{
    return Livewire::test('accounts::admin.entry-details')->dispatch('open-entry-details', entryId: $entryId);
}

function asRole(string $role): AdminUser
{
    $user = AdminUser::factory()->create();
    $user->syncRoles($role);
    test()->actingAs($user, Area::Admin->guard());

    return $user;
}

test('an entry\'s details show what it was made of, and who recorded it, when', function () {
    $entry = chargedBy($this->admin->getKey(), $this->customer, [ChargeLine::product($this->rice->id, 2000, Money::fromDecimalString('140.00'))]);

    detailsOf($entry)
        ->assertDispatched('overlay-show', id: 'entry-details')
        ->assertSeeText('أرز الضحى 5 كجم')
        ->assertSeeText('280.00')
        ->assertSeeText(__('accounts::module.entry.original_price', ['price' => '150.00']))
        ->assertSeeText($this->admin->name)
        ->assertSeeText(AccountTransaction::query()->findOrFail($entry)->reference)
        ->assertSeeText(__('accounts::module.details.standing'));
});

test('a void asks why, then posts the reversal and leaves the entry, struck', function () {
    $entry = chargedBy($this->admin->getKey(), $this->customer, [ChargeLine::free('حاجات', Money::fromDecimalString('350.00'))]);

    $details = detailsOf($entry)
        ->call('startVoid')
        ->assertSeeText(__('accounts::module.details.void_explained', ['amount' => '350.00', 'name' => 'محمد أحمد علي', 'balance' => __('accounts::module.balance.settled')]))
        ->call('void')
        ->assertHasErrors('reason');

    expect(AccountTransaction::query()->count())->toBe(1);

    $details->set('reason', 'duplicate')->call('void')
        ->assertHasNoErrors()
        ->assertDispatched('entry-posted')
        ->assertSeeText(__('accounts::module.details.voided', ['amount' => '350.00']))
        ->assertSeeText(__('accounts::module.statement.voided'));

    $reversal = AccountTransaction::query()->where('kind', EntryKind::Reversal)->sole();

    expect($reversal->reverses_id)->toBe($entry)
        ->and($reversal->reason)->toBe(CorrectionReason::Duplicate)
        ->and(standingOf($this->customer))->toBe('0.00');
});

test('a void decided on a balance a colleague has since changed is turned back', function () {
    $entry = chargedBy($this->admin->getKey(), $this->customer, [ChargeLine::free('حاجات', Money::fromDecimalString('350.00'))]);
    $details = detailsOf($entry)->call('startVoid')->set('reason', 'duplicate');

    chargedBy(AdminUser::factory()->create()->getKey(), $this->customer, [ChargeLine::free('حاجات', Money::fromDecimalString('20.00'))]);

    $details->call('void')->assertSet('problem', __('accounts::module.entry.changed', ['balance' => __('accounts::module.balance.owes').' 370.00']));

    expect(AccountTransaction::query()->where('kind', EntryKind::Reversal)->exists())->toBeFalse();
});

test('whoever recorded an entry may correct it for a while, and then not without the permission', function () {
    $cashier = asRole('cashier');
    $entry = chargedBy($cashier->getKey(), $this->customer, [ChargeLine::free('حاجات', Money::fromDecimalString('50.00'))]);

    detailsOf($entry)
        ->assertSet('entryId', $entry)
        ->assertSeeText(__('accounts::module.details.window', ['time' => local_time(now()->addMinutes(15))->locale(app()->getLocale())->isoFormat('LT')]));

    expect(detailsOf($entry)->instance()->mayCorrect)->toBeTrue();

    $this->travel(16)->minutes();

    $late = detailsOf($entry)->assertSeeText(__('accounts::module.details.why_permission'));

    expect($late->instance()->mayCorrect)->toBeFalse();

    $late->set('reason', 'other')->call('void')->assertForbidden();
});

test('a cashier cannot correct a colleague\'s entry, and a manager can', function () {
    $entry = chargedBy($this->admin->getKey(), $this->customer, [ChargeLine::free('حاجات', Money::fromDecimalString('50.00'))]);

    asRole('cashier');
    expect(detailsOf($entry)->instance()->mayCorrect)->toBeFalse();

    asRole('manager');
    expect(detailsOf($entry)->instance()->mayCorrect)->toBeTrue();
});

test('a void, or what is already voided, says why it cannot be corrected', function () {
    $entry = chargedBy($this->admin->getKey(), $this->customer, [ChargeLine::free('حاجات', Money::fromDecimalString('50.00'))]);
    detailsOf($entry)->call('startVoid')->set('reason', 'duplicate')->call('void');
    $reversal = AccountTransaction::query()->where('kind', EntryKind::Reversal)->sole()->getKey();

    detailsOf($entry)->assertSeeText(__('accounts::module.details.why_voided'));
    detailsOf($reversal)->assertSeeText(__('accounts::module.details.why_reversal'));
});

test('an entry recorded on the wrong customer moves to the right one, whole', function () {
    $entry = chargedBy($this->admin->getKey(), $this->customer, [
        ChargeLine::product($this->rice->id, 2000),
        ChargeLine::productByAmount($this->cheese->id, Money::fromDecimalString('10.00')),
        ChargeLine::free('عيش', Money::fromDecimalString('5.00')),
    ]);

    // Archived since: it moves as a line by its name and what it came to.
    $this->rice->update(['is_active' => false]);

    $move = detailsOf($entry)
        ->call('startMove')
        ->set('moveSearch', 'محمد أحمد')
        ->assertSeeText('محمد أحمد حسن');

    expect($move->instance()->candidates->pluck('id')->all())->toBe([$this->other->id]);

    $move->call('move')->assertHasErrors('moveTo');

    $move->call('chooseMoveTo', $this->other->id)
        ->call('move')
        ->assertHasNoErrors()
        ->assertSeeText(__('accounts::module.details.moved', ['name' => 'محمد أحمد حسن']));

    $replacement = AccountTransaction::query()->whereNotNull('replaces_id')->sole();

    expect(standingOf($this->customer))->toBe('0.00')
        ->and(standingOf($this->other))->toBe('315.00')
        ->and($replacement->items->map(fn ($item) => [$item->name, $item->product_id, $item->line_total->toDecimalString()])->all())->toBe([
            ['أرز الضحى 5 كجم', null, '300.00'],
            ['جبنة بيضاء سائبة', $this->cheese->id, '10.00'],
            ['عيش', null, '5.00'],
        ])
        ->and(AccountTransaction::query()->where('kind', EntryKind::Reversal)->sole()->reason)->toBe(CorrectionReason::WrongCustomer);
});

test('correcting opens the recording drawer with what the entry was', function () {
    $entry = chargedBy($this->admin->getKey(), $this->customer, [
        ChargeLine::product($this->rice->id, 2000),
        ChargeLine::productByAmount($this->cheese->id, Money::fromDecimalString('10.00')),
    ]);

    detailsOf($entry)->call('edit')
        ->assertDispatched('overlay-hide', id: 'entry-details')
        ->assertDispatched('open-correction', entryId: $entry);

    Livewire::test('accounts::admin.entry-drawer')
        ->dispatch('open-correction', entryId: $entry)
        ->assertSet('correcting.id', $entry)
        ->assertSet('form.lines.0.product', $this->rice->id)
        ->assertSet('form.lines.0.quantity', '2')
        ->assertSet('form.lines.0.price', '150.00')
        ->assertSet('form.lines.1.byAmount', true)
        ->assertSet('form.lines.1.amount', '10.00')
        ->assertSeeText(__('accounts::module.entry.title_correct'));
});

test('a correction asks why, and posts the void and what it should have been, as one', function () {
    $entry = chargedBy($this->admin->getKey(), $this->customer, [ChargeLine::product($this->rice->id, 3000)]);
    app(AccountLedgerInterface::class)->payment(new PaymentRequest($this->customer->id, $this->admin->getKey(), (string) Str::uuid(), Money::fromDecimalString('100.00')));

    $drawer = Livewire::test('accounts::admin.entry-drawer')
        ->dispatch('open-correction', entryId: $entry)
        ->set('form.lines.0.quantity', '2')
        // 350 owed; the charge was 450 and should have been 300: 200.
        ->assertSeeText(__('accounts::module.balance.owes').' 200.00')
        ->call('save')
        ->assertHasErrors('correctionReason');

    $drawer->set('correctionReason', 'wrong_amount')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('entry-posted')
        ->assertSet('posted.undoable', false)
        ->assertSeeText(__('accounts::module.entry.corrected', ['balance' => __('accounts::module.balance.owes').' 200.00']));

    $replacement = AccountTransaction::query()->whereNotNull('replaces_id')->sole();

    expect(standingOf($this->customer))->toBe('200.00')
        ->and($replacement->replaces_id)->toBe($entry)
        ->and($replacement->items->sole()->quantity)->toBe(2000)
        ->and(AccountTransaction::query()->where('kind', EntryKind::Reversal)->sole()->operation_id)->toBe($replacement->operation_id);
});

test('a payment is corrected by its amount', function () {
    chargedBy($this->admin->getKey(), $this->customer, [ChargeLine::free('حاجات', Money::fromDecimalString('500.00'))]);
    $payment = app(AccountLedgerInterface::class)->payment(new PaymentRequest($this->customer->id, $this->admin->getKey(), (string) Str::uuid(), Money::fromDecimalString('200.00')))->first()->entryId;

    Livewire::test('accounts::admin.entry-drawer')
        ->dispatch('open-correction', entryId: $payment)
        ->assertSet('form.amount', '200.00')
        ->assertSeeText(__('accounts::module.entry.right_amount'))
        ->set('form.amount', '250')
        ->set('correctionReason', 'wrong_amount')
        ->call('save')
        ->assertHasNoErrors();

    expect(standingOf($this->customer))->toBe('250.00');
});

test('a correction decided on a balance that has changed since is turned back', function () {
    $entry = chargedBy($this->admin->getKey(), $this->customer, [ChargeLine::free('حاجات', Money::fromDecimalString('450.00'))]);
    $seen = CustomerAccount::query()->where('customer_id', $this->customer->id)->value('last_entry_id');

    $drawer = Livewire::test('accounts::admin.entry-drawer')
        ->dispatch('open-correction', entryId: $entry, expectedLastEntryId: $seen)
        ->set('form.lines.0.amount', '400')
        ->set('correctionReason', 'wrong_amount');

    chargedBy(AdminUser::factory()->create()->getKey(), $this->customer, [ChargeLine::free('حاجات', Money::fromDecimalString('20.00'))]);

    $drawer->call('save')->assertSet('problem', __('accounts::module.entry.changed', ['balance' => __('accounts::module.balance.owes').' 470.00']));

    expect(AccountTransaction::query()->whereNotNull('replaces_id')->exists())->toBeFalse();
});

test('nobody corrects what they may not', function () {
    $entry = chargedBy($this->admin->getKey(), $this->customer, [ChargeLine::free('حاجات', Money::fromDecimalString('50.00'))]);
    asRole('cashier');

    Livewire::test('accounts::admin.entry-drawer')->dispatch('open-correction', entryId: $entry)->assertForbidden();
    detailsOf($entry)->call('edit')->assertForbidden();
});

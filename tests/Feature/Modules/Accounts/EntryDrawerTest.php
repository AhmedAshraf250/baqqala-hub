<?php

use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Money\Money;
use App\Modules\Accounts\Contracts\AccountLedgerInterface;
use App\Modules\Accounts\Contracts\Data\ChargeLine;
use App\Modules\Accounts\Contracts\Data\ChargeRequest;
use App\Modules\Accounts\Contracts\Data\EntryOpening;
use App\Modules\Accounts\Contracts\Enums\CorrectionReason;
use App\Modules\Accounts\Contracts\Enums\EntryKind;
use App\Modules\Accounts\Database\Models\AccountTransaction;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use App\Modules\Customers\Contracts\Data\CustomerData;
use App\Modules\Customers\Domain\Actions\CreateCustomer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->admin = actingAsAdmin();
    $this->customer = app(CreateCustomer::class)->handle(['name' => 'محمد أحمد علي', 'phone' => '01001235678']);
});

/**
 * The drawer, opened for the test's customer.
 */
function drawerFor(CustomerData $customer, string $kind = 'charge'): Testable
{
    return Livewire::test('accounts::admin.entry-drawer')->dispatch('open-entry', customerId: $customer->id, kind: $kind);
}

function owes(CustomerData $customer): string
{
    return CustomerAccount::query()->where('customer_id', $customer->id)->firstOrFail()->outstanding->toDecimalString();
}

function postedElsewhere(CustomerData $customer, string $amount): void
{
    app(AccountLedgerInterface::class)->charge(new ChargeRequest(
        $customer->id,
        AdminUser::factory()->create()->getKey(),
        (string) Str::uuid(),
        [ChargeLine::free('حاجات', Money::fromDecimalString($amount))],
    ));
}

test('it opens for one customer, as one operation, focused on what to type', function () {
    $drawer = drawerFor($this->customer)
        ->assertSet('customerId', $this->customer->id)
        ->assertSet('form.kind', 'charge')
        ->assertDispatched('overlay-show', id: 'entry-drawer')
        ->assertSeeText('محمد أحمد علي')
        ->assertSeeText('…5678');

    expect(Str::isUuid($drawer->get('operationId')))->toBeTrue();
});

test('an amount typed in the box is a charge with no product, and Enter adds it', function () {
    drawerFor($this->customer)
        ->set('search', '٣٥٠')
        ->call('addFromSearch')
        ->assertSet('search', '')
        // Unnamed: it shows, and is saved as, the owner's word for such lines.
        ->assertSet('form.lines.0.name', '')
        ->assertSeeHtml('placeholder="'.__('accounts::module.entry.misc').'"')
        ->assertSet('form.lines.0.amount', '350.00')
        ->assertSeeText(__('accounts::module.entry.save_charge', ['amount' => '350.00']))
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('entry-posted')
        ->assertDispatched('overlay-hide', id: 'entry-drawer')
        ->assertDispatched('overlay-show', id: 'entry-toast')
        ->assertSeeText(__('accounts::module.entry.posted_charge', ['name' => 'محمد أحمد علي', 'amount' => '350.00', 'balance' => __('accounts::module.balance.owes').' 350.00']));

    expect(owes($this->customer))->toBe('350.00');
});

test('Ctrl+Enter with the amount still in the box records it at once', function () {
    // «+ 350 Ctrl+Enter»: the main journey, five steps.
    drawerFor($this->customer)
        ->set('search', '350')
        ->call('save')
        ->assertHasNoErrors();

    expect(owes($this->customer))->toBe('350.00')
        ->and(AccountTransaction::query()->sole()->items()->sole()->name)->toBe(__('accounts::module.entry.misc'));
});

test('a word in the box is a line by that name, waiting for its amount', function () {
    drawerFor($this->customer)
        ->set('search', 'عيش وفول')
        ->call('addFromSearch')
        ->assertSet('form.lines.0.name', 'عيش وفول')
        ->assertSet('form.lines.0.amount', '')
        ->call('save')
        ->assertHasErrors(['form.lines.0.amount'])
        ->set('form.lines.0.amount', '45')
        ->call('save')
        ->assertHasNoErrors();

    expect(owes($this->customer))->toBe('45.00');
});

test('nothing on the charge is refused before anything is posted', function () {
    drawerFor($this->customer)
        ->call('save')
        ->assertHasErrors(['form.lines']);

    expect(AccountTransaction::query()->count())->toBe(0);
});

test('a charge paid in part at once posts both, in one operation', function () {
    drawerFor($this->customer)
        ->set('search', '350')
        ->call('addFromSearch')
        ->set('form.payingNow', true)
        ->set('form.paidNow', '100')
        ->assertSeeText(__('accounts::module.balance.owes').' 250.00')
        ->call('save')
        ->assertHasNoErrors();

    $entries = AccountTransaction::query()->orderBy('id')->get();

    expect(owes($this->customer))->toBe('250.00')
        ->and($entries->pluck('kind')->all())->toBe([EntryKind::Charge, EntryKind::Payment])
        ->and($entries->pluck('operation_id')->unique())->toHaveCount(1);
});

test('a payment lowers what is owed, and the preview says so before the save', function () {
    postedElsewhere($this->customer, '900.00');

    // The total, then where the account is now and where it will be.
    drawerFor($this->customer, 'payment')
        ->set('form.amount', '200')
        ->assertSeeText(__('accounts::module.entry.total_payment'))
        ->assertSeeText(__('accounts::module.entry.now'))
        ->assertSeeText(__('accounts::module.balance.owes').' 900.00')
        ->assertSeeText(__('accounts::module.entry.after'))
        ->assertSeeText(__('accounts::module.balance.owes').' 700.00')
        ->call('save')
        ->assertHasNoErrors();

    expect(owes($this->customer))->toBe('700.00');
});

test('paying it all is one press', function () {
    postedElsewhere($this->customer, '420.00');

    drawerFor($this->customer, 'payment')
        ->call('payAll')
        ->assertSet('form.amount', '420.00')
        ->call('save');

    expect(owes($this->customer))->toBe('0.00');
});

test('more than is owed asks whether the rest went back as change or stays as credit', function (string $choice, string $after) {
    postedElsewhere($this->customer, '420.00');

    drawerFor($this->customer, 'payment')
        ->set('form.amount', '500')
        ->assertSeeText(__('accounts::module.entry.overpaid', ['amount' => '80.00']))
        ->set('form.overpayment', $choice)
        ->call('save')
        ->assertHasNoErrors();

    expect(owes($this->customer))->toBe($after);
})->with([
    'change' => ['owed_only', '0.00'],
    'credit' => ['keep_as_credit', '-80.00'],
]);

test('the same amount for the same customer moments ago is asked about, never refused', function () {
    postedElsewhere($this->customer, '350.00');

    $drawer = drawerFor($this->customer)
        ->set('search', '350')
        ->call('save')
        ->assertSet('twin.entry', EntryKind::Charge->label().' 350.00');

    expect(AccountTransaction::query()->count())->toBe(1);

    $drawer->call('confirmTwin')->assertDispatched('entry-posted');

    expect(AccountTransaction::query()->count())->toBe(2)
        ->and(owes($this->customer))->toBe('700.00');
});

test('«the same one» records nothing and closes', function () {
    postedElsewhere($this->customer, '350.00');

    drawerFor($this->customer)
        ->set('search', '350')
        ->call('save')
        ->call('dropTwin')
        ->assertDispatched('overlay-hide', id: 'entry-drawer')
        ->assertSet('form.lines', []);

    expect(AccountTransaction::query()->count())->toBe(1);
});

test('a twin long gone, or voided, is not asked about', function () {
    $this->travelTo(now()->subMinutes(5));
    postedElsewhere($this->customer, '350.00');
    $this->travelBack();

    drawerFor($this->customer)
        ->set('search', '350')
        ->call('save')
        ->assertSet('twin', null);

    expect(AccountTransaction::query()->count())->toBe(2);
});

test('a payment decided on a balance a colleague has since changed is turned back with the new one', function () {
    postedElsewhere($this->customer, '900.00');

    $drawer = drawerFor($this->customer, 'payment')->set('form.amount', '900');

    postedElsewhere($this->customer, '50.00');

    $drawer->call('save')
        ->assertSet('problem', __('accounts::module.entry.changed', ['balance' => __('accounts::module.balance.owes').' 950.00']));

    expect(owes($this->customer))->toBe('950.00');

    // Asked again, against what it is now, it goes through.
    $drawer->call('save')->assertSet('problem', null);

    expect(owes($this->customer))->toBe('50.00');
});

test('a charge past the customer\'s limit is turned back with what the balance would be', function () {
    CustomerAccount::query()->where('customer_id', $this->customer->id)->update(['credit_limit' => 10000]);

    drawerFor($this->customer)
        ->set('search', '150')
        ->call('save')
        ->assertSet('problem', __('accounts::module.entry.over_limit', ['limit' => '100.00', 'balance' => __('accounts::module.balance.owes').' 150.00']));

    expect(AccountTransaction::query()->count())->toBe(0);
});

test('an amount that is not one is refused under its field', function (string $typed) {
    drawerFor($this->customer, 'payment')
        ->set('form.amount', $typed)
        ->call('save')
        ->assertHasErrors(['form.amount']);
})->with(['zero' => ['0'], 'three decimals' => ['12.345'], 'letters' => ['abc'], 'negative' => ['-5']]);

test('the toast takes a save back within its seconds, and the entries stay, voided', function () {
    $drawer = drawerFor($this->customer)
        ->set('search', '350')
        ->call('addFromSearch')
        ->set('form.payingNow', true)
        ->set('form.paidNow', '100')
        ->call('save')
        ->call('undo')
        ->assertSeeText(__('accounts::module.entry.undone'))
        ->assertDispatched('entry-posted');

    $reversals = AccountTransaction::query()->where('kind', EntryKind::Reversal)->get();

    expect(owes($this->customer))->toBe('0.00')
        ->and($reversals)->toHaveCount(2)
        ->and($reversals->pluck('reason')->unique()->all())->toBe([CorrectionReason::Undo]);

    // Pressed again, it does nothing.
    $drawer->call('undo');
    expect(AccountTransaction::query()->where('kind', EntryKind::Reversal)->count())->toBe(2);
});

test('an undo after someone else posted is turned back', function () {
    $drawer = drawerFor($this->customer)->set('search', '350')->call('save');

    postedElsewhere($this->customer, '20.00');

    $drawer->call('undo')->assertSeeText(__('accounts::module.entry.undo_refused'));

    expect(owes($this->customer))->toBe('370.00');
});

test('someone who may only look cannot open it, or record through it', function () {
    $viewer = AdminUser::factory()->create();
    $viewer->givePermissionTo('accounts.view');
    test()->actingAs($viewer, Area::Admin->guard());

    Livewire::test('accounts::admin.entry-drawer')
        ->dispatch('open-entry', customerId: $this->customer->id, kind: 'charge')
        ->assertForbidden();
});

test('the list carries the drawer for whoever may record', function () {
    $this->get(route('admin.accounts.index'))->assertOk()->assertSee('id="entry-drawer"', escape: false);
});

test('a line with no product reads like the others: its total where every total is, its field where a price stands', function () {
    drawerFor($this->customer)
        ->call('addFreeLine')
        ->set('form.lines.0.amount', '45')
        ->assertSeeHtml('<div class="entry-line-total num" dir="ltr">45.00</div>')
        ->assertSeeHtml('<div class="entry-line-money">')
        ->assertSeeText(__('accounts::module.entry.free_line'));
});

test('the currency beside a figure\'s field is written in the reader\'s language', function () {
    app()->setLocale('en');
    drawerFor($this->customer, 'payment')->assertSeeHtml('<span class="input-group-text">EGP</span>');

    app()->setLocale('ar');
    drawerFor($this->customer, 'payment')->assertSeeHtml('<span class="input-group-text">ج.م</span>');
});

test('opened from another screen, it arrives filled in and keeps the operation it was handed', function () {
    $operation = (string) Str::uuid();

    $drawer = Livewire::test('accounts::admin.entry-drawer')
        ->dispatch(EntryOpening::EVENT, ...(new EntryOpening(
            customerId: $this->customer->id,
            amount: Money::fromDecimalString('75'),
            description: 'زيت وسكر',
            note: 'من الكشكول',
            operationId: $operation,
        ))->parameters())
        ->assertSet('operationId', $operation)
        ->assertSet('form.note', 'من الكشكول');

    expect($drawer->get('form.lines'))->toHaveCount(1)
        ->and($drawer->get('form.lines')[0]['name'])->toBe('زيت وسكر')
        ->and($drawer->get('form.lines')[0]['amount'])->toBe('75.00');

    // Nothing is posted until it is saved.
    expect(owes($this->customer))->toBe('0.00');

    $drawer->call('save');

    expect(owes($this->customer))->toBe('75.00')
        ->and(AccountTransaction::query()->where('operation_id', $operation)->exists())->toBeTrue();
});

test('a payment handed over arrives as its amount, and an operation that is not one is replaced', function () {
    Livewire::test('accounts::admin.entry-drawer')
        ->dispatch(EntryOpening::EVENT, ...(new EntryOpening(
            customerId: $this->customer->id,
            kind: EntryKind::Payment,
            amount: Money::fromDecimalString('40'),
            operationId: 'not-a-uuid',
        ))->parameters())
        ->assertSet('form.kind', 'payment')
        ->assertSet('form.amount', '40.00')
        ->assertSet('form.lines', [])
        ->assertNotSet('operationId', 'not-a-uuid');
});

<?php

use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Money\Money;
use App\Modules\Accounts\Contracts\AccountLedgerInterface;
use App\Modules\Accounts\Contracts\Data\ChargeLine;
use App\Modules\Accounts\Contracts\Data\ChargeRequest;
use App\Modules\Accounts\Contracts\Data\EntryOpening;
use App\Modules\Accounts\Contracts\Data\PaymentRequest;
use App\Modules\Accounts\Contracts\Data\VoidRequest;
use App\Modules\Accounts\Contracts\Enums\CorrectionReason;
use App\Modules\Accounts\Contracts\Enums\EntryKind;
use App\Modules\Accounts\Contracts\Enums\OverpaymentChoice;
use App\Modules\Accounts\Database\Models\AccountTransaction;
use App\Modules\Accounts\Domain\Actions\CorrectEntry;
use App\Modules\Customers\Contracts\Data\CustomerData;
use App\Modules\Customers\Domain\Actions\CreateCustomer;
use App\Modules\Notebook\Contracts\Enums\NoteStatus;
use App\Modules\Notebook\Contracts\NoteReaderInterface;
use App\Modules\Notebook\Database\Models\Note;
use App\Modules\Notebook\Domain\Actions\DiscardNote;
use App\Modules\Notebook\NotebookServiceProvider;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->admin = actingAsAdmin();
    $this->ummKarim = app(CreateCustomer::class)->handle(['name' => 'أم كريم', 'phone' => '01001234567']);
    $this->mohamed = app(CreateCustomer::class)->handle(['name' => 'محمد أحمد علي']);
});

/**
 * A note waiting in the notebook, as the quick note writes one.
 */
function jotted(string $body, EntryKind $kind = EntryKind::Charge): Note
{
    return Note::factory()->create(['body' => $body, 'kind' => $kind, 'written_by' => AdminUser::query()->firstOrFail()->getKey()]);
}

/**
 * A name as the notebook sets it inside a sentence: apart from the words
 * around it, so either script reads in its place.
 */
function isolated(string $name): string
{
    return "⁨{$name}⁩";
}

function asNotebookRole(string ...$permissions): AdminUser
{
    $user = AdminUser::factory()->create();
    $user->givePermissionTo($permissions);
    test()->actingAs($user, Area::Admin->guard());

    return $user;
}

/*
|--------------------------------------------------------------------------
| Jotting, in the rush
|--------------------------------------------------------------------------
*/

test('a note is jotted in words from any screen, and moves no balance', function () {
    Livewire::test('notebook::admin.capture')
        ->set('body', 'أم كريم زيت وسكر 75')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('body', '')
        ->assertDispatched('overlay-hide', id: 'notebook-capture')
        ->assertDispatched('overlay-show', id: 'notebook-toast')
        ->assertDispatched('note-written')
        ->assertSeeText(__('notebook::module.capture.saved'));

    $note = Note::query()->sole();

    expect($note->body)->toBe('أم كريم زيت وسكر 75')
        ->and($note->kind)->toBe(EntryKind::Charge)
        ->and($note->status)->toBe(NoteStatus::Pending)
        ->and($note->written_by)->toBe($this->admin->getKey())
        ->and(Str::isUuid($note->operation_id))->toBeTrue()
        ->and(AccountTransaction::query()->count())->toBe(0);
});

test('money handed over is jotted the same way', function () {
    Livewire::test('notebook::admin.capture')
        ->set('kind', 'payment')
        ->set('body', 'محمد دفع 100')
        ->call('save');

    expect(Note::query()->sole()->kind)->toBe(EntryKind::Payment);
});

test('a note says something, and is only something taken or paid', function () {
    Livewire::test('notebook::admin.capture')
        ->call('save')
        ->assertHasErrors(['body' => 'required'])
        ->set('body', 'شيء')
        ->set('kind', 'discount')
        ->call('save')
        ->assertHasErrors(['kind']);

    expect(Note::query()->count())->toBe(0);
});

test('the quick note is on every screen for whoever may jot, and the drawer for whoever may post', function () {
    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('id="notebook-capture"', escape: false)
        ->assertSee('data-shortcut-click="KeyK"', escape: false)
        ->assertSee('id="entry-drawer"', escape: false);

    // A new request, as the next one in production is: what each person may
    // have is worked out once per request.
    app()->forgetScopedInstances();
    asNotebookRole('accounts.view');

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee('id="notebook-capture"', escape: false)
        ->assertDontSee('id="entry-drawer"', escape: false);
});

/**
 * A clip as a browser records one: WebM's first bytes, then enough to hold
 * a second of sound.
 */
function recorded(string $name = 'note.webm', int $bytes = 4096): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "\x1A\x45\xDF\xA3".str_repeat("\x42", $bytes));
}

test('a voice note is kept only once it is known whole — its kind from its bytes, its fingerprint read back', function () {
    Storage::fake('local');

    $this->postJson(route('admin.notebook.voice.store'), [
        'voice' => recorded(),
        'seconds' => 6,
        'peak' => 40,
        'kind' => 'charge',
        'body' => 'أم كريم',
    ])->assertCreated()->assertJson(['seconds' => 6]);

    $note = Note::query()->sole();
    $kept = (string) Storage::disk('local')->get((string) $note->voice_path);

    expect($note->hasVoice())->toBeTrue()
        ->and($note->voice_type)->toBe('audio/webm')
        ->and(str_ends_with((string) $note->voice_path, '.webm'))->toBeTrue()
        ->and($note->voice_seconds)->toBe(6)
        ->and($note->voice_peak)->toBe(40)
        ->and($note->voice_bytes)->toBe(strlen($kept))
        ->and($note->voice_sha256)->toBe(hash('sha256', $kept))
        ->and($note->body)->toBe('أم كريم');

    $this->get(route('admin.notebook.voice.show', $note))
        ->assertOk()
        ->assertHeader('Content-Type', 'audio/webm');
});

test('what is not a sound is refused, however it is named — and nothing of it is kept', function (UploadedFile $voice) {
    Storage::fake('local');

    $this->postJson(route('admin.notebook.voice.store'), ['voice' => $voice])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('voice');

    expect(Note::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'a picture' => fn () => UploadedFile::fake()->image('photo.png'),
    'silence named .webm' => fn () => UploadedFile::fake()->create('note.webm', 24, 'audio/webm'),
    'a sound too short to hold a word' => fn () => recorded(bytes: 100),
    'too large' => fn () => recorded(bytes: 5 * 1024 * 1024),
]);

test('whoever may not jot sends nothing', function () {
    Storage::fake('local');
    asNotebookRole('accounts.view');

    $this->postJson(route('admin.notebook.voice.store'), ['voice' => recorded()])->assertForbidden();

    expect(Note::query()->count())->toBe(0);
});

test('whoever said it hears it back, to know it was kept; someone else\'s clip is for whoever goes through the notebook', function () {
    Storage::fake('local');
    Storage::disk('local')->put('notebook/2026-10/theirs.webm', 'sound');
    $theirs = Note::factory()->voice('notebook/2026-10/theirs.webm')->create(['written_by' => $this->admin->getKey()]);

    $writer = asNotebookRole('notebook.write');

    $this->postJson(route('admin.notebook.voice.store'), ['voice' => recorded()])->assertCreated();
    $mine = Note::query()->where('written_by', $writer->getKey())->sole();

    $this->get(route('admin.notebook.voice.show', $mine))->assertOk();
    $this->get(route('admin.notebook.voice.show', $theirs))->assertForbidden();
});

test('after a voice note is saved, the toast plays it back from the disk', function () {
    $note = Note::factory()->voice()->create(['written_by' => $this->admin->getKey()]);

    Livewire::test('notebook::admin.capture')
        ->dispatch('notebook-voice-saved', note: $note->id, seconds: 5)
        ->assertSet('heard', ['note' => $note->id, 'seconds' => 5])
        ->assertSeeHtml('src="'.route('admin.notebook.voice.show', $note->id).'"')
        ->assertSeeText(trans_choice('notebook::module.voice.check', 5, ['seconds' => 5]))
        ->assertDispatched('overlay-show', id: 'notebook-toast');
});

test('a clip that went missing, or changed since it was saved, is said so; a faint one is flagged', function () {
    Storage::fake('local');
    Storage::disk('local')->put('notebook/whole.webm', str_repeat('x', 2000));
    Storage::disk('local')->put('notebook/changed.webm', str_repeat('x', 1500));
    Storage::disk('local')->put('notebook/faint.webm', str_repeat('x', 2000));

    Note::factory()->voice('notebook/whole.webm')->create(['voice_bytes' => 2000, 'voice_peak' => 60]);
    Note::factory()->voice('notebook/gone.webm')->create(['voice_bytes' => 2000]);
    Note::factory()->voice('notebook/changed.webm')->create(['voice_bytes' => 2000]);
    Note::factory()->voice('notebook/faint.webm')->create(['voice_bytes' => 2000, 'voice_peak' => 5]);

    $page = Livewire::test('notebook::admin.page.index')
        ->assertSeeText(__('notebook::module.page.clip_missing'))
        ->assertSeeText(__('notebook::module.page.clip_damaged'))
        ->assertSeeText(__('notebook::module.page.clip_faint'));

    // The whole and the faint can be played; the other two cannot.
    expect(substr_count($page->html(), '<audio'))->toBe(2);
});

/*
|--------------------------------------------------------------------------
| Reading a note
|--------------------------------------------------------------------------
*/

test('a note is read for who, how much, and what for — the name first or last, the figure in either script', function (string $text, ?string $who, ?string $amount, string $what) {
    $reading = app(NoteReaderInterface::class)->read($text);

    expect($reading->customer()?->name)->toBe($who)
        ->and($reading->amount?->toDecimalString())->toBe($amount)
        ->and($reading->description)->toBe($what);
})->with([
    'name first' => ['أم كريم زيت وسكر 75', 'أم كريم', '75.00', 'زيت وسكر'],
    'name last, Arabic digits' => ['زيت وسكر أم كريم ٧٥', 'أم كريم', '75.00', 'زيت وسكر'],
    'part of a name' => ['محمد أحمد أخد عيش بـ20', 'محمد أحمد علي', '20.00', 'عيش'],
    'no name, a currency word' => ['أخد زيت 50 جنيه', null, '50.00', 'زيت'],
    'no figure' => ['أم كريم سكر', 'أم كريم', null, 'سكر'],
]);

test('a name more than one customer shares is offered as each of them', function () {
    app(CreateCustomer::class)->handle(['name' => 'محمد سامي']);

    $reading = app(NoteReaderInterface::class)->read('محمد 30');

    expect($reading->customer())->toBeNull()
        ->and(collect($reading->customers)->pluck('name')->sort()->values()->all())->toBe(['محمد أحمد علي', 'محمد سامي']);
});

/*
|--------------------------------------------------------------------------
| Going through the notebook
|--------------------------------------------------------------------------
*/

test('the notebook lists what waits, oldest first, each with whose it seems to be and how much', function () {
    $this->travelTo(now()->subHour());
    jotted('أم كريم زيت وسكر 75');
    $this->travelBack();
    jotted('سكر');

    Livewire::test('notebook::admin.page.index')
        ->assertSeeTextInOrder(['أم كريم زيت وسكر 75', 'سكر'])
        ->assertSeeText(trans_choice('notebook::module.page.waiting', 2, ['count' => 2]))
        ->assertSeeText(__('notebook::module.page.post_amount', ['name' => isolated('أم كريم'), 'amount' => '75.00']))
        // Nobody is named in the second: the person finds them.
        ->assertSeeText(__('notebook::module.page.choose'));
});

test('choosing whose it is opens the recording drawer on it, filled in, carrying the note\'s operation', function () {
    $note = jotted('أم كريم زيت وسكر 75');

    Livewire::test('notebook::admin.page.index')
        ->call('post', $note->id, $this->ummKarim->id)
        ->assertDispatched(EntryOpening::EVENT, function (string $event, array $parameters) use ($note) {
            return $parameters['customerId'] === $this->ummKarim->id
                && $parameters['kind'] === 'charge'
                && $parameters['amount'] === '75.00'
                && $parameters['description'] === 'زيت وسكر'
                && $parameters['operationId'] === $note->operation_id
                && str_contains($parameters['note'], 'أم كريم زيت وسكر 75');
        });

    // Nothing is posted by opening.
    expect($note->fresh()->status)->toBe(NoteStatus::Pending)
        ->and(AccountTransaction::query()->count())->toBe(0);
});

test('another customer is found by name or phone, and the drawer opens for them', function () {
    $note = jotted('زيت 40');

    Livewire::test('notebook::admin.page.index')
        ->call('choose', $note->id)
        ->set('find', '4567')
        ->assertSeeText('أم كريم')
        ->set('find', 'نبيل')
        ->assertSeeText(__('notebook::module.page.no_match'))
        ->call('post', $note->id, $this->mohamed->id)
        ->assertSet('choosing', null)
        ->assertDispatched(EntryOpening::EVENT);
});

/*
|--------------------------------------------------------------------------
| Following the ledger
|--------------------------------------------------------------------------
*/

/**
 * Save the drawer opened on a note: what the person does after checking it.
 */
function postedFrom(Note $note, CustomerData $customer): int
{
    return app(AccountLedgerInterface::class)->charge(new ChargeRequest(
        $customer->id,
        AdminUser::query()->firstOrFail()->getKey(),
        $note->operation_id,
        [ChargeLine::free('زيت وسكر', Money::fromDecimalString('75.00'))],
    ))->first()->entryId;
}

test('a note is posted when the entry it opened is saved, and waits again when that entry is undone', function () {
    $note = jotted('أم كريم زيت وسكر 75');
    $entry = postedFrom($note, $this->ummKarim);

    $posted = $note->fresh();

    expect($posted->status)->toBe(NoteStatus::Posted)
        ->and($posted->customer_id)->toBe($this->ummKarim->id)
        ->and($posted->entry_id)->toBe($entry)
        ->and($posted->settled_by)->toBe($this->admin->getKey());

    app(AccountLedgerInterface::class)->void(new VoidRequest($entry, $this->admin->getKey(), (string) Str::uuid(), CorrectionReason::Undo));

    $waiting = $note->fresh();

    expect($waiting->status)->toBe(NoteStatus::Pending)
        // A new operation, so its next posting is never taken for the old one.
        ->and($waiting->operation_id)->not->toBe($note->operation_id)
        ->and($waiting->customer_id)->toBe($this->ummKarim->id);

    Livewire::test('notebook::admin.page.index')
        ->assertSeeText(__('notebook::module.page.was_on', ['name' => isolated('أم كريم')]));
});

test('a correction moves the note to the entry that replaces its own', function () {
    $note = jotted('محمد دفع 50', EntryKind::Payment);
    app(AccountLedgerInterface::class)->charge(new ChargeRequest($this->mohamed->id, $this->admin->getKey(), (string) Str::uuid(), [ChargeLine::free('حاجات', Money::fromDecimalString('200.00'))]));

    $entry = app(AccountLedgerInterface::class)->payment(new PaymentRequest($this->mohamed->id, $this->admin->getKey(), $note->operation_id, Money::fromDecimalString('50.00'), overpayment: OverpaymentChoice::KeepAsCredit))->first()->entryId;

    app(CorrectEntry::class)->handle($entry, $this->admin->getKey(), (string) Str::uuid(), CorrectionReason::WrongAmount, amount: Money::fromDecimalString('40.00'));

    $corrected = $note->fresh();
    $replacement = AccountTransaction::query()->where('replaces_id', $entry)->sole();

    expect($corrected->status)->toBe(NoteStatus::Posted)
        ->and($corrected->entry_id)->toBe($replacement->id);
});

test('an entry from some other operation leaves the notebook alone', function () {
    $note = jotted('أم كريم 75');

    app(AccountLedgerInterface::class)->charge(new ChargeRequest($this->ummKarim->id, $this->admin->getKey(), (string) Str::uuid(), [ChargeLine::free('حاجات', Money::fromDecimalString('75.00'))]));

    expect($note->fresh()->status)->toBe(NoteStatus::Pending);
});

/*
|--------------------------------------------------------------------------
| Deleting, and who may
|--------------------------------------------------------------------------
*/

test('a note that was nothing goes to the trash with its clip, and stays in today\'s list', function () {
    Storage::fake('local');
    Storage::disk('local')->put('notebook/2026-10/clip.webm', 'sound');
    $note = Note::factory()->voice('notebook/2026-10/clip.webm')->create(['written_by' => $this->admin->getKey()]);

    Livewire::test('notebook::admin.page.index')
        ->call('discard', $note->id)
        ->assertSeeText(__('notebook::module.page.discarded'))
        ->assertSeeText(__('notebook::module.page.today'))
        ->assertSeeText(__('notebook::module.page.discarded_by', ['name' => isolated($this->admin->name)]));

    // Kept, clip and all, so a note deleted by mistake comes back whole.
    expect($note->fresh()->status)->toBe(NoteStatus::Discarded);
    Storage::disk('local')->assertExists('notebook/2026-10/clip.webm');
});

test('a note already posted is not deleted from the notebook — it is put right on the account', function () {
    $note = jotted('أم كريم زيت وسكر 75');
    postedFrom($note, $this->ummKarim);

    expect(app(DiscardNote::class)->handle($note->id, $this->admin->getKey()))->toBeFalse()
        ->and($note->fresh()->status)->toBe(NoteStatus::Posted);
});

test('only whoever goes through the notebook sees it, hears it, or deletes from it', function () {
    $note = jotted('أم كريم 75');

    asNotebookRole('notebook.write');

    $this->get(route('admin.notebook.index'))->assertForbidden();
    $this->get(route('admin.notebook.voice.show', $note))->assertForbidden();

    Livewire::test('notebook::admin.page.index')->call('discard', $note->id)->assertForbidden();

    expect($note->fresh()->status)->toBe(NoteStatus::Pending);
});

test('a cashier jots and goes through the notebook', function () {
    $cashier = AdminUser::factory()->create();
    $cashier->syncRoles('cashier');
    $this->actingAs($cashier, Area::Admin->guard());

    $this->get(route('admin.notebook.index'))->assertOk();
    $this->get(route('admin.dashboard'))->assertSee('id="notebook-capture"', escape: false);
});

test('uninstalling the notebook takes its voice clips, which no migration knows about', function () {
    Storage::fake('local');
    Storage::disk('local')->put('notebook/2026-10/one.webm', 'sound');
    Storage::disk('local')->put('notebook/2026-10/two.webm', 'sound');
    Storage::disk('local')->put('elsewhere/keep.txt', 'kept');

    $module = new NotebookServiceProvider(app());

    expect($module->leftovers())->toBe(['2 voice notes on the local disk']);

    $module->uninstall();

    expect($module->leftovers())->toBe([]);
    Storage::disk('local')->assertExists('elsewhere/keep.txt');
});

test('a name more than one customer shares asks which one, by name alone', function () {
    app(CreateCustomer::class)->handle(['name' => 'محمد سامي']);
    $note = jotted('محمد 30');

    Livewire::test('notebook::admin.page.index')
        ->assertSeeText(__('notebook::module.page.which'))
        ->assertSeeHtml('aria-label="'.e(__('notebook::module.page.post_for', ['name' => 'محمد سامي'])).'"')
        ->assertSeeHtml('aria-label="'.e(__('notebook::module.page.post_for', ['name' => 'محمد أحمد علي'])).'"');
});

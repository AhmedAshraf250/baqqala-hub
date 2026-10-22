<?php

use App\Foundation\Area\Area;
use App\Modules\Accounts\Contracts\Enums\EntryKind;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use App\Modules\Customers\Domain\Actions\CreateCustomer;
use App\Modules\Notebook\Contracts\Enums\NoteStatus;
use App\Modules\Notebook\Database\Models\Note;
use App\Modules\Notebook\Domain\Actions\WriteNote;

/*
 * The notebook in a real browser: jotting from any screen with the K key,
 * going through the notes with axe and on a phone, and a note becoming an
 * entry through the drawer.
 */

/**
 * A shop with a few notes jotted in the rush: one for a customer by name,
 * one for a name two customers share, and money handed over.
 */
function jottedInTheRush(): void
{
    $by = actingAsAdmin()->getKey();
    $create = app(CreateCustomer::class);
    $create->handle(['name' => 'أم كريم', 'phone' => '01001234567']);
    $create->handle(['name' => 'محمد أحمد علي']);
    $create->handle(['name' => 'محمد سامي']);

    $write = app(WriteNote::class);
    $write->handle($by, EntryKind::Charge, 'أم كريم زيت وسكر 75');
    $write->handle($by, EntryKind::Charge, 'محمد عيش 20');
    $write->handle($by, EntryKind::Payment, 'محمد سامي دفع 100');
}

function notebookPage(string $route, string $language, string $mode): mixed
{
    test()->withCookie(Area::Admin->localeCookie(), $language);

    $page = visit(route($route, absolute: false));

    return $mode === 'dark' ? $page->inDarkMode() : $page->inLightMode();
}

test('K from any screen, a line, Enter — it is in the notebook, and no balance moved', function () {
    jottedInTheRush();

    notebookPage('admin.accounts.index', 'ar', 'light')
        ->keys('.app-content', 'k')
        ->assertVisible('#notebook-capture.show')
        ->assertScript('document.activeElement.id', 'notebook-body')
        ->typeSlowly('#notebook-body', 'ام كريم 30')
        ->keys('#notebook-body', 'Enter')
        ->assertVisible('#notebook-toast.show')
        ->assertSee(__('notebook::module.capture.saved'))
        ->assertNoJavaScriptErrors();

    expect(Note::query()->where('body', 'ام كريم 30')->exists())->toBeTrue()
        ->and(CustomerAccount::query()->sum('outstanding'))->toBe(0);
});

test('the quick note, open, passes axe', function (string $language, string $mode) {
    jottedInTheRush();

    $page = notebookPage('admin.dashboard', $language, $mode)
        ->click('.fab')
        ->assertVisible('#notebook-capture.show');

    // axe reads colours as they are drawn: after the dialog has faded in.
    $page->script('() => new Promise((resolve) => setTimeout(resolve, 400))');

    $page->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
})->with(['ar', 'en'])->with(['light', 'dark']);

test('the notebook, with notes waiting, passes axe and fits a phone', function (string $language, string $mode) {
    jottedInTheRush();

    $page = notebookPage('admin.notebook.index', $language, $mode)
        ->assertSee('أم كريم زيت وسكر 75')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    expect(phoneOverflow($page))->toBeLessThanOrEqual(0);
})->with(['ar', 'en'])->with(['light', 'dark']);

test('a note goes to its account through the drawer, filled in, and is posted when saved', function () {
    jottedInTheRush();

    $page = notebookPage('admin.notebook.index', 'ar', 'light')
        ->click('.note-card .btn-flow-solid >> nth=0')
        ->assertVisible('#entry-drawer.show')
        ->assertSee('أم كريم')
        ->assertSee(__('accounts::module.entry.save_charge', ['amount' => '75.00']))
        ->keys('#entry-search', 'Control+Enter')
        // The note leaves the waiting list for today's, marked where it went.
        ->assertSee(__('notebook::module.page.today'))
        ->assertSee(trans_choice('notebook::module.page.waiting', 2, ['count' => 2]))
        ->assertNoJavaScriptErrors();

    $note = Note::query()->where('body', 'أم كريم زيت وسكر 75')->sole();

    expect($note->status)->toBe(NoteStatus::Posted)
        ->and(CustomerAccount::query()->where('customer_id', $note->customer_id)->value('outstanding')->toDecimalString())->toBe('75.00');

});

test('on a phone, the button is within reach and opens the quick note', function () {
    jottedInTheRush();

    $page = notebookPage('admin.accounts.index', 'ar', 'light')->resize(375, 812);

    $page->assertVisible('.fab')
        ->click('.fab')
        ->assertVisible('#notebook-capture.show')
        ->assertNoJavaScriptErrors();

    expect(phoneOverflow($page))->toBeLessThanOrEqual(0);
});

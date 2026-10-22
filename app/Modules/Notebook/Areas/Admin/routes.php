<?php

use App\Modules\Notebook\Areas\Admin\Controllers\VoiceNoteController;
use App\Modules\Notebook\Authorization\NotebookPermission;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Notebook — admin screens
|--------------------------------------------------------------------------
|
| Loaded by the admin shell under `/admin/notebook`, named `admin.notebook.…`,
| and already behind the admin guard. Every route names the permission it
| needs; the sidebar item that links here names the same one.
|
*/

// Going through the notebook: what is waiting, and what was settled today.
Route::livewire('/', 'notebook::admin.page.index')
    ->middleware('can:'.NotebookPermission::Review->value)
    ->name('index');

// A voice note, sent from the quick note on any screen…
Route::post('voice', [VoiceNoteController::class, 'store'])
    ->middleware('can:'.NotebookPermission::Write->value)
    ->name('voice.store');

// …heard back by whoever said it, to know it was kept, and when the
// notebook is gone through — the controller tells the two apart.
Route::get('notes/{note}/voice', [VoiceNoteController::class, 'show'])
    ->middleware('can:'.NotebookPermission::Write->value)
    ->name('voice.show');

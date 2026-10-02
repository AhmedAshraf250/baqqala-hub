<?php

use App\Foundation\Area\Area;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Event;

/*
 * Livewire sends every component update to one address outside `/admin`, so
 * the address cannot say which area's session to open. The admin bundle adds
 * `X-Area: admin` to each update; this proves a real browser sends it, and
 * that the update then succeeds in the admin session.
 */

test('a Livewire update from an admin page says it comes from the admin area', function () {
    $areasNamed = [];

    Event::listen(RequestHandled::class, function (RequestHandled $event) use (&$areasNamed): void {
        if ($event->request->hasHeader('X-Livewire')) {
            $areasNamed[] = [$event->request->header(Area::RequestHeader), $event->response->getStatusCode()];
        }
    });

    actingAsAdmin();
    readingIn('en');

    visit(route('admin.settings', absolute: false))
        ->pressAndWaitFor(__('shell.actions.save', locale: 'en'), 2)
        ->assertNoJavaScriptErrors();

    expect($areasNamed)->not->toBeEmpty()
        ->and($areasNamed[0])->toBe([Area::Admin->value, 200]);
});

<?php

use App\Foundation\Settings\SettingStore;
use App\Foundation\Time\LocalClock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(LazilyRefreshDatabase::class);

test('moments are stored in UTC whatever the local zone is', function () {
    // The local zone is a way of reading moments, never of storing them.
    expect(config('app.timezone'))->toBe('UTC')
        ->and(app(LocalClock::class)->timezone())->toBe('Africa/Cairo');
});

test('a stored moment reads on the local wall clock', function () {
    $clock = app(LocalClock::class);

    // 21:30 UTC on an October evening is 00:30 the next day in Cairo (UTC+3).
    $local = $clock->local(CarbonImmutable::parse('2026-10-01 21:30:00', 'UTC'));

    expect($local->format('Y-m-d H:i'))->toBe('2026-10-02 00:30')
        ->and($local->timezoneName)->toBe('Africa/Cairo');
});

test('a business day starts at midnight unless told otherwise', function () {
    $clock = app(LocalClock::class);

    expect($clock->dayStartsAt())->toBe('00:00')
        ->and($clock->businessDate(CarbonImmutable::parse('2026-10-01 22:30:00', 'UTC'))->format('Y-m-d'))->toBe('2026-10-02');
});

test('a sale after midnight counts towards the day the shop opened, when the day starts later', function () {
    app(SettingStore::class)->put(LocalClock::DAY_STARTS_AT, '04:00');

    $clock = app(LocalClock::class);

    // 01:30 on the 2nd in Cairo, before the shop's day turns at 04:00.
    $halfPastOne = CarbonImmutable::parse('2026-10-02 01:30:00', 'Africa/Cairo');
    $fivePast = CarbonImmutable::parse('2026-10-02 05:00:00', 'Africa/Cairo');

    expect($clock->businessDate($halfPastOne)->format('Y-m-d'))->toBe('2026-10-01')
        ->and($clock->businessDate($fivePast)->format('Y-m-d'))->toBe('2026-10-02');
});

test('a business day bounds a query in UTC, from its start to the next', function () {
    app(SettingStore::class)->put(LocalClock::DAY_STARTS_AT, '04:00');

    [$start, $end] = app(LocalClock::class)->dayBounds(CarbonImmutable::parse('2026-10-02', 'Africa/Cairo'));

    // 04:00 in Cairo (UTC+3) is 01:00 UTC.
    expect($start->toDateTimeString())->toBe('2026-10-02 01:00:00')
        ->and($end->toDateTimeString())->toBe('2026-10-03 01:00:00')
        ->and($start->timezoneName)->toBe('UTC');
});

test('the zone the owner chose wins over the configured one', function () {
    app(SettingStore::class)->put(LocalClock::TIMEZONE, 'Asia/Riyadh');

    expect(app(LocalClock::class)->timezone())->toBe('Asia/Riyadh');
});

test('today is the business day it is now', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-02 22:30:00', 'UTC'));

    // 01:30 on the 3rd in Cairo.
    expect(app(LocalClock::class)->today()->format('Y-m-d'))->toBe('2026-10-03');
});

test('a template shows a stored moment through local_time()', function () {
    expect(local_time(CarbonImmutable::parse('2026-10-01 21:30:00', 'UTC'))?->format('H:i'))->toBe('00:30')
        ->and(local_time(null))->toBeNull();
});

test('a setting falls back to its default until the owner sets it', function () {
    $settings = app(SettingStore::class);

    expect($settings->get('foundation.anything', 'default'))->toBe('default');

    $settings->put('foundation.anything', ['kept' => true, 'count' => 3]);

    expect($settings->get('foundation.anything'))->toBe(['kept' => true, 'count' => 3]);
});

test('a saved setting is what the next request reads', function () {
    $owner = actingAsAdmin();

    app(SettingStore::class)->put(LocalClock::TIMEZONE, 'Asia/Dubai', $owner->getKey());

    // What a worker does between requests: the next one starts from the table.
    app()->forgetScopedInstances();

    expect(app(SettingStore::class)->get(LocalClock::TIMEZONE))->toBe('Asia/Dubai')
        ->and(DB::table('settings')->where('key', LocalClock::TIMEZONE)->value('updated_by'))->toBe($owner->getKey());
});

test('the settings are read once per request, and only when asked', function () {
    app(SettingStore::class)->put(LocalClock::TIMEZONE, 'Asia/Dubai');
    app()->forgetScopedInstances();

    DB::enableQueryLog();

    $clock = app(LocalClock::class);
    $clock->timezone();
    $clock->dayStartsAt();
    $clock->timezone();

    expect(DB::getQueryLog())->toHaveCount(1);
});

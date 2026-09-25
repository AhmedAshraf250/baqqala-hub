<?php

use App\Foundation\Identity\Models\FrontendUser;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

/*
 * The frontend's templates render what they are given — by a component's
 * class, a controller, or a Livewire component — and fetch nothing. These
 * prove each of those hands the template what it shows.
 */

test('the user menu shows the signed-in visitor, from the sidebar and from the header', function () {
    $visitor = actingAsCustomer(FrontendUser::factory()->create(['name' => 'Umm Ahmed', 'email' => 'umm.ahmed@example.test']));

    $html = $this->get(route('frontend.account.dashboard'))->assertOk()->getContent();

    // Once in each menu: the sidebar's on a wide screen, the header's on a
    // narrow one.
    expect(substr_count($html, 'umm.ahmed@example.test'))->toBe(2)
        ->and($html)->toContain('data-test="sidebar-menu-button"')
        ->and($html)->toContain($visitor->initials());
});

test('a sign-in screen shows the message it was sent back with', function () {
    $this->withSession(['status' => 'We have emailed your password reset link.'])
        ->get(route('password.request'))
        ->assertOk()
        ->assertSee('We have emailed your password reset link.');
});

test('the reset form carries the link\'s token and address, and the password rules', function () {
    $html = $this->get(route('password.reset', ['token' => 'a-reset-token', 'email' => 'umm.ahmed@example.test']))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('value="a-reset-token"')
        ->toContain('value="umm.ahmed@example.test"')
        ->toMatch('/passwordrules="[^"]+"/');
});

test('the front page offers a signed-in visitor their account, and anyone else the sign-in screen', function () {
    $this->get(route('frontend.home'))->assertSee(route('login'));

    // Signed in to the back office is nobody here.
    actingAsAdmin();
    $this->get(route('frontend.home'))->assertSee(route('login'))->assertDontSee(route('frontend.account.dashboard'));

    actingAsCustomer();
    $this->get(route('frontend.home'))->assertSee(route('frontend.account.dashboard'));
});

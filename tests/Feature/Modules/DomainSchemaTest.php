<?php

use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\User;
use App\Foundation\Money\Money;
use App\Modules\Accounts\Contracts\Enums\TransactionType;
use App\Modules\Accounts\Database\Models\AccountTransaction;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use App\Modules\Catalog\Database\Models\Category;
use App\Modules\Catalog\Database\Models\Product;
use App\Modules\Customers\Database\Models\Customer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('the core domain models persist with their relationships intact', function () {
    $administrator = User::factory()->admin()->create();
    $parentCategory = Category::factory()->create();
    $category = Category::factory()->for($parentCategory, 'parent')->create();

    $product = Product::factory()->for($category)->create([
        'price' => Money::fromDecimalString('25.50'),
        'cost_price' => Money::fromDecimalString('20.00'),
        'attributes' => ['unit' => 'pack'],
    ]);

    $customer = Customer::factory()->withLogin()->create();
    $account = CustomerAccount::factory()->create(['customer_id' => $customer->getKey()]);

    $transaction = AccountTransaction::factory()
        ->for($account, 'account')
        ->for($administrator, 'creator')
        ->credit()
        ->create([
            'amount' => Money::fromDecimalString('25.50'),
            'outstanding_after' => Money::fromDecimalString('25.50'),
        ]);

    expect($category->parent->is($parentCategory))->toBeTrue()
        ->and($product->category->is($category))->toBeTrue()
        // No relation either way between a customer and their account: each
        // belongs to its own module, so the account holds a plain id and asks
        // CustomerRepositoryInterface for the rest.
        ->and($account->customer_id)->toBe($customer->getKey())
        ->and($customer->user->area)->toBe(Area::Frontend)
        ->and($account->transactions()->first()->is($transaction))->toBeTrue()
        ->and($transaction->creator->is($administrator))->toBeTrue()
        ->and($product->attributes)->toBe(['unit' => 'pack'])
        ->and($transaction->type)->toBe(TransactionType::Credit);
});

test('money columns come back as exact Money values', function () {
    $product = Product::factory()->create([
        'price' => Money::fromDecimalString('25.50'),
    ]);

    expect($product->fresh()->price)->toBeInstanceOf(Money::class)
        ->and($product->fresh()->price->toDecimalString())->toBe('25.50');
});

test('a new account owes nothing', function () {
    $account = CustomerAccount::factory()->create();

    expect($account->outstanding->isZero())->toBeTrue()
        ->and($account->isInDebt())->toBeFalse();
});

test('the catalog scopes filter as described', function () {
    $inactive = Category::factory()->inactive()->create();
    $active = Category::factory()->create();

    Product::factory()->lowStock()->for($active)->create();
    Product::factory()->for($active)->create(['stock_quantity' => 500]);

    $activeIds = Category::query()->active()->pluck('id');

    expect($activeIds)->toContain($active->id)
        ->not->toContain($inactive->id)
        ->and(Product::query()->lowStock()->count())->toBe(1);
});

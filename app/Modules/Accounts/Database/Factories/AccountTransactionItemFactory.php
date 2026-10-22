<?php

namespace App\Modules\Accounts\Database\Factories;

use App\Foundation\Money\Money;
use App\Modules\Accounts\Database\Models\AccountTransaction;
use App\Modules\Accounts\Database\Models\AccountTransactionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds charge lines directly, for read-side tests. A line that has to add
 * up with its entry comes from RecordCharge instead.
 *
 * @extends Factory<AccountTransactionItem>
 */
class AccountTransactionItemFactory extends Factory
{
    protected $model = AccountTransactionItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = Money::fromMinorUnits(fake()->numberBetween(100, 20000));

        return [
            'transaction_id' => AccountTransaction::factory(),
            'position' => 0,
            'product_id' => null,
            'name' => fake()->words(2, true),
            'unit' => null,
            'quantity' => 1000,
            'unit_price' => $price,
            'list_price' => null,
            'line_total' => $price,
        ];
    }
}

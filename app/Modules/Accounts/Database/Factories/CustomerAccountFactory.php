<?php

namespace App\Modules\Accounts\Database\Factories;

use App\Foundation\Money\Money;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use App\Modules\Customers\Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerAccount>
 */
class CustomerAccountFactory extends Factory
{
    protected $model = CustomerAccount::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // A declared dependency's fixture, not its model: fixtures may build
            // each other's rows, runtime code may not reach each other's records.
            'customer_id' => CustomerFactory::new(),
            'outstanding' => Money::zero(),
            'credit_limit' => Money::fromDecimalString(
                (string) config('accounts.default_credit_limit'),
            ),
        ];
    }

    /**
     * An account allowed to owe up to the given decimal amount.
     */
    public function withCreditLimit(string $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'credit_limit' => Money::fromDecimalString($amount),
        ]);
    }
}

<?php

namespace App\Modules\Accounts\Database\Factories;

use App\Foundation\Identity\Models\User;
use App\Foundation\Money\Money;
use App\Modules\Accounts\Contracts\Enums\TransactionType;
use App\Modules\Accounts\Database\Models\AccountTransaction;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use App\Modules\Accounts\Domain\Actions\PostAccountTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Builds ledger rows directly, for read-side tests and seeding.
 *
 * Anything that must keep the account balance in step has to go through
 * {@see PostAccountTransaction} instead.
 *
 * @extends Factory<AccountTransaction>
 */
class AccountTransactionFactory extends Factory
{
    protected $model = AccountTransaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = Money::fromMinorUnits(fake()->numberBetween(100, 20000));

        return [
            'account_id' => CustomerAccount::factory(),
            'type' => fake()->randomElement(TransactionType::cases()),
            'amount' => $amount,
            'outstanding_after' => $amount,
            'description' => fake()->sentence(),
            'reference' => config('accounts.transaction_reference_prefix')
                .'-'.Str::upper(Str::random(10)),
            'created_by' => User::factory()->admin(),
        ];
    }

    /** Taken and not paid for — «عليه». */
    public function debit(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TransactionType::Debit,
        ]);
    }

    /** Money handed over — «له». */
    public function credit(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TransactionType::Credit,
        ]);
    }
}

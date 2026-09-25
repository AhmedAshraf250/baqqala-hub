<?php

namespace App\Modules\Accounts\Database\Models;

use App\Foundation\Identity\Models\User;
use App\Foundation\Money\Money;
use App\Foundation\Money\MoneyCast;
use App\Modules\Accounts\Contracts\Enums\TransactionType;
use App\Modules\Accounts\Database\Factories\AccountTransactionFactory;
use App\Modules\Accounts\Domain\Actions\PostAccountTransaction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One immutable line in a customer account ledger.
 *
 * Rows are written by {@see PostAccountTransaction}
 * and are never edited afterwards; a mistake is corrected by posting its
 * inverse, so the history stays auditable.
 *
 * @property int $id
 * @property int $account_id
 * @property TransactionType $type
 * @property Money $amount
 * @property Money $outstanding_after
 * @property string|null $description
 * @property string $reference
 * @property int $created_by
 */
#[UseFactory(AccountTransactionFactory::class)]
#[Fillable(['type', 'amount', 'outstanding_after', 'description', 'reference', 'created_by'])]
class AccountTransaction extends Model
{
    /** @use HasFactory<AccountTransactionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<CustomerAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(CustomerAccount::class, 'account_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Newest entries first, the order a statement is read in.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'amount' => MoneyCast::class,
            'outstanding_after' => MoneyCast::class,
        ];
    }
}

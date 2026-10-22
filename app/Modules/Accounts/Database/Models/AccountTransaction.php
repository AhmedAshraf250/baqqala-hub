<?php

namespace App\Modules\Accounts\Database\Models;

use App\Foundation\Identity\Models\User;
use App\Foundation\Money\Money;
use App\Foundation\Money\MoneyCast;
use App\Modules\Accounts\Authorization\AccountTransactionPolicy;
use App\Modules\Accounts\Contracts\Data\PostedEntry;
use App\Modules\Accounts\Contracts\Enums\CorrectionReason;
use App\Modules\Accounts\Contracts\Enums\EntryKind;
use App\Modules\Accounts\Contracts\Enums\TransactionType;
use App\Modules\Accounts\Contracts\Exceptions\EntryAlreadyVoided;
use App\Modules\Accounts\Contracts\Exceptions\ReversalCannotBeVoided;
use App\Modules\Accounts\Database\Factories\AccountTransactionFactory;
use App\Modules\Accounts\Domain\Actions\PostAccountTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/**
 * One immutable line in a customer account ledger.
 *
 * Rows are written by {@see PostAccountTransaction} and never edited or
 * deleted afterwards — the model refuses both. A mistake is corrected by a
 * reversal pointing at it (`reverses_id`), and a corrected version points at
 * what it replaces (`replaces_id`), so the history says what happened, when,
 * and who put it right.
 *
 * `kind` is what happened; `type` the way it moved the balance. `amount` is
 * always positive, and `outstanding_after` is the balance right after it, so
 * the balance before is never more than a subtraction away.
 *
 * @property int $id
 * @property int $account_id
 * @property TransactionType $type
 * @property EntryKind $kind
 * @property Money $amount
 * @property Money $outstanding_after
 * @property string|null $description
 * @property CorrectionReason|null $reason
 * @property int|null $reverses_id
 * @property int|null $replaces_id
 * @property string|null $operation_id
 * @property string $reference
 * @property int $created_by
 * @property CarbonImmutable $created_at
 */
#[UseFactory(AccountTransactionFactory::class)]
#[UsePolicy(AccountTransactionPolicy::class)]
#[Fillable(['type', 'kind', 'amount', 'outstanding_after', 'description', 'reason', 'reverses_id', 'replaces_id', 'operation_id', 'reference', 'created_by'])]
class AccountTransaction extends Model
{
    /** @use HasFactory<AccountTransactionFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('A ledger entry is never edited. Post a reversal, or a correction, instead.');
        });

        static::deleting(static function (): never {
            throw new LogicException('A ledger entry is never deleted. Post a reversal instead.');
        });
    }

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
     * What the entry was made of, in the order it was entered.
     *
     * @return HasMany<AccountTransactionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(AccountTransactionItem::class, 'transaction_id')->orderBy('position');
    }

    /**
     * The reversal that cancelled this entry, if one did.
     *
     * @return HasOne<self, $this>
     */
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_id');
    }

    /**
     * The entry this one cancels.
     *
     * @return BelongsTo<self, $this>
     */
    public function reversed(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_id');
    }

    /**
     * The entry this one is the corrected version of.
     *
     * @return BelongsTo<self, $this>
     */
    public function replaced(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_id');
    }

    /**
     * The corrected version of this entry, if it was corrected.
     *
     * @return HasOne<self, $this>
     */
    public function replacement(): HasOne
    {
        return $this->hasOne(self::class, 'replaces_id');
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
     * Refuse to cancel what may not be cancelled: a reversal, or an entry
     * already reversed. The unique `reverses_id` holds the second line even
     * against two requests at once; this says why, first.
     *
     * @throws ReversalCannotBeVoided
     * @throws EntryAlreadyVoided
     */
    public function assertVoidable(): void
    {
        if (! $this->kind->isCorrectable()) {
            throw new ReversalCannotBeVoided($this->getKey());
        }

        $reversedBy = self::query()->where('reverses_id', $this->getKey())->value('id');

        if ($reversedBy !== null) {
            throw new EntryAlreadyVoided($this->getKey(), (int) $reversedBy);
        }
    }

    /**
     * The balance right before this entry.
     */
    public function outstandingBefore(): Money
    {
        return $this->type->applyTo($this->outstanding_after, $this->amount->negated());
    }

    /**
     * This entry as other modules see it.
     */
    public function toPosted(int $customerId): PostedEntry
    {
        return new PostedEntry(
            entryId: $this->getKey(),
            customerId: $customerId,
            kind: $this->kind,
            type: $this->type,
            amount: $this->amount,
            outstandingBefore: $this->outstandingBefore(),
            outstandingAfter: $this->outstanding_after,
            reference: $this->reference,
            postedAt: CarbonImmutable::instance($this->created_at),
        );
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
            'kind' => EntryKind::class,
            'reason' => CorrectionReason::class,
            'amount' => MoneyCast::class,
            'outstanding_after' => MoneyCast::class,
        ];
    }
}

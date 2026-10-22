<?php

namespace App\Foundation\Audit;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * One line of the change log: who changed what, when, and how.
 *
 * Written by {@see AuditTrail} and never changed afterwards — the model
 * refuses an update, the way the ledger's entries do.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $subject
 * @property int|null $subject_id
 * @property string $label
 * @property string $kind
 * @property AuditAction $action
 * @property list<array{label: string, from: string|null, to: string|null, translated: bool}>|null $fields
 * @property CarbonImmutable $created_at
 */
class AuditEntry extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('A change-log entry is never changed.');
        });
    }

    /**
     * What changed, field by field.
     *
     * @return list<AuditChange>
     */
    public function changeList(): array
    {
        return array_map(
            static fn (array $change): AuditChange => AuditChange::fromArray($change),
            $this->fields ?? [],
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
            'action' => AuditAction::class,
            'fields' => 'array',
            'subject_id' => 'integer',
            'user_id' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}

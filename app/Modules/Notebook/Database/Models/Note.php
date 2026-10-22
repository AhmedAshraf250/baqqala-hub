<?php

namespace App\Modules\Notebook\Database\Models;

use App\Modules\Accounts\Contracts\Enums\EntryKind;
use App\Modules\Notebook\Contracts\Enums\NoteStatus;
use App\Modules\Notebook\Database\Factories\NoteFactory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Something jotted at the counter in the rush — «أم كريم زيت وسكر 75», or
 * the same said into the microphone — to be posted to the right account
 * when the shop is quiet.
 *
 * A note is not money: it moves no balance and no report counts it. Only
 * what jotted it is fillable; where it stands — `status`, the operation that
 * will post it, the account and entry it went to — is written by the steps
 * that move it, never from a form.
 *
 * @property int $id
 * @property EntryKind $kind
 * @property string|null $body
 * @property string|null $voice_path
 * @property string|null $voice_type
 * @property int|null $voice_seconds
 * @property int|null $voice_bytes
 * @property string|null $voice_sha256
 * @property int|null $voice_peak
 * @property int|null $written_by
 * @property NoteStatus $status
 * @property string $operation_id
 * @property int|null $customer_id
 * @property int|null $entry_id
 * @property int|null $settled_by
 * @property CarbonImmutable|null $settled_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[UseFactory(NoteFactory::class)]
#[Fillable(['kind', 'body', 'voice_path', 'voice_type', 'voice_seconds', 'voice_bytes', 'voice_sha256', 'voice_peak'])]
class Note extends Model
{
    /** @use HasFactory<NoteFactory> */
    use HasFactory;

    /** Below this loudness, 0 to 100, a clip is faint. */
    public const int FAINT = 12;

    protected $table = 'notebook_notes';

    protected $attributes = [
        'kind' => 'charge',
        'status' => 'pending',
    ];

    public function hasVoice(): bool
    {
        return $this->voice_path !== null;
    }

    /**
     * Whether the browser heard it only faintly — a clip worth listening to
     * closely before posting it.
     */
    public function isFaint(): bool
    {
        return $this->voice_peak !== null && $this->voice_peak < self::FAINT;
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('status'), NoteStatus::Pending->value);
    }

    protected function casts(): array
    {
        return [
            'kind' => EntryKind::class,
            'status' => NoteStatus::class,
            'voice_seconds' => 'integer',
            'voice_bytes' => 'integer',
            'voice_peak' => 'integer',
            'settled_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}

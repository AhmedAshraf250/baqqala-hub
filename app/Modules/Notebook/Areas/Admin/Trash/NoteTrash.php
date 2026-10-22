<?php

namespace App\Modules\Notebook\Areas\Admin\Trash;

use App\Admin\Contracts\Trash\TrashSourceInterface;
use App\Admin\Trash\TrashItem;
use App\Foundation\Identity\Models\AdminUser;
use App\Modules\Notebook\Authorization\NotebookPermission;
use App\Modules\Notebook\Contracts\Enums\NoteStatus;
use App\Modules\Notebook\Database\Models\Note;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Notes deleted from the notebook, with their words and their clips — brought
 * back to wait for posting again, or let go of for good, clip and all.
 */
final readonly class NoteTrash implements TrashSourceInterface
{
    public function label(): string
    {
        return 'notebook::module.trash.notes';
    }

    public function permission(): NotebookPermission
    {
        return NotebookPermission::Review;
    }

    public function items(): array
    {
        $notes = Note::query()->where('status', NoteStatus::Discarded->value)->latest('settled_at')->limit(200)->get();
        $people = AdminUser::query()->whereKey($notes->pluck('settled_by')->filter()->unique()->all())->pluck('name', 'id');

        return array_values($notes->map(static fn (Note $note): TrashItem => new TrashItem(
            id: $note->id,
            title: $note->body ?? __('notebook::module.page.voice_only'),
            subtitle: __('notebook::module.kind.'.$note->kind->value).($note->hasVoice() ? ' · '.__('notebook::module.trash.with_voice') : ''),
            deletedAt: $note->settled_at ?? $note->updated_at,
            deletedBy: $note->settled_by === null ? null : ($people[$note->settled_by] ?? null),
            icon: $note->hasVoice() ? 'mic' : 'journal-text',
        ))->all());
    }

    public function restore(int $id): bool
    {
        return DB::transaction(static function () use ($id): bool {
            $note = Note::query()->whereKey($id)->where('status', NoteStatus::Discarded->value)->lockForUpdate()->first();

            // Back to waiting, with a new operation: whatever was opened on it
            // before it was deleted is never taken for its posting.
            return (bool) $note?->forceFill([
                'status' => NoteStatus::Pending,
                'operation_id' => (string) Str::uuid(),
                'settled_by' => null,
                'settled_at' => null,
            ])->save();
        });
    }

    public function purge(int $id): bool
    {
        return DB::transaction(static function () use ($id): bool {
            $note = Note::query()->whereKey($id)->where('status', NoteStatus::Discarded->value)->lockForUpdate()->first();

            if ($note === null) {
                return false;
            }

            $clip = $note->voice_path;
            $note->delete();

            if ($clip !== null) {
                DB::afterCommit(static fn () => Storage::disk((string) config('notebook.voice.disk'))->delete($clip));
            }

            return true;
        });
    }
}

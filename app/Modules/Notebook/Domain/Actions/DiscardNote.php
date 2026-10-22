<?php

namespace App\Modules\Notebook\Domain\Actions;

use App\Modules\Notebook\Contracts\Enums\NoteStatus;
use App\Modules\Notebook\Database\Models\Note;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a note — jotted twice, or nothing was taken after all — into the
 * trash. Only a note still waiting may go; one already posted is put right
 * on the account, as any entry is.
 *
 * Nothing of it is lost yet: its words and its clip stay, so a note deleted
 * by mistake is brought back whole. They go only when it is deleted for good
 * from the trash.
 */
final readonly class DiscardNote
{
    /**
     * @return bool whether it was waiting, and is now in the trash
     */
    public function handle(int $noteId, int $by): bool
    {
        return DB::transaction(function () use ($noteId, $by): bool {
            $note = Note::query()->whereKey($noteId)->lockForUpdate()->firstOrFail();

            if ($note->status !== NoteStatus::Pending) {
                return false;
            }

            $note->forceFill([
                'status' => NoteStatus::Discarded,
                'settled_by' => $by,
                'settled_at' => now(),
            ])->save();

            return true;
        });
    }
}

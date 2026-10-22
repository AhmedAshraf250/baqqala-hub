<?php

namespace App\Modules\Notebook\Domain\Listeners;

use App\Foundation\Area\Area;
use App\Modules\Accounts\Contracts\Enums\EntryKind;
use App\Modules\Accounts\Contracts\Events\AccountEntryPosted;
use App\Modules\Notebook\Contracts\Enums\NoteStatus;
use App\Modules\Notebook\Database\Models\Note;
use Illuminate\Support\Str;

/**
 * Keeps each note where its entry is, from the ledger's own announcements.
 *
 * A note is posted through the drawer, which carries the note's operation
 * id; when the ledger announces an entry from that operation, the note is
 * posted. When that entry is voided — undone at once, or later — the note
 * waits again, with a new operation id, so its next posting is never taken
 * for the old one. When a correction replaces the entry, the note follows
 * the replacement.
 */
final readonly class FollowLedgerEntries
{
    public function handle(AccountEntryPosted $event): void
    {
        if ($event->kind === EntryKind::Reversal) {
            $this->reopen($event->reversesEntryId);

            return;
        }

        if ($event->replacesEntryId !== null && $this->follow($event)) {
            return;
        }

        if ($event->operationId !== null) {
            $this->post($event);
        }
    }

    private function post(AccountEntryPosted $event): void
    {
        $note = Note::query()->pending()->where('operation_id', $event->operationId)->first();

        $note?->forceFill($this->posted($event))->save();
    }

    private function reopen(?int $entryId): void
    {
        $note = $entryId === null ? null : Note::query()
            ->where('status', NoteStatus::Posted->value)
            ->where('entry_id', $entryId)
            ->first();

        // The account and entry stay: a correction's replacement finds the
        // note by them, and the notebook says where it was.
        $note?->forceFill([
            'status' => NoteStatus::Pending,
            'operation_id' => (string) Str::uuid(),
            'settled_by' => null,
            'settled_at' => null,
        ])->save();
    }

    private function follow(AccountEntryPosted $event): bool
    {
        $note = Note::query()->pending()->where('entry_id', $event->replacesEntryId)->first();

        $note?->forceFill($this->posted($event))->save();

        return $note !== null;
    }

    /**
     * @return array<string, mixed>
     */
    private function posted(AccountEntryPosted $event): array
    {
        return [
            'status' => NoteStatus::Posted,
            'customer_id' => $event->customerId,
            'entry_id' => $event->entryId,
            'settled_by' => Area::Admin->user()?->getKey(),
            'settled_at' => now(),
        ];
    }
}

<?php

namespace App\Modules\Notebook\Areas\Admin\Controllers;

use App\Foundation\Area\Area;
use App\Modules\Accounts\Contracts\Enums\EntryKind;
use App\Modules\Notebook\Authorization\NotebookPermission;
use App\Modules\Notebook\Database\Models\Note;
use App\Modules\Notebook\Domain\Actions\WriteNote;
use App\Modules\Notebook\Domain\Exceptions\VoiceClipRefused;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * A note said rather than written: the browser records it, checks it has a
 * voice in it, and sends it here with whatever was typed beside it — and it
 * is played back from here, to whoever said it and to whoever goes through
 * the notebook.
 */
final class VoiceNoteController
{
    public function store(Request $request, WriteNote $write): JsonResponse
    {
        $valid = $request->validate([
            'voice' => ['required', 'file', 'max:'.config('notebook.voice.max_kilobytes')],
            'seconds' => ['nullable', 'integer', 'min:1'],
            'peak' => ['nullable', 'integer', 'min:0', 'max:100'],
            'kind' => ['nullable', Rule::in([EntryKind::Charge->value, EntryKind::Payment->value])],
            'body' => ['nullable', 'string', 'max:'.WriteNote::MAX_LENGTH],
        ]);

        try {
            $note = $write->handle(
                (int) Area::Admin->user()?->getKey(),
                EntryKind::from($valid['kind'] ?? EntryKind::Charge->value),
                $valid['body'] ?? null,
                $request->file('voice'),
                isset($valid['seconds']) ? (int) $valid['seconds'] : null,
                isset($valid['peak']) ? (int) $valid['peak'] : null,
            );
        } catch (VoiceClipRefused) {
            return response()->json([
                'message' => __('notebook::module.voice.refused'),
                'errors' => ['voice' => [__('notebook::module.voice.refused')]],
            ], 422);
        }

        return response()->json(['note' => $note->getKey(), 'seconds' => $note->voice_seconds], 201);
    }

    /**
     * The clip as the disk holds it — to whoever said it, to hear that it was
     * kept, and to whoever goes through the notebook.
     */
    public function show(Note $note): BinaryFileResponse
    {
        $person = Area::Admin->user();

        abort_unless($person !== null && ($note->written_by === $person->getKey() || $person->can(NotebookPermission::Review->value)), 403);

        $disk = Storage::disk((string) config('notebook.voice.disk'));

        abort_if($note->voice_path === null || ! $disk->exists($note->voice_path), 404);

        return response()->file($disk->path($note->voice_path), [
            'Content-Type' => $note->voice_type ?? 'application/octet-stream',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}

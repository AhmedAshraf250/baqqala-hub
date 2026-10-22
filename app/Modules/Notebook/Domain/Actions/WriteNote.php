<?php

namespace App\Modules\Notebook\Domain\Actions;

use App\Modules\Accounts\Contracts\Enums\EntryKind;
use App\Modules\Notebook\Contracts\Enums\NoteStatus;
use App\Modules\Notebook\Database\Models\Note;
use App\Modules\Notebook\Domain\Exceptions\VoiceClipRefused;
use App\Modules\Notebook\Domain\Services\VoiceClip;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Jots a note in the rush: «أخذ» or «دفع», in words, by voice, or both — and
 * nothing else. It is posted to an account later, by a person, through the
 * drawer that records entries; until then it moves no balance.
 *
 * A voice note is kept only once it is known to be whole: its first bytes
 * say it is a sound, and what the disk holds afterwards is, byte for byte,
 * what was sent. Its size and fingerprint are kept with it, so a clip that
 * goes missing or is damaged later is told apart from one that is whole.
 */
final readonly class WriteNote
{
    /** The longest a note's words may be. */
    public const int MAX_LENGTH = 500;

    /**
     * @param  int|null  $voicePeak  How loud the clip was at its loudest, 0 to 100, as the browser heard it.
     *
     * @throws VoiceClipRefused when the clip is not a sound, or did not land whole
     */
    public function handle(int $writtenBy, EntryKind $kind, ?string $body, ?UploadedFile $voice = null, ?int $voiceSeconds = null, ?int $voicePeak = null): Note
    {
        $body = Str::limit(trim((string) $body), self::MAX_LENGTH, '');

        if ($body === '' && $voice === null) {
            throw new InvalidArgumentException('A note says something: in words, by voice, or both.');
        }

        if ($kind !== EntryKind::Charge && $kind !== EntryKind::Payment) {
            throw new InvalidArgumentException('A note is something taken or something paid.');
        }

        $note = new Note(['kind' => $kind, 'body' => $body === '' ? null : $body]);

        if ($voice !== null) {
            $note->fill([
                ...$this->keep($voice),
                'voice_seconds' => $voiceSeconds === null ? null : max(1, min($voiceSeconds, (int) config('notebook.voice.max_seconds'))),
                'voice_peak' => $voicePeak === null ? null : max(0, min($voicePeak, 100)),
            ]);
        }

        try {
            $note->forceFill([
                'written_by' => $writtenBy,
                'status' => NoteStatus::Pending,
                'operation_id' => (string) Str::uuid(),
            ])->save();
        } catch (Throwable $failure) {
            // A clip with no note to it is a file nobody can reach again.
            if ($note->voice_path !== null) {
                $this->disk()->delete($note->voice_path);
            }

            throw $failure;
        }

        return $note;
    }

    /**
     * Keep the clip, under the kind its bytes say it is, and prove it landed.
     *
     * @return array{voice_path: string, voice_type: string, voice_bytes: int, voice_sha256: string}
     */
    private function keep(UploadedFile $voice): array
    {
        $bytes = (string) file_get_contents((string) $voice->getRealPath());
        $format = VoiceClip::format(substr($bytes, 0, 16));

        if ($format === null || strlen($bytes) < VoiceClip::SMALLEST) {
            throw VoiceClipRefused::notASound();
        }

        $fingerprint = hash('sha256', $bytes);
        $path = 'notebook/'.now()->format('Y-m').'/'.Str::uuid().'.'.$format['extension'];
        $disk = $this->disk();

        $disk->put($path, $bytes);

        if (! $disk->exists($path) || hash('sha256', (string) $disk->get($path)) !== $fingerprint) {
            $disk->delete($path);

            throw VoiceClipRefused::notSavedWhole();
        }

        return [
            'voice_path' => $path,
            'voice_type' => $format['type'],
            'voice_bytes' => strlen($bytes),
            'voice_sha256' => $fingerprint,
        ];
    }

    private function disk(): Filesystem
    {
        return Storage::disk((string) config('notebook.voice.disk'));
    }
}

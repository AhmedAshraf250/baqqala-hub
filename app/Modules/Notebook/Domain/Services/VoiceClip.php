<?php

namespace App\Modules\Notebook\Domain\Services;

/**
 * Whether a file is a sound a browser can play, from its first bytes — not
 * from its name, or the type the browser claimed for it — and which kind.
 *
 * The formats are the ones browsers record in: WebM and Ogg (Chrome,
 * Firefox), MP4 (Safari), and WAV and MP3 for anything else.
 */
final class VoiceClip
{
    /** How few bytes a clip of a second or more can be, at the least. */
    public const int SMALLEST = 1024;

    /**
     * The clip's type and the extension it is kept under, or null when the
     * bytes are not a sound at all.
     *
     * @return array{type: string, extension: string}|null
     */
    public static function format(string $head): ?array
    {
        return match (true) {
            str_starts_with($head, "\x1A\x45\xDF\xA3") => ['type' => 'audio/webm', 'extension' => 'webm'],
            str_starts_with($head, 'OggS') => ['type' => 'audio/ogg', 'extension' => 'ogg'],
            substr($head, 4, 4) === 'ftyp' => ['type' => 'audio/mp4', 'extension' => 'm4a'],
            str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WAVE' => ['type' => 'audio/wav', 'extension' => 'wav'],
            str_starts_with($head, 'ID3'), in_array(substr($head, 0, 2), ["\xFF\xFB", "\xFF\xF3", "\xFF\xF2"], true) => ['type' => 'audio/mpeg', 'extension' => 'mp3'],
            default => null,
        };
    }
}

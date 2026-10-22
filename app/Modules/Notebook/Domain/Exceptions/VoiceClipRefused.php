<?php

namespace App\Modules\Notebook\Domain\Exceptions;

use RuntimeException;

/**
 * A voice note that was not kept: what arrived is not a sound, is too short
 * to hold one, or did not land on the disk whole. The person is told, while
 * they can still say it again.
 */
final class VoiceClipRefused extends RuntimeException
{
    public static function notASound(): self
    {
        return new self('The clip is not a sound a browser can play.');
    }

    public static function notSavedWhole(): self
    {
        return new self('The clip read back from the disk is not the clip that was sent.');
    }
}

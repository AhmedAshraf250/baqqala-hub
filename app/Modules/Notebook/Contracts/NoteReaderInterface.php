<?php

namespace App\Modules\Notebook\Contracts;

use App\Modules\Notebook\Contracts\Data\NoteReading;

/**
 * Reads a note jotted in the rush — «أم كريم زيت وسكر 75» — for who it may
 * be about, how much, and what for.
 *
 * The one bound here reads by rules: the customers' own search, and the last
 * figure in the text. It is a contract so a smarter reader — a small model
 * that also hears voice notes — can take its place without the screens
 * knowing. Whatever reads it only suggests; a person posts.
 */
interface NoteReaderInterface
{
    public function read(string $text): NoteReading;
}

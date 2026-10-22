<?php

namespace App\Foundation\Text;

/**
 * Text as a search compares it: the spellings a reader treats as the same word
 * folded into one.
 *
 * People type «احمد» for «أحمد», «جبنه» for «جبنة», «مصطفي» for «مصطفى», with
 * or without vowel marks, in Arabic or Latin digits. A search that tells them
 * apart finds nothing and looks broken. The folding is stored beside the name
 * (a `search_name` column) and applied to what is typed, so both sides meet in
 * the same form and the database compares plain strings.
 *
 * The admin bundle carries a JavaScript twin of this for searches that run in
 * the browser. `tests/Fixtures/search-text.json` holds the expected results,
 * and both implementations are tested against that one file.
 */
final class SearchText
{
    /**
     * Letters that are spelled interchangeably, folded to the commoner form.
     *
     * @var array<string, string>
     */
    private const array LETTERS = [
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
        'ة' => 'ه',
        'ى' => 'ي',
        'ؤ' => 'و',
        'ئ' => 'ي',
    ];

    /**
     * The folded form: Latin digits, lower-case Latin letters, no vowel marks
     * or tatweel, interchangeable letters merged, and anything that is not a
     * letter or a digit reduced to single spaces.
     */
    public static function normalize(string $text): string
    {
        $text = Numerals::toLatin($text);
        $text = mb_strtolower($text, 'UTF-8');

        // Vowel marks and every other combining mark (shadda, the superscript
        // alef, Quranic signs) and tatweel carry no meaning for a search.
        $text = (string) preg_replace('/[\p{Mn}\x{0640}]/u', '', $text);

        $text = strtr($text, self::LETTERS);

        // Punctuation and symbols separate words; a decimal point between two
        // digits is part of a size such as «2.25».
        $text = (string) preg_replace('/(?<!\d)\.|\.(?!\d)|[^\p{L}\p{N}.]+/u', ' ', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}

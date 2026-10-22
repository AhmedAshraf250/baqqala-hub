<?php

namespace App\Foundation\Text;

/**
 * A phone number as it is stored and compared: its digits, and nothing else.
 *
 * The counter types «٠١٠٠ ١٢٣ ٤٥٦٧» one day and «0100-123-4567» the next.
 * Stored as typed, the second would pass as a different person and a search
 * for «4567» would miss the first. Folded here on the way in, both are
 * `01001234567` — and a search folds its digits the same way.
 */
final class PhoneNumber
{
    /**
     * The digits, as 0–9, with a leading `+` kept for an international
     * number; spaces, dashes, dots, and brackets dropped. Null when nothing
     * that was typed is a digit.
     */
    public static function normalize(?string $typed): ?string
    {
        if ($typed === null) {
            return null;
        }

        $typed = trim(Numerals::toLatin($typed));
        $digits = (string) preg_replace('/\D+/', '', $typed);

        if ($digits === '') {
            return null;
        }

        return str_starts_with($typed, '+') ? '+'.$digits : $digits;
    }
}

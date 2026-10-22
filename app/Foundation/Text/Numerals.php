<?php

namespace App\Foundation\Text;

/**
 * Digits as a person types them, made readable by the code that parses them.
 *
 * Someone on an Arabic keyboard types an amount as «٣٥٠٫٥٠»: Arabic-Indic
 * digits and the Arabic decimal separator. A Persian layout gives the eastern
 * forms (۳۵۰). Both mean exactly what «350.50» means, so every parser that
 * reads a number a person typed — money, quantities, a phone number — reads it
 * through here first.
 */
final class Numerals
{
    /**
     * The same text with every Arabic-Indic and eastern Arabic-Indic digit as
     * 0–9, and the Arabic decimal separator as a full stop. Anything else is
     * left alone.
     */
    public static function toLatin(string $text): string
    {
        return strtr($text, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٫' => '.',
        ]);
    }
}

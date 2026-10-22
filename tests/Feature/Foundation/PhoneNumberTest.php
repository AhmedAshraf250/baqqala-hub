<?php

use App\Foundation\Text\PhoneNumber;

test('a phone number is stored as its digits, however it was typed', function (string $typed, string $stored) {
    expect(PhoneNumber::normalize($typed))->toBe($stored);
})->with([
    'Latin, plain' => ['01001234567', '01001234567'],
    'Arabic-Indic digits' => ['٠١٠٠١٢٣٤٥٦٧', '01001234567'],
    'Persian digits' => ['۰۱۰۰۱۲۳۴۵۶۷', '01001234567'],
    'grouped with spaces' => ['0100 123 4567', '01001234567'],
    'grouped with dashes and brackets' => ['(0100) 123-4567', '01001234567'],
    'Arabic-Indic, grouped' => ['٠١٠٠ ١٢٣ ٤٥٦٧', '01001234567'],
    'international keeps its plus' => ['+20 100 123 4567', '+201001234567'],
    'surrounded by spaces' => ['  01001234567  ', '01001234567'],
]);

test('nothing that is a digit is no phone number at all', function (?string $typed) {
    expect(PhoneNumber::normalize($typed))->toBeNull();
})->with([
    'null' => [null],
    'empty' => [''],
    'spaces' => ['   '],
    'only separators' => ['- ( ) +'],
]);

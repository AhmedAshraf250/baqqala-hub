<?php

use App\Foundation\Text\Numerals;
use App\Foundation\Text\SearchText;

/**
 * The expected foldings, shared with the browser's twin of SearchText.
 *
 * Read by path rather than through base_path(): a dataset is resolved before
 * the application boots.
 *
 * @return array<string, array{0: string, 1: string}>
 */
function searchTextCases(): array
{
    $fixture = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/search-text.json'), true, flags: JSON_THROW_ON_ERROR);

    return collect($fixture['cases'])
        ->mapWithKeys(fn (array $case): array => [$case['why'] => [$case['input'], $case['expected']]])
        ->all();
}

test('a search folds the spellings a reader treats as one word', function (string $input, string $expected) {
    expect(SearchText::normalize($input))->toBe($expected);
})->with(searchTextCases());

test('folding twice changes nothing', function (string $input) {
    $once = SearchText::normalize($input);

    expect(SearchText::normalize($once))->toBe($once);
})->with(fn (): array => array_map(fn (array $case): array => [$case[0]], searchTextCases()));

test('what is typed meets what is stored in the same form', function () {
    // The name is folded when it is saved and the query when it is typed, so
    // a plain substring comparison finds «أحمد» from «احمد».
    expect(SearchText::normalize('محمد أحمد علي'))->toContain(SearchText::normalize('احمد'))
        ->and(SearchText::normalize('جبنة بيضا سايبة'))->toContain(SearchText::normalize('جبنه'));
});

test('Arabic-Indic digits become Latin and nothing else moves', function () {
    expect(Numerals::toLatin('٣٥٠٫٥٠ ج.م'))->toBe('350.50 ج.م')
        ->and(Numerals::toLatin('۱۲۳'))->toBe('123')
        ->and(Numerals::toLatin('abc 123'))->toBe('abc 123');
});

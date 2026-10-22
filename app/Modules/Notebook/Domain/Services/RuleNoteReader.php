<?php

namespace App\Modules\Notebook\Domain\Services;

use App\Foundation\Money\Money;
use App\Foundation\Text\Numerals;
use App\Modules\Customers\Contracts\CustomerRepositoryInterface;
use App\Modules\Customers\Contracts\Data\CustomerData;
use App\Modules\Notebook\Contracts\Data\NoteReading;
use App\Modules\Notebook\Contracts\NoteReaderInterface;
use Illuminate\Container\Attributes\Scoped;
use InvalidArgumentException;

/**
 * Reads a note by rules, the way the counter writes one: the name first —
 * «أم كريم زيت وسكر 75» — or last, and the figure last.
 *
 * Who: the longest run of the first words (or the last) that the customers'
 * own search finds anyone by, so «أم كريم» wins over «أم». How much: the last
 * figure, in either script, with or without «ج» or «بـ» stuck to it. What
 * for: the words left, less the ones that only say what happened.
 */
#[Scoped]
final class RuleNoteReader implements NoteReaderInterface
{
    /** Words that say what happened or what money is, not who or what for. */
    private const array FILLER = [
        'أخد', 'اخد', 'أخذ', 'اخذ', 'خد', 'واخد', 'وأخد', 'دفع', 'ودفع', 'دافع', 'سدد',
        'على', 'علي', 'عليه', 'عليها', 'حساب', 'حسابه', 'حسابها', 'الحساب',
        'جنيه', 'جنية', 'جنيها', 'ج', 'جم', 'ج.م', 'ب', 'بـ',
    ];

    /** How many words a name is looked for in, from either end. */
    private const int NAME_WORDS = 3;

    /** How many people a note may seem to be about, at most. */
    private const int CANDIDATES = 3;

    public function __construct(private readonly CustomerRepositoryInterface $customers) {}

    public function read(string $text): NoteReading
    {
        $words = preg_split('/[\s،,:;—–\-]+/u', trim(Numerals::toLatin($text)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        [$amount, $words] = $this->amount($words);
        [$people, $words] = $this->people($words);

        return new NoteReading(
            customers: $people,
            amount: $amount,
            description: implode(' ', array_values(array_filter($words, static fn (string $word): bool => ! in_array($word, self::FILLER, true)))),
        );
    }

    /**
     * The last figure, and the words without it.
     *
     * @param  list<string>  $words
     * @return array{0: Money|null, 1: list<string>}
     */
    private function amount(array $words): array
    {
        for ($at = count($words) - 1; $at >= 0; $at--) {
            if (preg_match('/^(?:بـ?)?(\d+(?:\.\d{1,2})?)(?:ج|جم|جنيه|ج\.م)?$/u', $words[$at], $figure) !== 1) {
                continue;
            }

            try {
                $amount = Money::fromDecimalString($figure[1]);
            } catch (InvalidArgumentException) {
                continue;
            }

            if ($amount->isPositive()) {
                array_splice($words, $at, 1);

                return [$amount, $words];
            }
        }

        return [null, $words];
    }

    /**
     * Who the first words name — or, failing that, the last — and the words
     * that are left.
     *
     * @param  list<string>  $words
     * @return array{0: list<CustomerData>, 1: list<string>}
     */
    private function people(array $words): array
    {
        $longest = min(self::NAME_WORDS, count($words));

        for ($length = $longest; $length >= 1; $length--) {
            $found = $this->customers->search(implode(' ', array_slice($words, 0, $length)), self::CANDIDATES);

            if ($found->isNotEmpty()) {
                return [array_values($found->all()), array_slice($words, $length)];
            }
        }

        for ($length = $longest; $length >= 1; $length--) {
            $found = $this->customers->search(implode(' ', array_slice($words, -$length)), self::CANDIDATES);

            if ($found->isNotEmpty()) {
                return [array_values($found->all()), array_slice($words, 0, count($words) - $length)];
            }
        }

        return [[], $words];
    }
}

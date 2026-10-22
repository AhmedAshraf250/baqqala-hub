<?php

namespace App\Modules\Accounts\Database\Seeders;

use App\Foundation\Money\Money;
use App\Modules\Accounts\Contracts\AccountLedgerInterface;
use App\Modules\Accounts\Contracts\Data\ChargeLine;
use App\Modules\Accounts\Contracts\Data\ChargeRequest;
use App\Modules\Accounts\Contracts\Data\PaymentRequest;
use App\Modules\Accounts\Contracts\Data\VoidRequest;
use App\Modules\Accounts\Contracts\Enums\CorrectionReason;
use App\Modules\Accounts\Contracts\Enums\OverpaymentChoice;
use App\Modules\Accounts\Contracts\Enums\TransactionType;
use App\Modules\Accounts\Contracts\Exceptions\CreditLimitExceeded;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use App\Modules\Accounts\Domain\Actions\CorrectEntry;
use App\Modules\Accounts\Domain\Actions\RecordOpeningBalance;
use App\Modules\Catalog\Contracts\Data\ProductData;
use App\Modules\Catalog\Contracts\ProductRepositoryInterface;
use App\Modules\Customers\Contracts\CustomerDirectoryInterface;
use App\Modules\Customers\Contracts\CustomerGroupRepositoryInterface;
use App\Modules\Customers\Contracts\CustomerRepositoryInterface;
use App\Modules\Customers\Contracts\Data\CustomerData;
use App\Modules\Customers\Contracts\Data\CustomerGroupData;
use App\Modules\Customers\Contracts\Enums\GroupColor;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * A shop's worth of made-up customers and three months of their accounts,
 * for trying the screens on a developer's machine — never for a real shop.
 *
 * Forty customers, three of them «محمد أحمد» so the screens have to tell them
 * apart, in four groups; every entry posted through the real ledger with the
 * clock set back, so balances, the «was, now» on every line, and each
 * account's summary are what the counter would have produced. Among them: a
 * customer who pays on time, one who is late, one gone quiet owing money,
 * one past their limit, one in credit, one who has not bought yet, and a few
 * voided and corrected entries. The same seed gives the same shop every time.
 *
 * Every phone starts with {@see PHONE_PREFIX}, which is how a second run
 * knows the demo is already there.
 */
final readonly class DemoAccounts
{
    public const string PHONE_PREFIX = '0109990';

    /** How far back the history goes. */
    private const int DAYS = 90;

    /**
     * Who they are, and how they use their account. Three share a name.
     *
     * @var list<array{0: string, 1: string, 2: list<string>}>
     */
    private const array PEOPLE = [
        ['محمد أحمد علي', 'regular', ['neighbours']],
        ['محمد أحمد حسن', 'regular', ['staff']],
        ['محمد أحمد السيد', 'late', []],
        ['أم كريم', 'late', ['neighbours', 'important']],
        ['سعيد للأدوات', 'credit', ['wholesale']],
        ['مصطفى عبد الله', 'settled', []],
        ['عم حسن', 'dormant', []],
        ['نادية سمير', 'over_limit', ['staff']],
        ['فاطمة الزهراء', 'regular', ['neighbours']],
        ['إبراهيم يوسف', 'regular', []],
        ['منى عادل', 'settled', ['important']],
        ['خالد عبد الرحمن', 'late', ['wholesale']],
        ['أحمد سامي', 'regular', []],
        ['هدى محمود', 'regular', ['neighbours']],
        ['ياسر فتحي', 'dormant', []],
        ['سارة جمال', 'settled', ['staff']],
        ['عبد الله صلاح', 'regular', ['wholesale']],
        ['رحاب عيد', 'regular', []],
        ['كريم مجدي', 'late', []],
        ['أم يوسف', 'regular', ['neighbours']],
        ['حسام الدين', 'settled', []],
        ['إيمان عبد العزيز', 'regular', ['staff']],
        ['مطعم الأصدقاء', 'regular', ['wholesale', 'important']],
        ['شريف رمضان', 'credit', []],
        ['ولاء حسني', 'regular', []],
        ['عادل مرسي', 'late', ['neighbours']],
        ['نور الهدى', 'regular', []],
        ['طارق نبيل', 'settled', []],
        ['مريم خليل', 'regular', ['neighbours']],
        ['جمال الدين', 'dormant', []],
        ['أسماء رضا', 'regular', ['staff']],
        ['وليد شوقي', 'regular', []],
        ['هالة فؤاد', 'settled', ['important']],
        ['محمود الجزار', 'regular', ['wholesale']],
        ['رانيا كمال', 'regular', []],
        ['زياد عمرو', 'new', []],
        ['دعاء سليمان', 'regular', ['neighbours']],
        ['بكر عوض', 'late', []],
        ['سلمى حامد', 'regular', []],
        ['فرن البركة', 'new', ['wholesale']],
    ];

    /** @var array<string, array{0: string, 1: GroupColor}> */
    private const array GROUPS = [
        'wholesale' => ['جملة', GroupColor::Blue],
        'neighbours' => ['جيران', GroupColor::Teal],
        'important' => ['مهمون', GroupColor::Amber],
        'staff' => ['موظفو الشركة', GroupColor::Purple],
    ];

    public function __construct(
        private CustomerDirectoryInterface $directory,
        private CustomerRepositoryInterface $customers,
        private CustomerGroupRepositoryInterface $groups,
        private AccountLedgerInterface $ledger,
        private ProductRepositoryInterface $products,
        private RecordOpeningBalance $openingBalance,
        private CorrectEntry $correct,
    ) {}

    /**
     * Whether the demo customers are already on the books.
     */
    public function isInstalled(): bool
    {
        return $this->customers->search(self::PHONE_PREFIX, 1)->isNotEmpty();
    }

    /**
     * Add the demo customers and their history.
     *
     * @return array{customers: int, groups: int, entries: int}
     */
    public function install(int $postedBy): array
    {
        $random = new Randomizer(new Mt19937(2026));
        $products = array_values($this->products->getActive()->all());
        $now = CarbonImmutable::now();
        $entries = 0;

        try {
            // On the books before their first entry.
            $this->at($now->subDays(140));

            $groups = $this->addGroups($postedBy);
            $people = $this->addPeople($groups, $postedBy);

            foreach ($people as $index => [$customer, $profile]) {
                $entries += $this->history($customer, $profile, $index, $now, $products, $random, $postedBy);
            }
        } finally {
            $this->at(null);
        }

        return ['customers' => count($people), 'groups' => count($groups), 'entries' => $entries];
    }

    /**
     * @return array<string, int>
     */
    private function addGroups(int $postedBy): array
    {
        $ids = [];
        $order = 10;

        foreach (self::GROUPS as $key => [$name, $color]) {
            $ids[$key] = (int) $this->groups->save(new CustomerGroupData(null, $name, $color, sortOrder: $order, createdBy: $postedBy))->id;
            $order += 10;
        }

        return $ids;
    }

    /**
     * @param  array<string, int>  $groups
     * @return list<array{0: CustomerData, 1: string}>
     */
    private function addPeople(array $groups, int $postedBy): array
    {
        $people = [];

        foreach (self::PEOPLE as $index => [$name, $profile, $memberOf]) {
            // A few buy on account without leaving a number.
            $phone = $index % 9 === 4 ? null : self::PHONE_PREFIX.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT);

            $customer = $this->directory->add(new CustomerData(null, $name, $phone));

            foreach ($memberOf as $group) {
                $this->groups->saveGroupMembers($groups[$group], [(int) $customer->id], add: true, changedBy: $postedBy);
            }

            $people[] = [$customer, $profile];
        }

        return $people;
    }

    /**
     * One customer's three months, by how they use their account, each entry
     * in its turn and none of them later than now.
     *
     * @param  list<ProductData>  $products
     */
    private function history(CustomerData $customer, string $profile, int $index, CarbonImmutable $now, array $products, Randomizer $random, int $postedBy): int
    {
        $id = (int) $customer->id;
        $posted = 0;

        if ($profile === 'new') {
            return 0;
        }

        // Gone quiet: bought a few times long ago, and owes for it still.
        if ($profile === 'dormant') {
            foreach ([130, 122, 115, 104] as $daysAgo) {
                $this->at($now->subDays($daysAgo)->setTime($random->getInt(9, 20), $random->getInt(0, 59)));
                $posted += $this->charge($id, $products, $random, $postedBy);
            }

            return $posted;
        }

        $first = $now->subDays(self::DAYS)->setTime(9, 0);

        // Some arrive owing what the paper book says.
        if ($index % 5 === 0) {
            $this->at($first);
            $this->openingBalance->handle($id, $postedBy, (string) Str::uuid(), Money::fromMinorUnits($random->getInt(20, 120) * 1000), TransactionType::Debit, 'من الكشكول');
            $posted++;
        }

        // A late payer stopped paying some weeks ago, and kept buying.
        $lastPayment = $profile === 'late' ? self::DAYS - $random->getInt(40, 60) : self::DAYS;

        for ($day = 1; $day <= self::DAYS; $day++) {
            $moment = $first->addDays($day)->setTime($random->getInt(8, 21), $random->getInt(0, 59));

            if ($moment->greaterThan($now)) {
                $moment = $now->subMinutes($random->getInt(30, 90));
            }

            if ($random->getInt(1, 100) <= 25 || $day === self::DAYS) {
                $this->at($moment);
                $posted += $this->charge($id, $products, $random, $postedBy);
            }

            if ($day === self::DAYS - 10 && ($index % 7 === 1 || $index % 11 === 3)) {
                $this->at($moment->addMinutes(1));
                $posted += $this->mistake($id, $index, $postedBy);
            }

            if ($day <= $lastPayment && $this->paysToday($profile, $day, $random)) {
                $this->at($moment->addMinutes(5));
                $posted += $this->payment($id, $profile, $random, $postedBy, $day === self::DAYS);
            }
        }

        if ($profile === 'over_limit') {
            // The limit was set after the debt grew past it.
            $owed = CustomerAccount::query()->where('customer_id', $id)->firstOrFail()->outstanding;
            CustomerAccount::query()->where('customer_id', $id)->update(['credit_limit' => max(10_000, intdiv($owed->minorUnits * 2, 3))]);
        }

        return $posted;
    }

    /**
     * @param  list<ProductData>  $products
     */
    private function charge(int $customerId, array $products, Randomizer $random, int $postedBy): int
    {
        $lines = [];

        if ($products === [] || $random->getInt(1, 100) <= 35) {
            $lines[] = ChargeLine::free('حاجات متفرقة', Money::fromMinorUnits($random->getInt(4, 90) * 500));
        } else {
            foreach (array_slice($random->shuffleArray($products), 0, $random->getInt(1, 4)) as $product) {
                $quantity = $product->unit->allowsFractions() ? $random->getInt(1, 8) * 250 : $random->getInt(1, 3) * 1000;
                $lines[] = ChargeLine::product($product->id, $quantity);
            }
        }

        try {
            $this->ledger->charge(new ChargeRequest($customerId, $postedBy, (string) Str::uuid(), $lines));
        } catch (CreditLimitExceeded) {
            return 0;
        }

        return 1;
    }

    private function paysToday(string $profile, int $day, Randomizer $random): bool
    {
        return match ($profile) {
            'settled' => $day % 14 === 0 || $day === self::DAYS,
            'credit' => $day % 20 === 0 || $day === self::DAYS,
            'over_limit' => false,
            default => $random->getInt(1, 100) <= 9,
        };
    }

    private function payment(int $customerId, string $profile, Randomizer $random, int $postedBy, bool $last): int
    {
        $owed = CustomerAccount::query()->where('customer_id', $customerId)->firstOrFail()->outstanding;

        if (! $owed->isPositive()) {
            return 0;
        }

        if ($profile === 'settled' || ($profile === 'credit' && ! $last)) {
            $amount = $owed;
        } elseif ($profile === 'credit') {
            // All of it, and a little more to keep.
            $amount = $owed->plus(Money::fromMinorUnits($random->getInt(2, 20) * 2500));
        } else {
            // Part of it, in round pounds.
            $part = intdiv($owed->minorUnits * $random->getInt(20, 70), 100);
            $amount = Money::fromMinorUnits(min($owed->minorUnits, max(1000, $part - $part % 500)));
        }

        $this->ledger->payment(new PaymentRequest($customerId, $postedBy, (string) Str::uuid(), $amount, overpayment: OverpaymentChoice::KeepAsCredit));

        return 1;
    }

    /**
     * A charge recorded by mistake, then voided as recorded twice — or
     * corrected as a wrong amount — so a statement has crossed-out lines.
     */
    private function mistake(int $customerId, int $index, int $postedBy): int
    {
        $charge = $this->ledger->charge(new ChargeRequest($customerId, $postedBy, (string) Str::uuid(), [ChargeLine::free('حاجات متفرقة', Money::fromDecimalString('35.00'))]));

        if ($index % 7 === 1) {
            $this->ledger->void(new VoidRequest($charge->first()->entryId, $postedBy, (string) Str::uuid(), CorrectionReason::Duplicate));

            return 2;
        }

        $this->correct->handle($charge->first()->entryId, $postedBy, (string) Str::uuid(), CorrectionReason::WrongAmount, lines: [ChargeLine::free('حاجات متفرقة', Money::fromDecimalString('53.00'))]);

        return 3;
    }

    /**
     * Set the clock every posting reads, or let it go.
     */
    private function at(?CarbonImmutable $moment): void
    {
        Carbon::setTestNow($moment);
        CarbonImmutable::setTestNow($moment);
    }
}

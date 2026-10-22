<?php

use App\Modules\Customers\Contracts\CustomerRepositoryInterface;
use App\Modules\Customers\Contracts\Data\CustomerCriteria;
use App\Modules\Customers\Contracts\Data\CustomerData;
use App\Modules\Customers\Database\Models\Customer;
use App\Modules\Customers\Database\Models\CustomerGroup;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

uses(LazilyRefreshDatabase::class);

/**
 * The names a search over the given term finds, best first.
 *
 * @return list<string>
 */
function namesFound(string $term, int $limit = 10): array
{
    return app(CustomerRepositoryInterface::class)->search($term, $limit)
        ->map(fn (CustomerData $customer): string => $customer->name)
        ->all();
}

test('saving a customer folds their name and stores their phone as its digits', function () {
    $customer = Customer::factory()->create(['name' => 'أُسامة الشَّافعي', 'phone' => '٠١٠٠ ١٢٣ ٤٥٦٧']);

    expect($customer->search_name)->toBe('اسامه الشافعي')
        ->and($customer->phone)->toBe('01001234567')
        ->and(app(CustomerRepositoryInterface::class)->find($customer->id)->phone)->toBe('01001234567');
});

test('a blank phone is stored as none, so it is never anyone else\'s', function () {
    // Two blanks stored as '' would collide on the unique index.
    Customer::factory()->create(['phone' => '']);
    Customer::factory()->create(['phone' => '  ']);

    expect(Customer::query()->whereNull('phone')->count())->toBe(2);
});

test('a name is found in any of its spellings', function (string $stored, string $typed) {
    Customer::factory()->create(['name' => $stored]);
    Customer::factory()->create(['name' => 'Someone else entirely']);

    expect(namesFound($typed))->toBe([$stored]);
})->with([
    'hamza on the alef' => ['أحمد سعيد', 'احمد'],
    'hamza under the alef' => ['إبراهيم علي', 'ابراهيم'],
    'taa marbuta as haa' => ['فاطمة حسن', 'فاطمه'],
    'alef maqsura as yaa' => ['مصطفى كمال', 'مصطفي'],
    'vowel marks' => ['مُحَمَّد', 'محمد'],
    'Latin, either case' => ['Mona Adel', 'mona'],
]);

test('every word must match, in any order', function () {
    Customer::factory()->create(['name' => 'محمد أحمد علي']);
    Customer::factory()->create(['name' => 'محمد حسن']);

    expect(namesFound('علي محمد'))->toBe(['محمد أحمد علي'])
        ->and(namesFound('محمد'))->toHaveCount(2);
});

test('digits find a customer by their phone, typed in either script', function (string $typed) {
    Customer::factory()->create(['name' => 'أم كريم', 'phone' => '01001234567']);
    Customer::factory()->create(['name' => 'أبو سمير', 'phone' => '01119876543']);

    expect(namesFound($typed))->toBe(['أم كريم']);
})->with([
    'the last digits' => ['4567'],
    'Arabic-Indic' => ['٤٥٦٧'],
    'the whole number, grouped' => ['0100 123 4567'],
    'a name and digits' => ['كريم 4567'],
]);

test('the best match comes first', function () {
    foreach (['سامح محمد', 'محمد', 'أبومحمد', 'محمد سامي'] as $name) {
        Customer::factory()->create(['name' => $name]);
    }

    // Exactly the term, then starting with it, then a word that starts with
    // it, then containing it.
    expect(namesFound('محمد'))->toBe(['محمد', 'محمد سامي', 'سامح محمد', 'أبومحمد']);
});

test('a phone ending in the digits comes before one that only has them', function () {
    // Named against the alphabet, so only the rank can put them in this order.
    Customer::factory()->create(['name' => 'Adam, who has them in the middle', 'phone' => '01045670000']);
    Customer::factory()->create(['name' => 'Zeina, whose phone ends in them', 'phone' => '01000004567']);

    expect(namesFound('4567'))->toBe(['Zeina, whose phone ends in them', 'Adam, who has them in the middle']);
});

test('a search stops at its limit', function () {
    Customer::factory()->count(5)->sequence(fn ($sequence) => ['name' => "سعيد {$sequence->index}"])->create();

    expect(namesFound('سعيد', limit: 3))->toHaveCount(3);
});

test('a term with nothing to search by finds nobody', function (string $term) {
    Customer::factory()->count(2)->create();

    expect(namesFound($term))->toBe([]);
})->with([
    'empty' => [''],
    'spaces' => ['   '],
    'punctuation only' => ['%_-'],
]);

test('customers stored before the search are folded when it arrives, and a clashing phone is left for a person', function () {
    $migration = require collect(File::glob(app_path('Modules/Customers/Database/Migrations/*_add_search_name_to_customers_table.php')))->sole();
    $migration->down();

    DB::table('customers')->insert([
        ['name' => 'أحمد', 'phone' => '٠١٠٠ ١٢٣ ٤٥٦٧', 'created_at' => now(), 'updated_at' => now()],
        ['name' => 'Same Number', 'phone' => '0100-123-4567', 'created_at' => now(), 'updated_at' => now()],
        ['name' => 'No Number', 'phone' => null, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $migration->up();

    expect(DB::table('customers')->orderBy('id')->get()->map(fn (object $row): array => [$row->search_name, $row->phone])->all())->toBe([
        ['احمد', '01001234567'],
        // Its digits are the first one's: two records of one person, to merge.
        ['same number', '0100-123-4567'],
        ['no number', null],
    ]);
});

test('a list narrows to ids by name and by group, in one query', function () {
    $wholesale = CustomerGroup::factory()->create();
    $neighbours = CustomerGroup::factory()->create();

    $ahmed = Customer::factory()->create(['name' => 'أحمد التاجر']);
    $mona = Customer::factory()->create(['name' => 'منى']);
    $ahmedNextDoor = Customer::factory()->create(['name' => 'أحمد الجار']);
    Customer::factory()->create(['name' => 'Nobody in a group']);

    $wholesale->members()->attach($ahmed->id);
    $neighbours->members()->attach([$mona->id, $ahmedNextDoor->id]);

    $repository = app(CustomerRepositoryInterface::class);

    DB::enableQueryLog();
    $byGroupAndName = $repository->searchIds(new CustomerCriteria('احمد', [$neighbours->id]));

    expect(DB::getQueryLog())->toHaveCount(1)
        ->and($byGroupAndName)->toBe([$ahmedNextDoor->id])
        ->and($repository->searchIds(new CustomerCriteria(groupIds: [$wholesale->id, $neighbours->id])))
        ->toEqualCanonicalizing([$ahmed->id, $mona->id, $ahmedNextDoor->id])
        ->and($repository->searchIds(new CustomerCriteria('احمد')))
        ->toEqualCanonicalizing([$ahmed->id, $ahmedNextDoor->id])
        ->and($repository->searchIds(new CustomerCriteria))->toHaveCount(4);
});

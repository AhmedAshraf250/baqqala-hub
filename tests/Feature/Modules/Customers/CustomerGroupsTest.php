<?php

use App\Foundation\Identity\Models\AdminUser;
use App\Modules\Customers\Contracts\CustomerGroupRepositoryInterface;
use App\Modules\Customers\Contracts\Data\CustomerGroupData;
use App\Modules\Customers\Contracts\Enums\GroupColor;
use App\Modules\Customers\Database\Models\Customer;
use App\Modules\Customers\Database\Models\CustomerGroup;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->groups = app(CustomerGroupRepositoryInterface::class);
    $this->owner = AdminUser::factory()->create()->getKey();
});

/**
 * The ids of the groups a customer is in, archived ones included.
 *
 * @return list<int>
 */
function groupsOf(Customer $customer): array
{
    return $customer->groups()->orderBy('customer_groups.id')->pluck('customer_groups.id')->map(intval(...))->all();
}

test('a group is made, renamed, and recoloured without touching anyone in it', function () {
    $made = $this->groups->save(new CustomerGroupData(null, 'جملة', GroupColor::Blue, createdBy: $this->owner));
    $customer = Customer::factory()->create();
    $this->groups->saveGroupMembers($made->id, [$customer->id], add: true, changedBy: $this->owner);

    $renamed = $this->groups->save(new CustomerGroupData($made->id, 'تجار الجملة', GroupColor::Teal, icon: 'truck'));

    expect($renamed->name)->toBe('تجار الجملة')
        ->and($renamed->color)->toBe(GroupColor::Teal)
        ->and($renamed->icon)->toBe('truck')
        ->and($renamed->memberCount)->toBe(1)
        // Who made it is written once, and a later save does not erase it.
        ->and($renamed->createdBy)->toBe($this->owner)
        ->and(groupsOf($customer))->toBe([$made->id]);
});

test('the groups come in the owner\'s order, with how many are in each', function () {
    $later = CustomerGroup::factory()->create(['name' => 'قريبين', 'sort_order' => 20]);
    $first = CustomerGroup::factory()->create(['name' => 'مهمين', 'sort_order' => 10]);
    $later->members()->attach(Customer::factory()->count(3)->create()->modelKeys());

    $all = $this->groups->getAll();

    expect($all->keys()->all())->toBe([$first->id, $later->id])
        ->and($all[$later->id]->memberCount)->toBe(3)
        ->and($all[$first->id]->memberCount)->toBe(0);
});

test('archiving hides a group and keeps who is in it, for the day it is restored', function () {
    $ramadan = CustomerGroup::factory()->create(['name' => 'رمضان 2025']);
    $customer = Customer::factory()->create();
    $ramadan->members()->attach($customer->id);

    $archived = $this->groups->save(new CustomerGroupData($ramadan->id, $ramadan->name, $ramadan->color, archivedAt: CarbonImmutable::now()));

    expect($archived->isArchived())->toBeTrue()
        ->and($this->groups->getAll()->has($ramadan->id))->toBeFalse()
        ->and($this->groups->getAll(includeArchived: true)->has($ramadan->id))->toBeTrue()
        ->and($this->groups->getForCustomers([$customer->id])->has($customer->id))->toBeFalse()
        ->and(groupsOf($customer))->toBe([$ramadan->id]);

    $restored = $this->groups->save(new CustomerGroupData($ramadan->id, $ramadan->name, $ramadan->color));

    expect($restored->isArchived())->toBeFalse()
        ->and($restored->memberCount)->toBe(1)
        ->and($this->groups->getForCustomers([$customer->id])[$customer->id][0]->id)->toBe($ramadan->id);
});

test('a deleted group goes to the trash, and comes back as it was — memberships and all', function () {
    $group = CustomerGroup::factory()->create(['name' => 'جيران']);
    $customer = Customer::factory()->create();
    $group->members()->attach($customer->id);

    $this->groups->delete($group->id);

    expect($this->groups->find($group->id))->toBeNull()
        ->and($this->groups->findTrashed()->keys()->all())->toBe([$group->id])
        ->and($this->groups->findTrashed()->first()->deletedAt)->not->toBeNull();

    // Saved with no deletion time, it is back.
    $trashed = $this->groups->findTrashed()->first();
    $this->groups->save(new CustomerGroupData($trashed->id, $trashed->name, $trashed->color, $trashed->icon, $trashed->sortOrder, $trashed->archivedAt));

    expect($this->groups->find($group->id)?->memberCount)->toBe(1)
        ->and($this->groups->findTrashed())->toBeEmpty();
});

test('a group leaves the trash for good only once nobody is in it', function () {
    $used = CustomerGroup::factory()->create();
    $used->members()->attach(Customer::factory()->create()->id);
    $empty = CustomerGroup::factory()->create();

    $this->groups->delete($used->id);
    $this->groups->delete($empty->id);

    expect($this->groups->deleteForever($used->id))->toBeFalse()
        ->and($this->groups->deleteForever($empty->id))->toBeTrue()
        ->and($this->groups->findTrashed()->keys()->all())->toBe([$used->id])
        // Only what is in the trash can go for good.
        ->and($this->groups->deleteForever(CustomerGroup::factory()->create()->id))->toBeFalse();
});

test('every customer\'s groups are read in one query, in the owner\'s order', function () {
    $second = CustomerGroup::factory()->create(['sort_order' => 2]);
    $first = CustomerGroup::factory()->create(['sort_order' => 1]);
    [$inBoth, $inOne, $inNone] = Customer::factory()->count(3)->create();
    $second->members()->attach([$inBoth->id, $inOne->id]);
    $first->members()->attach($inBoth->id);

    DB::enableQueryLog();
    $groups = $this->groups->getForCustomers([$inBoth->id, $inOne->id, $inNone->id]);

    expect(DB::getQueryLog())->toHaveCount(1)
        ->and(array_map(fn (CustomerGroupData $group) => $group->id, $groups[$inBoth->id]))->toBe([$first->id, $second->id])
        ->and(array_map(fn (CustomerGroupData $group) => $group->id, $groups[$inOne->id]))->toBe([$second->id])
        ->and($groups->has($inNone->id))->toBeFalse()
        ->and($this->groups->getForCustomers([]))->toBeEmpty();
});

test('a customer is put in exactly the groups chosen, and an archived one is left as it was', function () {
    [$wholesale, $neighbours, $important] = CustomerGroup::factory()->count(3)->create();
    $archivedIn = CustomerGroup::factory()->archived()->create();
    $archivedOut = CustomerGroup::factory()->archived()->create();
    $customer = Customer::factory()->create();
    $customer->groups()->attach([$wholesale->id, $neighbours->id, $archivedIn->id]);

    // The screen offers the three that are not archived; the owner keeps
    // «neighbours», drops «wholesale», adds «important».
    $this->groups->saveMembership($customer->id, [$neighbours->id, $important->id, $archivedOut->id], $this->owner);

    expect(groupsOf($customer))->toBe([$neighbours->id, $important->id, $archivedIn->id])
        ->and(DB::table('customer_group_members')->where(['customer_id' => $customer->id, 'group_id' => $important->id])->value('created_by'))->toBe($this->owner);
});

test('many customers join or leave a group at once, and repeating it changes nothing', function () {
    $group = CustomerGroup::factory()->create();
    $customers = Customer::factory()->count(3)->create()->modelKeys();
    $firstAdder = AdminUser::factory()->create()->getKey();

    $this->groups->saveGroupMembers($group->id, [$customers[0]], add: true, changedBy: $firstAdder);
    $this->groups->saveGroupMembers($group->id, $customers, add: true, changedBy: $this->owner);

    expect($this->groups->find($group->id)->memberCount)->toBe(3)
        // Already in it: who put them there first still stands.
        ->and(DB::table('customer_group_members')->where(['customer_id' => $customers[0]])->value('created_by'))->toBe($firstAdder);

    $this->groups->saveGroupMembers($group->id, [$customers[0], $customers[1]], add: false, changedBy: $this->owner);
    $this->groups->saveGroupMembers($group->id, [$customers[0]], add: false, changedBy: $this->owner);

    expect($this->groups->find($group->id)->memberCount)->toBe(1);
});

test('a customer who is deleted leaves their groups, which stay', function () {
    $group = CustomerGroup::factory()->create();
    $customer = Customer::factory()->create();
    $group->members()->attach($customer->id);

    // A fixture is never announced, so no account holds this one.
    $customer->delete();

    expect($this->groups->find($group->id)->memberCount)->toBe(0);
});

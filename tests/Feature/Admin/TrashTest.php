<?php

use App\Foundation\Area\Area;
use App\Foundation\Audit\AuditAction;
use App\Foundation\Audit\AuditEntry;
use App\Foundation\Identity\Models\AdminUser;
use App\Modules\Accounts\Areas\Admin\Trash\GroupTrash;
use App\Modules\Accounts\Contracts\AccountLedgerInterface;
use App\Modules\Accounts\Contracts\Data\ChargeLine;
use App\Modules\Accounts\Contracts\Data\ChargeRequest;
use App\Modules\Catalog\Areas\Admin\Trash\CategoryTrash;
use App\Modules\Catalog\Areas\Admin\Trash\ProductTrash;
use App\Modules\Catalog\Database\Models\Category;
use App\Modules\Catalog\Database\Models\Product;
use App\Modules\Catalog\Domain\Actions\DeleteCategory;
use App\Modules\Catalog\Domain\Actions\DeleteProduct;
use App\Modules\Customers\Database\Models\CustomerGroup;
use App\Modules\Customers\Domain\Actions\CreateCustomer;
use App\Modules\Notebook\Areas\Admin\Trash\NoteTrash;
use App\Modules\Notebook\Contracts\Enums\NoteStatus;
use App\Modules\Notebook\Database\Models\Note;
use App\Modules\Notebook\Domain\Actions\DiscardNote;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->admin = actingAsAdmin();
    $this->shelf = Category::factory()->create(['name' => 'زيوت وسمنة']);
    $this->oil = Product::factory()->for($this->shelf)->priced('90.00')->create(['name' => 'زيت كريستال 1 لتر', 'barcode' => '6221234500017']);
});

function trashScreen(): mixed
{
    return Livewire::test('admin::page.trash');
}

test('what is deleted waits in the trash, under its kind, with who deleted it', function () {
    app(DeleteProduct::class)->handle($this->oil->id);

    expect(Product::query()->find($this->oil->id))->toBeNull();

    trashScreen()
        ->assertSeeText(__('catalog::module.trash.products'))
        ->assertSeeText('زيت كريستال 1 لتر')
        // Who deleted it, by name: the change log knows.
        ->assertSeeText("\u{2068}{$this->admin->name}\u{2069}");
});

test('a product comes back to its shelf, and the change log says so', function () {
    app(DeleteProduct::class)->handle($this->oil->id);

    trashScreen()
        ->call('restore', ProductTrash::class, $this->oil->id, 'زيت كريستال 1 لتر')
        ->assertSeeText(__('shell.trash.restored', ['name' => "\u{2068}زيت كريستال 1 لتر\u{2069}"]))
        ->assertSeeText(__('shell.trash.empty_title'));

    expect(Product::query()->find($this->oil->id)?->category_id)->toBe($this->shelf->id)
        ->and(AuditEntry::query()->where('subject', 'catalog.product')->pluck('action')->map->value->all())->toBe(['deleted', 'restored']);
});

test('deleted for good, it is gone — after the question — and the change log says so', function () {
    app(DeleteProduct::class)->handle($this->oil->id);

    trashScreen()
        ->assertSeeHtml('data-confirm="'.e(__('shell.trash.purge_confirm', ['name' => 'زيت كريستال 1 لتر'])).'"')
        ->call('purge', ProductTrash::class, $this->oil->id, 'زيت كريستال 1 لتر')
        ->assertSeeText(__('shell.trash.purged', ['name' => "\u{2068}زيت كريستال 1 لتر\u{2069}"]));

    expect(Product::withTrashed()->find($this->oil->id))->toBeNull()
        ->and(AuditEntry::query()->where('subject', 'catalog.product')->latest('id')->value('action'))->toBe(AuditAction::Purged);
});

test('a product that was sold is never deleted: it is archived instead, and the trash never sees it', function () {
    $customer = app(CreateCustomer::class)->handle(['name' => 'أم كريم']);
    app(AccountLedgerInterface::class)->charge(new ChargeRequest($customer->id, $this->admin->getKey(), (string) Str::uuid(), [ChargeLine::product($this->oil->id, 1000)]));

    expect(app(DeleteProduct::class)->handle($this->oil->id))->toBeFalse()
        ->and(Product::query()->find($this->oil->id))->not->toBeNull()
        ->and(app(ProductTrash::class)->items())->toBe([]);
});

test('a category goes to the trash only when nothing is in it — not even what is in the trash itself', function () {
    app(DeleteProduct::class)->handle($this->oil->id);

    // Its product is in the trash, and could come back into it.
    expect(app(DeleteCategory::class)->handle($this->shelf->id))->toBeFalse();

    app(ProductTrash::class)->purge($this->oil->id);

    expect(app(DeleteCategory::class)->handle($this->shelf->id))->toBeTrue()
        ->and(collect(app(CategoryTrash::class)->items())->pluck('title')->all())->toBe(['زيوت وسمنة']);

    trashScreen()->call('restore', CategoryTrash::class, $this->shelf->id, 'زيوت وسمنة');

    expect(Category::query()->find($this->shelf->id))->not->toBeNull();
});

test('a code a product in the trash still holds says where it is, instead of «already taken»', function () {
    app(DeleteProduct::class)->handle($this->oil->id);

    Livewire::test('catalog::admin.product-drawer')
        ->call('open')
        ->set('form.name', 'زيت آخر')
        ->set('form.categoryId', $this->shelf->id)
        ->set('form.unit', 'piece')
        ->set('form.price', '50')
        ->set('form.barcode', '6221234500017')
        ->call('save')
        ->assertHasErrors(['form.barcode'])
        ->assertSeeText(__('catalog::module.product.code_in_trash', ['name' => 'زيت كريستال 1 لتر']));
});

test('a group deleted from the dialog comes back with its name and colour', function () {
    $group = CustomerGroup::factory()->create(['name' => 'جيران']);

    Livewire::test('accounts::admin.customers.groups')->call('delete', $group->id);

    expect(CustomerGroup::query()->find($group->id))->toBeNull();

    trashScreen()
        ->assertSeeText(__('accounts::module.trash.groups'))
        ->assertSeeText('جيران')
        ->call('restore', GroupTrash::class, $group->id, 'جيران');

    expect(CustomerGroup::query()->find($group->id)?->name)->toBe('جيران');
});

test('a note comes back to wait again, with a new operation; deleted for good, its clip goes too', function () {
    Storage::fake('local');
    Storage::disk('local')->put('notebook/clip.webm', 'sound');
    $note = Note::factory()->voice('notebook/clip.webm')->create(['body' => 'أم كريم زيت', 'written_by' => $this->admin->getKey()]);
    $operation = $note->operation_id;

    app(DiscardNote::class)->handle($note->id, $this->admin->getKey());
    trashScreen()->assertSeeText('أم كريم زيت')->call('restore', NoteTrash::class, $note->id, 'أم كريم زيت');

    $back = $note->fresh();

    expect($back->status)->toBe(NoteStatus::Pending)
        ->and($back->operation_id)->not->toBe($operation);

    app(DiscardNote::class)->handle($note->id, $this->admin->getKey());
    trashScreen()->call('purge', NoteTrash::class, $note->id, 'أم كريم زيت');

    expect(Note::query()->find($note->id))->toBeNull();
    Storage::disk('local')->assertMissing('notebook/clip.webm');
});

test('the trash acts only on what it holds, from the kinds it knows', function () {
    trashScreen()
        ->call('restore', ProductTrash::class, $this->oil->id, 'زيت كريستال 1 لتر')
        ->assertSeeText(__('shell.trash.gone'))
        ->call('purge', 'App\\Not\\A\\Source', 1, 'x')
        ->assertSeeText(__('shell.trash.gone'));

    expect(Product::query()->find($this->oil->id))->not->toBeNull();
});

test('only whoever keeps the trash opens it, and each kind shows only to whoever may use it', function () {
    app(DeleteProduct::class)->handle($this->oil->id);
    CustomerGroup::factory()->create(['deleted_at' => now()]);

    $cashier = AdminUser::factory()->create();
    $cashier->syncRoles('cashier');
    $this->actingAs($cashier, Area::Admin->guard());

    $this->get(route('admin.trash'))->assertForbidden();

    $keeper = AdminUser::factory()->create();
    $keeper->givePermissionTo(['trash.manage', 'catalog.manage']);
    $this->actingAs($keeper, Area::Admin->guard());
    app()->forgetScopedInstances();

    trashScreen()
        ->assertSeeText(__('catalog::module.trash.products'))
        ->assertDontSeeText(__('accounts::module.trash.groups'))
        ->call('restore', GroupTrash::class, 1, 'x')
        ->assertSeeText(__('shell.trash.gone'));
});

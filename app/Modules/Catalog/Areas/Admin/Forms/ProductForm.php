<?php

namespace App\Modules\Catalog\Areas\Admin\Forms;

use App\Foundation\Money\Money;
use App\Foundation\Text\Numerals;
use App\Modules\Catalog\Contracts\Enums\SaleUnit;
use App\Modules\Catalog\Database\Models\Product;
use Closure;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * A product as the drawer edits it: what it is called and where it sits, how
 * it is sold and for how much, and the other names and codes it is found by.
 */
final class ProductForm extends Form
{
    public ?int $productId = null;

    public string $name = '';

    public ?int $categoryId = null;

    public string $unit = 'piece';

    public string $price = '';

    public string $searchTerms = '';

    public string $barcode = '';

    public string $sku = '';

    /**
     * A price as typed — «12.5», «١٢٫٥٠», «.75» — or null when it is not one.
     */
    public static function money(string $typed): ?Money
    {
        $typed = trim(Numerals::toLatin($typed));

        if (str_starts_with($typed, '.')) {
            $typed = '0'.$typed;
        }

        if (preg_match('/^\d{1,9}(\.\d{1,2})?$/', $typed) !== 1) {
            return null;
        }

        return Money::fromDecimalString($typed);
    }

    /**
     * Fill the drawer from a product, or empty it for a new one, named as the
     * search was and on the shelf the list was narrowed to.
     */
    public function load(?Product $product, string $name = '', ?int $categoryId = null): void
    {
        $this->resetValidation();

        $this->productId = $product?->id;
        $this->name = $product->name ?? $name;
        $this->categoryId = $product->category_id ?? $categoryId;
        $this->unit = ($product->unit ?? SaleUnit::Piece)->value;
        $this->price = $product?->price->toDecimalString() ?? '';
        $this->searchTerms = (string) $product?->search_terms;
        $this->barcode = (string) $product?->barcode;
        $this->sku = (string) $product?->sku;
    }

    /**
     * Read what was typed before it is checked: the name trimmed, and codes
     * typed on an Arabic keyboard made Latin, so «٦٢٢» and «622» are one
     * barcode to the uniqueness rule too.
     */
    public function normalise(): void
    {
        $this->name = trim($this->name);
        $this->searchTerms = trim($this->searchTerms);
        $this->barcode = trim(Numerals::toLatin($this->barcode));
        $this->sku = trim(Numerals::toLatin($this->sku));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', $this->nameIsFree(...)],
            'categoryId' => ['required', 'integer', Rule::exists('categories', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'unit' => ['required', Rule::enum(SaleUnit::class)],
            'price' => ['required', 'string', static function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! (self::money($value)?->isPositive() ?? false)) {
                    $fail(__('catalog::module.product.price_invalid'));
                }
            }],
            'searchTerms' => ['nullable', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:32', 'regex:/^[0-9A-Za-z-]+$/', Rule::unique('products', 'barcode')->ignore($this->productId)->withoutTrashed(), $this->notInTrash('barcode')],
            'sku' => ['nullable', 'string', 'max:64', Rule::unique('products', 'sku')->ignore($this->productId)->withoutTrashed(), $this->notInTrash('sku')],
        ];
    }

    /**
     * A code a product in the trash still holds is not free — and saying so
     * points to bringing that product back, not to a code taken by nobody
     * the person can see.
     */
    private function notInTrash(string $column): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($column): void {
            $trashed = Product::onlyTrashed()->where($column, $value)->value('name');

            if ($trashed !== null) {
                $fail(__('catalog::module.product.code_in_trash', ['name' => $trashed]));
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'name' => __('catalog::module.product.name'),
            'categoryId' => __('catalog::module.product.category'),
            'unit' => __('catalog::module.product.unit'),
            'price' => __('catalog::module.product.price'),
            'searchTerms' => __('catalog::module.product.search_terms'),
            'barcode' => __('catalog::module.product.barcode'),
            'sku' => __('catalog::module.product.sku'),
        ];
    }

    /**
     * What was typed, read: an empty field is nothing.
     *
     * @return array{category_id: int, name: string, unit: SaleUnit, price: Money, search_terms: string|null, barcode: string|null, sku: string|null}
     */
    public function details(): array
    {
        $optional = static fn (string $typed): ?string => $typed === '' ? null : $typed;

        return [
            'category_id' => (int) $this->categoryId,
            'name' => $this->name,
            'unit' => SaleUnit::from($this->unit),
            'price' => self::money($this->price) ?? Money::zero(),
            'search_terms' => $optional($this->searchTerms),
            'barcode' => $optional($this->barcode),
            'sku' => $optional($this->sku),
        ];
    }

    /**
     * Two products by one name would be one line in the picker that could be
     * either. An archived one by that name is better brought back.
     */
    private function nameIsFree(string $attribute, mixed $value, Closure $fail): void
    {
        $holder = Product::query()
            ->where('name', trim((string) $value))
            ->when($this->productId !== null, fn ($query) => $query->whereKeyNot($this->productId))
            ->first(['id', 'is_active']);

        if ($holder !== null) {
            $fail($holder->is_active ? __('catalog::module.product.name_taken') : __('catalog::module.product.name_taken_archived'));
        }
    }
}

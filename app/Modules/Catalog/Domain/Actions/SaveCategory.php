<?php

namespace App\Modules\Catalog\Domain\Actions;

use App\Foundation\Audit\AuditAction;
use App\Foundation\Audit\AuditTrail;
use App\Modules\Catalog\Database\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Adds a category, or changes one: its name, its icon, and the category it
 * sits under.
 *
 * A new category, or one moved under another, goes to the end of the ones
 * beside it; moving it from there is MoveCategory's. Two levels at most is the
 * screen's rule, checked before this is asked.
 */
final class SaveCategory
{
    /** What the change log calls each field. */
    private const array LABELS = [
        'name' => 'catalog::module.category.name',
        'parent' => 'catalog::module.category.parent',
        'icon' => 'catalog::module.category.icon',
    ];

    public function __construct(private readonly AuditTrail $audit) {}

    /**
     * @param  array{name: string, parent_id: int|null, icon: string|null}  $details
     */
    public function handle(array $details, ?int $categoryId = null): Category
    {
        return DB::transaction(function () use ($details, $categoryId): Category {
            $category = $categoryId === null ? new Category : Category::query()->lockForUpdate()->findOrFail($categoryId);
            $joinsAnotherShelf = ! $category->exists || $category->parent_id !== $details['parent_id'];
            $isNew = ! $category->exists;
            $before = $isNew ? [] : self::fields($category);

            $category->fill($details);

            if (! $category->exists) {
                $category->slug = $this->slugFor($details['name']);
            }

            if ($joinsAnotherShelf) {
                $category->sort_order = $this->lastPlaceUnder($details['parent_id'], $category->getKey()) + 10;
            }

            $category->save();

            $this->audit->record(
                'catalog.category',
                $category->id,
                $category->name,
                'catalog::module.audit.category',
                $isNew ? AuditAction::Created : AuditAction::Updated,
                AuditTrail::differences($before, self::fields($category), self::LABELS, ['icon']),
            );

            return $category;
        });
    }

    /**
     * A category's fields as the change log shows them.
     *
     * @return array<string, string|null>
     */
    private static function fields(Category $category): array
    {
        return [
            'name' => $category->name,
            'parent' => $category->parent_id === null ? null : Category::query()->whereKey($category->parent_id)->value('name'),
            'icon' => $category->icon === null ? null : 'catalog::module.icons.'.$category->icon,
        ];
    }

    private function lastPlaceUnder(?int $parentId, mixed $except): int
    {
        return (int) Category::query()
            ->where('parent_id', $parentId)
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except))
            ->max('sort_order');
    }

    private function slugFor(string $name): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;

        // A slug in the trash is still taken: it comes back with its own.
        while (Category::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }
}

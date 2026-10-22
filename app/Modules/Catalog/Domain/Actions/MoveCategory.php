<?php

namespace App\Modules\Catalog\Domain\Actions;

use App\Modules\Catalog\Database\Models\Category;
use Illuminate\Support\Facades\DB;

/**
 * Moves a category one place up or down among the ones beside it: the order
 * the picker shows its tiles in, and the products screen its shelves.
 *
 * The ones beside it are numbered again in steps of ten, so two that once
 * shared a number are told apart from then on.
 */
final class MoveCategory
{
    public function handle(int $categoryId, int $step): void
    {
        DB::transaction(static function () use ($categoryId, $step): void {
            $category = Category::query()->findOrFail($categoryId);

            $shelf = Category::query()
                ->where('parent_id', $category->parent_id)
                ->inOrder()
                ->lockForUpdate()
                ->get()
                ->values();

            $from = (int) $shelf->search(static fn (Category $beside): bool => $beside->is($category));
            $to = max(0, min($shelf->count() - 1, $from + $step));

            if ($from === $to) {
                return;
            }

            $moved = $shelf->splice($from, 1)->first();
            $shelf->splice($to, 0, [$moved]);

            foreach ($shelf->values() as $index => $beside) {
                $beside->sort_order = ($index + 1) * 10;

                if ($beside->isDirty('sort_order')) {
                    $beside->save();
                }
            }
        });
    }
}

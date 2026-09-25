<?php

namespace App\Foundation\Identity\Scopes;

use App\Foundation\Area\Area;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limits a query to logins that open the admin area.
 *
 * This is what makes the two guards genuinely separate rather than two names
 * for the same thing: `AdminUser` cannot load a customer row even when asked
 * for it by primary key.
 *
 * @implements Scope<Model>
 */
class AdminsOnly implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where($model->qualifyColumn('area'), Area::Admin->value);
    }
}

<?php

namespace App\Foundation\Identity\Scopes;

use App\Foundation\Area\Area;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limits a query to logins that open the frontend.
 *
 * @implements Scope<Model>
 */
class FrontendUsersOnly implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where($model->qualifyColumn('area'), Area::Frontend->value);
    }
}

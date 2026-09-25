<?php

namespace App\Foundation\Identity\Models;

use App\Foundation\Area\Area;
use App\Foundation\Identity\Scopes\AdminsOnly;
use Database\Factories\AdminUserFactory;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Spatie\Permission\Traits\HasRoles;

/**
 * The admin guard's view of the users table.
 *
 * Backs `auth('admin')`. The global scope means a customer row cannot be loaded
 * through this model at all, so a customer's session identifier resolves to
 * null on the admin guard — the areas are separated by the query itself, not
 * only by a middleware check.
 *
 * What an administrator may *do* is a spatie role, configured in
 * `config/roles.php`. Which *area* a login opens is its `area` column. They are
 * two different questions.
 */
#[UseFactory(AdminUserFactory::class)]
#[Table('users')]
#[ScopedBy(AdminsOnly::class)]
class AdminUser extends User
{
    use HasRoles;

    /**
     * A login created through this model opens the admin area. The area is
     * never taken from input.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'area' => Area::Admin->value,
    ];

    /**
     * The guard these roles and permissions are scoped to.
     *
     * Stated rather than inferred: spatie would otherwise derive it from the
     * class name, the same trap that once gave this model its own foreign key.
     */
    public function guardName(): string
    {
        return Area::Admin->guard();
    }
}

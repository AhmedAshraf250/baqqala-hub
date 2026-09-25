<?php

namespace App\Foundation\Identity\Models;

use App\Foundation\Area\Area;
use App\Foundation\Identity\Scopes\FrontendUsersOnly;
use Database\Factories\FrontendUserFactory;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;

/**
 * The frontend guard's view of the users table.
 *
 * Backs `auth('web')`. An admin row cannot be loaded through this model, so an
 * admin session identifier resolves to null on the frontend guard.
 */
#[UseFactory(FrontendUserFactory::class)]
#[Table('users')]
#[ScopedBy(FrontendUsersOnly::class)]
class FrontendUser extends User
{
    /**
     * A login created through this model opens the frontend.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'area' => Area::Frontend->value,
    ];
}

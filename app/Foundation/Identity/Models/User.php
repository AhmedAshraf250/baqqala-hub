<?php

namespace App\Foundation\Identity\Models;

use App\Foundation\Area\Area;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * A login. Not a person's record — that belongs to whichever module deals with
 * them — only the credentials, and the one area they open.
 *
 * `area` is deliberately not fillable. It is set by the area's own model
 * (`AdminUser`, `FrontendUser`) and by nothing a request can reach, so a form
 * that forgets to strip it cannot promote anyone.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Area $area
 * @property string|null $locale
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(UserFactory::class)]
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * The foreign key other tables point at this model with.
     *
     * Laravel derives this from the class name, which would give `AdminUser`
     * and `FrontendUser` their own keys — `admin_user_id`, `frontend_user_id` —
     * for columns that are all plainly `user_id`. The three classes are one
     * table, so they must agree on one key.
     */
    public function getForeignKey(): string
    {
        return 'user_id';
    }

    /**
     * The name this model is stored under in polymorphic columns.
     *
     * Pinned for the same reason: a notification written while the row was
     * loaded as a `FrontendUser` must still be found when it is loaded as a
     * `User`.
     */
    public function getMorphClass(): string
    {
        return self::class;
    }

    /**
     * The language this login chose, which mail and notifications sent to
     * it are written in. Null until they choose, and Laravel then uses the
     * application's.
     */
    public function preferredLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * Stored lowercased, the way both sign-in screens look it up. Saved as
     * typed, `Owner@Shop.com` could never sign in on SQLite or PostgreSQL, and
     * `owner@shop.com` could be registered beside it.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(set: static fn (string $value): string => Str::lower(trim($value)));
    }

    /**
     * Get the user's initials.
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'area' => Area::class,
        ];
    }
}

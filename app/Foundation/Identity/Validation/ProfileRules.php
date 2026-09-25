<?php

namespace App\Foundation\Identity\Validation;

use App\Foundation\Identity\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * The rules a login's own details have to satisfy.
 *
 * Uniqueness is checked against `User`, not against a guard's scoped subclass:
 * two people may not share an email address even across areas, because the
 * address is what identifies the row.
 */
final class ProfileRules
{
    /**
     * The full rule set for editing a profile.
     *
     * @return array<string, list<ValidationRule|string|Rule>>
     */
    public static function forProfile(?int $ignoreUserId = null): array
    {
        return [
            'name' => self::forName(),
            'email' => self::forEmail($ignoreUserId),
        ];
    }

    /**
     * @return list<ValidationRule|string>
     */
    public static function forName(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * @return list<ValidationRule|string|Rule>
     */
    public static function forEmail(?int $ignoreUserId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $ignoreUserId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($ignoreUserId),
        ];
    }
}

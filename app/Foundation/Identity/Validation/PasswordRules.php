<?php

namespace App\Foundation\Identity\Validation;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Password;

/**
 * The rules a password has to satisfy, in one place.
 *
 * A class rather than a trait: these are a shared *definition*, not behaviour
 * mixed into the caller, and every screen that sets a password — the admin
 * area, the customer portal, a reset link — must agree on them.
 */
final class PasswordRules
{
    /**
     * Rules for choosing a new password, including its confirmation field.
     *
     * @return list<Password|ValidationRule|string>
     */
    public static function forNewPassword(): array
    {
        return ['required', 'string', Password::default(), 'confirmed'];
    }

    /**
     * The same rules as a `passwordrules` attribute, so the password a
     * password manager suggests is one the form accepts.
     */
    public static function forPasswordManager(): string
    {
        return Password::default()->toPasswordRulesString();
    }

    /**
     * Rules for re-entering the password already on the account.
     *
     * Checked against the given guard's user. The default guard is the
     * customer's, so the admin area always names its own.
     *
     * @return list<ValidationRule|string>
     */
    public static function forCurrentPassword(?string $guard = null): array
    {
        return ['required', 'string', $guard === null ? 'current_password' : 'current_password:'.$guard];
    }
}

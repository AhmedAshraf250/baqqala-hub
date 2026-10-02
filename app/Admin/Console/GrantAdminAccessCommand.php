<?php

namespace App\Admin\Console;

use App\Admin\Authorization\RoleDefinitions;
use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Identity\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Gives someone access to the admin area, with a role.
 *
 * There is no public sign-up and no admin invitation screen yet, so this is
 * how the first administrator — and any other, until that screen exists —
 * gets in. It creates the login when there is none, and changes the role of
 * one that is already an administrator.
 *
 * It refuses to convert a customer's login. A login opens exactly one area,
 * and a customer's is the key to their account in the portal; making it an
 * admin login would lock them out of their own tab. An administrator who also
 * shops here signs in to each area with its own address.
 */
class GrantAdminAccessCommand extends Command
{
    protected $signature = 'app:admin:grant
                            {email? : The address the administrator signs in with}
                            {--name= : Their name, when the login is new}
                            {--role= : The role to give them}';

    protected $description = 'Create an administrator, or change an administrator\'s role';

    public function handle(RoleDefinitions $roles): int
    {
        $email = $this->stringArgument('email') ?? text(
            label: 'Which email address will they sign in with?',
            placeholder: 'someone@example.com',
            required: true,
            validate: static fn (string $value): ?string => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'That is not an email address.',
        );

        // Stored lowercased (see User), so looked up lowercased.
        $email = Str::lower(trim($email));

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null && $existing->area !== Area::Admin) {
            $this->components->error("[{$email}] is a customer login. A login opens one area — give the administrator an address of their own.");

            return self::FAILURE;
        }

        $role = $this->stringOption('role') ?? select(
            label: 'Which role?',
            options: array_keys($roles->patterns()),
            default: $roles->unrestricted(),
        );

        if (! array_key_exists($role, $roles->patterns())) {
            $this->components->error("[{$role}] is not a role. Roles are defined in config/roles.php.");

            return self::FAILURE;
        }

        $admin = $existing === null
            ? $this->createAdministrator($email)
            : AdminUser::query()->whereKey($existing->getKey())->firstOrFail();

        $this->ensureRolesExist($role);
        $admin->syncRoles($role);

        $this->components->info("[{$email}] can sign in at /admin/login as [{$role}].");

        return self::SUCCESS;
    }

    private function createAdministrator(string $email): AdminUser
    {
        $name = $this->stringOption('name') ?? text(label: 'Their name', required: true);
        $password = password(label: 'Choose a password for them', required: true);

        $admin = new AdminUser(['name' => $name, 'email' => $email, 'password' => $password]);

        // Created by whoever runs the console, so there is no address to prove.
        $admin->forceFill(['email_verified_at' => now()])->save();

        return $admin;
    }

    /**
     * Seed the roles on first use, so a fresh installation needs one command.
     */
    private function ensureRolesExist(string $role): void
    {
        if (Role::query()->where('name', $role)->where('guard_name', Area::Admin->guard())->doesntExist()) {
            $this->callSilently('db:seed', ['--class' => AuthorizationSeeder::class, '--force' => true]);
        }
    }

    private function stringArgument(string $name): ?string
    {
        $value = $this->argument($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}

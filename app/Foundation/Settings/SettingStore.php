<?php

namespace App\Foundation\Settings;

use Illuminate\Container\Attributes\Scoped;
use Illuminate\Support\Facades\DB;

/**
 * The settings an owner changes from the panel, layered over configuration.
 *
 * Configuration (`config/*.php`, `.env`) says what an installation starts
 * with; this says what the owner changed it to since. A layer that offers a
 * setting reads it here with its configured value as the default, so an
 * installation that never opened the settings screen behaves exactly as its
 * configuration says.
 *
 * Read once per request, and only when something asks: booting the
 * application issues no query. One instance per request, so a value saved in
 * this request is what the rest of it reads, and the next request starts from
 * the table again.
 */
#[Scoped]
final class SettingStore
{
    private const string TABLE = 'settings';

    /**
     * Every stored value, keyed by setting, once something asked for one.
     *
     * @var array<string, mixed>|null
     */
    private ?array $values = null;

    /**
     * The stored value, or the default when the owner has not set one.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->all()) ? $this->all()[$key] : $default;
    }

    /**
     * A declared setting's value, read as its type: what the owner set, or
     * what the declaration says it is until then.
     */
    public function value(SettingDefinition $setting): mixed
    {
        return $setting->cast($this->get($setting->key, $setting->default));
    }

    /**
     * Whether the owner has set this, rather than left it at its default.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    /**
     * Store a value, recording who changed it.
     */
    public function put(string $key, mixed $value, ?int $updatedBy = null): void
    {
        DB::table(self::TABLE)->updateOrInsert(
            ['key' => $key],
            ['value' => json_encode($value, JSON_THROW_ON_ERROR), 'updated_by' => $updatedBy, 'updated_at' => now()],
        );

        $this->values = [...$this->all(), $key => $value];
    }

    /**
     * @return array<string, mixed>
     */
    private function all(): array
    {
        return $this->values ??= DB::table(self::TABLE)
            ->pluck('value', 'key')
            ->map(static fn (string $value): mixed => json_decode($value, true, flags: JSON_THROW_ON_ERROR))
            ->all();
    }
}

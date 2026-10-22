<?php

namespace App\Modules\Notebook\Database\Factories;

use App\Modules\Accounts\Contracts\Enums\EntryKind;
use App\Modules\Notebook\Database\Models\Note;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Note>
 */
class NoteFactory extends Factory
{
    protected $model = Note::class;

    /**
     * Define the model's default state: a charge, jotted in words.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => EntryKind::Charge,
            'body' => fake()->firstName().' '.fake()->word().' '.fake()->numberBetween(5, 500),
            'status' => 'pending',
            'operation_id' => (string) Str::uuid(),
        ];
    }

    /**
     * Money handed over, jotted to post later.
     */
    public function payment(): static
    {
        return $this->state(fn (array $attributes) => ['kind' => EntryKind::Payment]);
    }

    /**
     * Said, not written: a clip on the private disk.
     */
    public function voice(string $path = 'notebook/test.webm'): static
    {
        return $this->state(fn (array $attributes) => [
            'body' => null,
            'voice_path' => $path,
            'voice_type' => 'audio/webm',
            'voice_seconds' => 4,
        ]);
    }
}

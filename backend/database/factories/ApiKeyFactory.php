<?php

namespace Database\Factories;

use App\Modules\Agent\Infrastructure\Persistence\Agent;
use App\Modules\Authentication\ApiKey\Domain\ApiKeyStatus;
use App\Modules\Authentication\ApiKey\Infrastructure\Persistence\ApiKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiKey>
 */
class ApiKeyFactory extends Factory
{
    protected $model = ApiKey::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $secret = Str::random(32);

        return [
            'agent_id' => Agent::factory(),
            'key_prefix' => 'sk_live_'.substr($secret, 0, 4),
            'key_hash' => hash('sha256', $secret),
            'status' => ApiKeyStatus::Active,
            'last_used_at' => fake()->optional()->dateTimeBetween('-1 week'),
            'expires_at' => null,
        ];
    }

    /**
     * Indicate that the key has been revoked.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApiKeyStatus::Revoked,
        ]);
    }
}

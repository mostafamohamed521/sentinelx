<?php

namespace Database\Factories;

use App\Modules\Audit\Domain\ActorType;
use App\Modules\Audit\Infrastructure\Persistence\AuditLog;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use App\Modules\Organization\Infrastructure\Persistence\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'actor_type' => ActorType::User,
            'actor_id' => User::factory(),
            'action' => 'agent.created',
            'resource_type' => 'Agent',
            'resource_id' => (string) Str::uuid(),
            'metadata' => [],
            'created_at' => now(),
        ];
    }

    /**
     * Indicate the entry was recorded for a system-initiated action, with
     * no Human actor.
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'actor_type' => ActorType::System,
            'actor_id' => null,
        ]);
    }
}

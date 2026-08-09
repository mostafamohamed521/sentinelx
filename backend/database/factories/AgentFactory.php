<?php

namespace Database\Factories;

use App\Modules\Agent\Domain\AgentStatus;
use App\Modules\Agent\Infrastructure\Persistence\Agent;
use App\Modules\Organization\Infrastructure\Persistence\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agent>
 */
class AgentFactory extends Factory
{
    protected $model = Agent::class;

    /**
     * Frameworks observed in the ASES Context — see docs/03-specifications.
     *
     * @var array<int, string>
     */
    private const FRAMEWORKS = ['CrewAI', 'LangGraph', 'OpenAI Agents SDK', 'AutoGen'];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->unique()->words(3, true).' Agent',
            'framework' => fake()->randomElement(self::FRAMEWORKS),
            'framework_version' => fake()->numerify('#.#.#'),
            'description' => fake()->optional()->sentence(),
            'status' => AgentStatus::Active,
            'last_seen_at' => fake()->optional()->dateTimeBetween('-1 week'),
        ];
    }

    /**
     * Indicate that the agent has been archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AgentStatus::Archived,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Modules\Agent\Infrastructure\Persistence\Agent;
use App\Modules\Observation\Domain\AnalysisStatus;
use App\Modules\Observation\Infrastructure\Persistence\Observation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Observation>
 */
class ObservationFactory extends Factory
{
    protected $model = Observation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $receivedAt = fake()->dateTimeBetween('-1 month');

        return [
            'agent_id' => Agent::factory(),
            // Denormalized to match the owning Agent's organization (ADR-005) —
            // never sourced independently, even in tests.
            'organization_id' => fn (array $attributes) => Agent::find($attributes['agent_id'])->organization_id,
            'analysis_status' => AnalysisStatus::Pending,
            'raw_ases_json' => $this->fakeAsesPayload(),
            'received_at' => $receivedAt,
            'processing_started_at' => null,
            'processed_at' => null,
        ];
    }

    /**
     * A minimal but structurally faithful ASES payload — Context, Events,
     * Metadata — per docs/03-specifications/03-ASES_JSON_SCHEMA.md.
     *
     * @return array<string, mixed>
     */
    private function fakeAsesPayload(): array
    {
        $start = fake()->dateTimeBetween('-1 hour');

        return [
            'context' => [
                'framework' => fake()->randomElement(['CrewAI', 'LangGraph', 'OpenAI Agents SDK', 'AutoGen']),
                'agent_version' => fake()->numerify('#.#.#'),
                'environment' => fake()->randomElement(['production', 'staging', 'development']),
                'execution_started_at' => $start->format(DATE_ATOM),
                'execution_finished_at' => fake()->dateTimeBetween($start)->format(DATE_ATOM),
            ],
            'events' => [
                [
                    'header' => ['type' => 'FILE_ACCESS', 'sequence' => 1],
                    'payload' => ['path' => fake()->filePath()],
                ],
                [
                    'header' => ['type' => 'API_CALL', 'sequence' => 2],
                    'payload' => ['url' => fake()->url(), 'method' => 'GET'],
                ],
            ],
            'metadata' => [
                'spec_version' => '1.0',
                'sdk_version' => fake()->numerify('#.#.#'),
                'generated_at' => now()->toAtomString(),
            ],
        ];
    }

    /**
     * Indicate the observation has completed ML processing.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'analysis_status' => AnalysisStatus::Completed,
            'processing_started_at' => fake()->dateTimeBetween($attributes['received_at'] ?? '-1 month'),
            'processed_at' => now(),
        ]);
    }
}

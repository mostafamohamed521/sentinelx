<?php

namespace Database\Factories;

use App\Modules\Analysis\Domain\Verdict;
use App\Modules\Analysis\Infrastructure\Persistence\Prediction;
use App\Modules\Observation\Infrastructure\Persistence\Observation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prediction>
 */
class PredictionFactory extends Factory
{
    protected $model = Prediction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $verdict = fake()->randomElement(Verdict::cases());
        $riskScore = match ($verdict) {
            Verdict::Safe => fake()->numberBetween(0, 29),
            Verdict::Suspicious => fake()->numberBetween(30, 69),
            Verdict::Malicious => fake()->numberBetween(70, 100),
        };

        return [
            'observation_id' => Observation::factory(),
            'verdict' => $verdict,
            'confidence' => fake()->randomFloat(2, 0.5, 1),
            'risk_score' => $riskScore,
            'summary' => fake()->sentence(),
            'model_version' => fake()->numerify('sentinelx-ml-#.#.#'),
            'prediction_json' => [
                'verdict' => $verdict->value,
                'risk_score' => $riskScore,
                'reasons' => [fake()->sentence()],
                'evidence' => [
                    ['type' => 'event_reference', 'sequence' => 1],
                ],
                'mitre_attack' => [],
                'owasp' => [],
            ],
            'analyzed_at' => now(),
        ];
    }

    /**
     * Indicate the prediction found the observation safe.
     */
    public function safe(): static
    {
        return $this->state(fn (array $attributes) => [
            'verdict' => Verdict::Safe,
            'risk_score' => fake()->numberBetween(0, 29),
        ]);
    }

    /**
     * Indicate the prediction found the observation malicious.
     */
    public function malicious(): static
    {
        return $this->state(fn (array $attributes) => [
            'verdict' => Verdict::Malicious,
            'risk_score' => fake()->numberBetween(70, 100),
        ]);
    }
}

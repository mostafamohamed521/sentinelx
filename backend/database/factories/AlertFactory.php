<?php

namespace Database\Factories;

use App\Modules\Alert\Domain\AlertStatus;
use App\Modules\Alert\Domain\Severity;
use App\Modules\Alert\Infrastructure\Persistence\Alert;
use App\Modules\Analysis\Infrastructure\Persistence\Prediction;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alert>
 */
class AlertFactory extends Factory
{
    protected $model = Alert::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'prediction_id' => Prediction::factory()->malicious(),
            'severity' => fake()->randomElement(Severity::cases()),
            'status' => AlertStatus::Open,
            'acknowledged_at' => null,
            'acknowledged_by' => null,
            'resolved_at' => null,
            'resolved_by' => null,
        ];
    }

    /**
     * Indicate the alert has been acknowledged.
     */
    public function acknowledged(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AlertStatus::Acknowledged,
            'acknowledged_at' => now(),
            'acknowledged_by' => User::factory(),
        ]);
    }

    /**
     * Indicate the alert has been resolved.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AlertStatus::Resolved,
            'acknowledged_at' => now()->subHour(),
            'acknowledged_by' => User::factory(),
            'resolved_at' => now(),
            'resolved_by' => User::factory(),
        ]);
    }
}

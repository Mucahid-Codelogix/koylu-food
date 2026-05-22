<?php

namespace Database\Factories;

use App\Enums\RouteStatus;
use App\Models\Route;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Route>
 */
class RouteFactory extends Factory
{
    protected $model = Route::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'driver_id' => User::factory(),
            'vehicle_id' => Vehicle::factory(),
            'route_date' => today(),
            'status' => RouteStatus::PLANNED,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn () => [
            'status' => RouteStatus::IN_PROGRESS,
            'started_at' => now(),
        ]);
    }

    public function loadingCompleted(): static
    {
        return $this->inProgress()->state(fn () => [
            'loading_completed_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => RouteStatus::COMPLETED,
            'started_at' => now()->subHours(4),
            'loading_completed_at' => now()->subHours(3),
            'completed_at' => now(),
        ]);
    }
}

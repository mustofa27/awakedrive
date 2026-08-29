<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DriverStatus;
use App\Models\DeviceAlert;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceAlert>
 */
class DeviceAlertFactory extends Factory
{
    protected $model = DeviceAlert::class;

    public function definition(): array
    {
        return [
            'device_id' => DeviceFactory::new(),
            'driver_status' => fake()->randomElement([DriverStatus::DROWSY->value, DriverStatus::MICROSLEEP->value]),
            'latitude' => fake()->latitude(-10, 10),
            'longitude' => fake()->longitude(90, 130),
            'triggered_at' => now()->subMinutes(fake()->numberBetween(1, 30)),
            'acknowledged_at' => null,
            'acknowledged_by' => null,
        ];
    }
}

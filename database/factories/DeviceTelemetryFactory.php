<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DriverStatus;
use App\Models\DeviceTelemetry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceTelemetry>
 */
class DeviceTelemetryFactory extends Factory
{
    protected $model = DeviceTelemetry::class;

    public function definition(): array
    {
        return [
            'device_id' => DeviceFactory::new(),
            'driver_status' => fake()->randomElement(DriverStatus::cases())->value,
            'latitude' => fake()->latitude(-10, 10),
            'longitude' => fake()->longitude(90, 130),
            'recorded_at' => now()->subMinutes(fake()->numberBetween(1, 60)),
        ];
    }
}

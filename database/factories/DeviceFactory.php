<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DeviceStatus;
use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    protected $model = Device::class;

    public function definition(): array
    {
        return [
            'company_id' => CompanyFactory::new(),
            'device_uid' => 'DMS-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => fake()->word().' Sensor',
            'driver_name' => fake()->name(),
            'vehicle_plate' => fake()->bothify('ABC-###'),
            'status' => DeviceStatus::ACTIVE->value,
            'last_seen_at' => now(),
        ];
    }
}

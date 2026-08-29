<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\DriverStatus;
use App\Models\Company;
use App\Models\Device;
use App\Models\DeviceAlert;
use App\Models\DeviceTelemetry;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DemoFleetSeeder extends Seeder
{
    public function run(): void
    {
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);
        $companyAdminRole = Role::firstOrCreate(['name' => 'company_admin']);
        $companyOperatorRole = Role::firstOrCreate(['name' => 'company_operator']);

        $superAdmin = User::firstOrCreate(
            ['email' => 'mustofaahmad@poltera.ac.id'],
            [
                'name' => 'Mustofa Ahmad',
                'password' => Hash::make('ZXCasd123!@#'),
                'company_id' => null,
            ]
        );
        $superAdmin->syncRoles([$superAdminRole]);

        $legacyAdmin = User::firstOrCreate(
            ['email' => 'admin@awakedrive.test'],
            [
                'name' => 'Platform Admin',
                'password' => Hash::make('password'),
                'company_id' => null,
            ]
        );
        $legacyAdmin->syncRoles([$superAdminRole]);

        $companies = [
            ['name' => 'Aurora Logistics', 'address' => 'Jakarta', 'phone' => '+62 21 1234'],
            ['name' => 'BlueRoute Freight', 'address' => 'Bandung', 'phone' => '+62 22 5678'],
            ['name' => 'Summit Cargo', 'address' => 'Surabaya', 'phone' => '+62 31 9876'],
        ];

        foreach ($companies as $index => $companyData) {
            $company = Company::firstOrCreate(['name' => $companyData['name']], $companyData + ['is_active' => true]);

            $admin = User::firstOrCreate(
                ['email' => 'admin'.$index.'@'.$company->id.'.test'],
                [
                    'name' => $company->name.' Admin',
                    'password' => Hash::make('password'),
                    'company_id' => $company->id,
                ]
            );
            $admin->syncRoles([$companyAdminRole]);

            $operator = User::firstOrCreate(
                ['email' => 'operator'.$index.'@'.$company->id.'.test'],
                [
                    'name' => $company->name.' Operator',
                    'password' => Hash::make('password'),
                    'company_id' => $company->id,
                ]
            );
            $operator->syncRoles([$companyOperatorRole]);

            foreach (range(1, 3) as $driverNumber) {
                $driver = Driver::firstOrCreate(
                    ['company_id' => $company->id, 'license_number' => 'LIC-'.($index + 1).'-'.$driverNumber],
                    [
                        'name' => 'Driver '.$driverNumber,
                        'phone' => '+62 812 '.($index + 1).'00'.$driverNumber,
                        'is_active' => true,
                    ],
                );

                Device::query()
                    ->where('company_id', $company->id)
                    ->where('driver_name', $driver->name)
                    ->whereNull('driver_id')
                    ->update(['driver_id' => $driver->id]);
            }

            foreach (range(1, 3) as $deviceIndex) {
                $driver = Driver::query()
                    ->where('company_id', $company->id)
                    ->where('license_number', 'LIC-'.($index + 1).'-'.$deviceIndex)
                    ->firstOrFail();
                $uid = sprintf('DMS-%02d-%03d', $index + 1, $deviceIndex);
                $device = Device::firstOrCreate(
                    ['device_uid' => $uid],
                    [
                        'company_id' => $company->id,
                        'driver_id' => $driver->id,
                        'name' => 'Vehicle '.$deviceIndex,
                        'driver_name' => $driver->name,
                        'vehicle_plate' => 'B '.($index + 1).' '.$deviceIndex,
                        'status' => 'active',
                        'last_seen_at' => now()->subMinutes($deviceIndex),
                    ]
                );

                foreach (range(1, 10) as $telemetryIndex) {
                    $status = fake()->randomElement(DriverStatus::cases());
                    $timestamp = now()->subMinutes($telemetryIndex * 5);

                    $telemetry = DeviceTelemetry::create([
                        'device_id' => $device->id,
                        'driver_status' => $status,
                        'latitude' => -6.2 + ($deviceIndex * 0.03),
                        'longitude' => 106.8 + ($telemetryIndex * 0.02),
                        'recorded_at' => $timestamp,
                    ]);

                    if (in_array($status, [DriverStatus::DROWSY, DriverStatus::MICROSLEEP], true)) {
                        DeviceAlert::firstOrCreate([
                            'device_id' => $device->id,
                            'triggered_at' => $timestamp,
                        ], [
                            'driver_status' => $status,
                            'latitude' => $telemetry->latitude,
                            'longitude' => $telemetry->longitude,
                            'acknowledged_at' => null,
                            'acknowledged_by' => null,
                        ]);
                    }
                }

                $device->refresh();
                $latest = $device->telemetry()->latest('recorded_at')->first();
                if ($latest) {
                    $device->last_seen_at = $latest->recorded_at;
                    $device->status = 'active';
                    $device->save();
                }

                Trip::query()->updateOrCreate(
                    ['device_id' => $device->id, 'status' => 'active'],
                    [
                        'company_id' => $company->id,
                        'driver_id' => $driver->id,
                        'start_latitude' => -6.2 + ($deviceIndex * 0.03),
                        'start_longitude' => 106.8,
                        'finish_latitude' => -6.1 + ($deviceIndex * 0.03),
                        'finish_longitude' => 106.95,
                        'completion_radius_meters' => 150,
                        'started_at' => now()->subMinutes(50),
                    ],
                );
            }
        }
    }
}

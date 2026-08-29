<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DriverStatus;
use App\Filament\Pages\Dashboard;
use App\Models\Company;
use App\Models\Device;
use App\Models\DeviceAlert;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\User;
use App\Services\TelemetryIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TelemetryProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_processes_valid_mqtt_payload_and_creates_alerts(): void
    {
        $company = Company::factory()->create();
        $device = Device::factory()->for($company)->create([
            'device_uid' => 'DMS-0042',
            'status' => 'active',
            'driver_name' => 'Ana Driver',
        ]);

        $service = app(TelemetryIngestionService::class);
        $telemetry = $service->processPayload([
            'device_id' => 'DMS-0042',
            'driver_status' => 'microsleep',
            'latitude' => -6.914744,
            'longitude' => 107.609810,
            'timestamp' => '2026-08-29T14:32:05Z',
        ]);

        $this->assertNotNull($telemetry);
        $this->assertSame(DriverStatus::MICROSLEEP->value, $telemetry->driver_status->value);
        $this->assertDatabaseHas('device_telemetry', [
            'device_id' => $device->id,
            'driver_status' => DriverStatus::MICROSLEEP->value,
        ]);
        $this->assertDatabaseHas('device_alerts', [
            'device_id' => $device->id,
            'driver_status' => DriverStatus::MICROSLEEP->value,
        ]);
    }

    public function test_it_ignores_unknown_device_payloads(): void
    {
        $service = app(TelemetryIngestionService::class);

        $this->assertNull($service->processPayload([
            'device_id' => 'MISSING-DEVICE',
            'driver_status' => 'normal',
            'latitude' => -6.0,
            'longitude' => 107.0,
            'timestamp' => '2026-08-29T14:32:05Z',
        ]));
    }

    public function test_company_users_cannot_access_other_company_devices(): void
    {
        $companyA = Company::factory()->create(['name' => 'Company A']);
        $companyB = Company::factory()->create(['name' => 'Company B']);
        $device = Device::factory()->for($companyB)->create(['device_uid' => 'B-01']);
        $user = User::factory()->for($companyA)->create();

        $this->actingAs($user);

        $this->assertFalse($user->can('view', $device));
    }

    public function test_company_operator_cannot_access_companies_or_users_but_can_view_alerts(): void
    {
        $company = Company::factory()->create();
        $operator = User::factory()->for($company)->create();
        $operator->syncRoles([Role::firstOrCreate(['name' => 'company_operator'])]);

        $this->assertFalse($operator->can('viewAny', Company::class));
        $this->assertFalse($operator->can('viewAny', User::class));
        $this->assertTrue($operator->can('viewAny', DeviceAlert::class));
    }

    public function test_fleet_resources_are_available_for_filament_admin(): void
    {
        $this->assertTrue(class_exists(\App\Filament\Resources\CompanyResource::class));
        $this->assertTrue(class_exists(\App\Filament\Resources\DeviceResource::class));
        $this->assertTrue(class_exists(\App\Filament\Resources\UserResource::class));
        $this->assertTrue(class_exists(\App\Filament\Resources\DeviceAlertResource::class));
        $this->assertTrue(class_exists(\App\Filament\Resources\DriverResource::class));
    }

    public function test_device_can_be_reassigned_only_to_a_driver_in_its_company(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $firstDriver = Driver::query()->create(['company_id' => $company->id, 'name' => 'First Driver']);
        $replacementDriver = Driver::query()->create(['company_id' => $company->id, 'name' => 'Replacement Driver']);
        $otherCompanyDriver = Driver::query()->create(['company_id' => $otherCompany->id, 'name' => 'Other Company Driver']);
        $device = Device::factory()->for($company)->create(['driver_id' => $firstDriver->id]);

        $device->update(['driver_id' => $replacementDriver->id]);

        $this->assertSame($replacementDriver->id, $device->fresh()->driver_id);
        $this->assertSame('Replacement Driver', $device->fresh()->driver_name);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $device->update(['driver_id' => $otherCompanyDriver->id]);
    }

    public function test_telemetry_completes_an_active_trip_at_its_finish_coordinate(): void
    {
        $company = Company::factory()->create();
        $driver = Driver::query()->create(['company_id' => $company->id, 'name' => 'Trip Driver']);
        $device = Device::factory()->for($company)->create(['device_uid' => 'DMS-TRIP', 'driver_id' => $driver->id]);
        $trip = Trip::query()->create([
            'company_id' => $company->id,
            'device_id' => $device->id,
            'driver_id' => $driver->id,
            'status' => 'active',
            'start_latitude' => -6.2,
            'start_longitude' => 106.8,
            'finish_latitude' => -6.9,
            'finish_longitude' => 107.6,
            'completion_radius_meters' => 150,
            'started_at' => now(),
        ]);

        app(TelemetryIngestionService::class)->processPayload([
            'device_id' => 'DMS-TRIP', 'driver_status' => 'normal', 'latitude' => -6.9, 'longitude' => 107.6, 'timestamp' => now()->toIso8601String(),
        ]);

        $this->assertSame('completed', $trip->fresh()->status->value);
        $this->assertNotNull($trip->fresh()->completed_at);
    }

    public function test_active_trip_is_parked_when_telemetry_is_stale_for_fifteen_minutes(): void
    {
        $company = Company::factory()->create();
        $driver = Driver::query()->create(['company_id' => $company->id, 'name' => 'Parked Driver']);
        $device = Device::factory()->for($company)->create(['driver_id' => $driver->id, 'last_seen_at' => now()->subMinutes(16)]);
        $trip = Trip::query()->create([
            'company_id' => $company->id, 'device_id' => $device->id, 'driver_id' => $driver->id, 'status' => 'active',
            'start_latitude' => -6.2, 'start_longitude' => 106.8, 'finish_latitude' => -6.9, 'finish_longitude' => 107.6, 'started_at' => now(),
        ]);

        $this->artisan('devices:mark-offline', ['--minutes' => 15])->assertSuccessful();

        $this->assertSame('parked', $trip->fresh()->status->value);
        $this->assertNotNull($trip->fresh()->parked_at);
        $this->assertSame('inactive', $device->fresh()->status->value);
    }

    public function test_devices_can_be_marked_offline_when_stale(): void
    {
        $company = Company::factory()->create();
        $device = Device::factory()->for($company)->create([
            'device_uid' => 'DMS-STALE',
            'status' => 'active',
            'last_seen_at' => now()->subMinutes(20),
        ]);

        $this->artisan('devices:mark-offline', ['--minutes' => 10])->assertSuccessful();

        $this->assertDatabaseHas('devices', [
            'id' => $device->id,
            'status' => 'offline',
        ]);
    }

    public function test_database_seed_creates_demo_fleet_data(): void
    {
        $this->artisan('db:seed', ['--class' => 'DemoFleetSeeder'])->assertSuccessful();

        $this->assertGreaterThanOrEqual(3, \App\Models\Company::query()->count());
        $this->assertGreaterThanOrEqual(3, \App\Models\User::query()->whereNotNull('company_id')->count());
    }

    public function test_seeded_super_admin_uses_requested_credentials(): void
    {
        $this->artisan('db:seed', ['--class' => 'DemoFleetSeeder'])->assertSuccessful();

        $user = User::query()->where('email', 'mustofaahmad@poltera.ac.id')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('super_admin'));
        $this->assertTrue(Hash::check('ZXCasd123!@#', $user->password));
    }

    public function test_super_admin_dashboard_aggregates_system_wide_stats(): void
    {
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);
        $companyA = Company::factory()->create(['name' => 'Company A']);
        $companyB = Company::factory()->create(['name' => 'Company B']);
        $superAdmin = User::factory()->create(['email' => 'super-admin@example.com', 'company_id' => null]);
        $superAdmin->syncRoles([$superAdminRole]);

        Device::factory()->count(2)->for($companyA)->create([
            'status' => 'active',
            'last_seen_at' => now(),
        ]);
        Device::factory()->for($companyB)->create([
            'status' => 'offline',
            'last_seen_at' => now()->subHours(2),
        ]);

        User::factory()->count(3)->for($companyA)->create();
        DeviceAlert::factory()->create([
            'device_id' => Device::query()->first()->id,
            'driver_status' => DriverStatus::DROWSY,
            'triggered_at' => now(),
        ]);

        $this->actingAs($superAdmin);

        $stats = (new Dashboard())->resolveStats();

        $this->assertSame(2, $stats['total_companies']);
        $this->assertSame(3, $stats['total_devices']);
        $this->assertSame(4, $stats['total_users']);
        $this->assertSame(2, $stats['active_now']);
        $this->assertSame(1, $stats['alerts_today']);
        $this->assertSame(1, $stats['offline_devices']);
    }

    public function test_root_route_displays_the_awakedrive_landing_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Drive awake.')
            ->assertSee('Open control center');
    }
}

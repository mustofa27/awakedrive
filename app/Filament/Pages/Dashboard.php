<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Company;
use App\Models\Device;
use App\Models\DeviceAlert;
use App\Models\Trip;
use App\Models\User;
use Filament\Pages\Page;

class Dashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = -2;

    protected static string $view = 'filament.pages.dashboard';

    public array $stats = [];

    public array $mapDevices = [];

    public function mount(): void
    {
        $this->stats = $this->resolveStats();
        $this->mapDevices = $this->resolveMapDevices();
    }

    public function resolveStats(): array
    {
        $query = Device::query()->with('latestTelemetry');
        $user = auth()->user();

        if ($user && $user->company_id) {
            $query->where('company_id', $user->company_id);
        }

        $devices = $query->get();
        $companyQuery = Company::query();
        $userQuery = User::query();

        if ($user && $user->company_id) {
            $companyQuery->where('id', $user->company_id);
            $userQuery->where('company_id', $user->company_id);
        }

        $todayAlerts = DeviceAlert::query()->when($user && $user->company_id, fn ($query) => $query->whereHas('device', fn ($deviceQuery) => $deviceQuery->where('company_id', $user->company_id)))->whereDate('triggered_at', today())->count();

        $isSuperAdmin = $user && $user->hasRole('super_admin');

        return [
            'total_companies' => $isSuperAdmin ? $companyQuery->count() : ($user?->company_id ? 1 : 0),
            'total_devices' => $devices->count(),
            'total_users' => $isSuperAdmin ? $userQuery->count() : ($user?->company_id ? $userQuery->where('company_id', $user->company_id)->count() : 0),
            'active_now' => $devices->where('status', 'active')->count(),
            'alerts_today' => $todayAlerts,
            'offline_devices' => $devices->where('status', 'offline')->count(),
        ];
    }

    public function resolveMapDevices(): array
    {
        $query = Trip::query()->where('status', 'active')->with(['device.latestTelemetry', 'company', 'driver']);
        $user = auth()->user();

        if ($user && $user->company_id) {
            $query->where('company_id', $user->company_id);
        }

        return $query->get()->map(function (Trip $trip): array {
            $device = $trip->device;
            $telemetry = $device->latestTelemetry;
            $driverStatus = $telemetry?->driver_status;

            return [
                'id' => $trip->id,
                'name' => $device->name ?: $device->device_uid,
                'device_uid' => $device->device_uid,
                'vehicle_plate' => $device->vehicle_plate,
                'status' => $device->status->value,
                'driver_name' => $trip->driver->name,
                'company' => $trip->company->name,
                'driver_status' => $driverStatus?->value ?? 'offline',
                'driver_status_label' => $driverStatus?->label() ?? 'Offline',
                'latitude' => $telemetry?->latitude,
                'longitude' => $telemetry?->longitude,
                'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            ];
        })->filter(fn (array $device) => is_numeric($device['latitude'] ?? null) && is_numeric($device['longitude'] ?? null))->values()->toArray();
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\DriverStatus;
use App\Events\DeviceTelemetryUpdated;
use App\Models\Device;
use App\Models\Trip;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MarkDevicesOffline extends Command
{
    protected $signature = 'devices:mark-offline {--minutes=15 : Minutes before a device is marked offline or an active trip is parked}';

    protected $description = 'Mark devices offline if they have not reported telemetry recently.';

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $threshold = now()->subMinutes($minutes);

        Trip::query()
            ->where('status', 'active')
            ->whereHas('device', fn ($query) => $query->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $threshold))
            ->each(function (Trip $trip): void {
                $trip->update(['status' => 'parked', 'parked_at' => now()]);
                $trip->device->update(['status' => 'inactive']);
            });

        Device::query()
            ->where(function ($query) use ($threshold) {
                $query->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $threshold);
            })
            ->whereDoesntHave('trips', fn ($query) => $query->whereIn('status', ['active', 'parked']))
            ->where('status', '!=', 'offline')
            ->each(function (Device $device): void {
                $device->status = 'offline';
                $device->save();

                $device->refresh();
                Log::info('Device marked offline by scheduler', ['device_id' => $device->id]);
                event(new DeviceTelemetryUpdated($device, $device->latestTelemetry()->first() ?? $device->telemetry()->latest('recorded_at')->first()));
            });

        return self::SUCCESS;
    }
}

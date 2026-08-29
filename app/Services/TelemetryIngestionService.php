<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DeviceStatus;
use App\Enums\DriverStatus;
use App\Events\DeviceTelemetryUpdated;
use App\Jobs\ProcessDeviceTelemetry;
use App\Models\Device;
use App\Models\DeviceTelemetry;
use App\Models\Trip;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class TelemetryIngestionService
{
    public function processPayload(array $payload): ?DeviceTelemetry
    {
        $deviceId = $payload['device_id'] ?? null;
        $driverStatus = $payload['driver_status'] ?? null;
        $latitude = $payload['latitude'] ?? null;
        $longitude = $payload['longitude'] ?? null;
        $timestamp = $payload['timestamp'] ?? null;

        if (! is_string($deviceId) || ! is_string($driverStatus) || ! is_numeric($latitude) || ! is_numeric($longitude) || ! is_string($timestamp)) {
            Log::channel('mqtt')->warning('Malformed MQTT telemetry payload', ['payload' => $payload]);

            return null;
        }

        $status = DriverStatus::fromValue($driverStatus);
        if ($status === null) {
            Log::channel('mqtt')->warning('Unknown driver status payload', ['payload' => $payload]);

            return null;
        }

        $device = Device::query()->where('device_uid', $deviceId)->first();
        if (! $device) {
            Log::channel('mqtt')->warning('Unknown MQTT device id', ['device_id' => $deviceId]);

            return null;
        }

        $recordedAt = CarbonImmutable::parse($timestamp);

        $telemetry = DeviceTelemetry::query()->create([
            'device_id' => $device->id,
            'driver_status' => $status,
            'latitude' => (float) $latitude,
            'longitude' => (float) $longitude,
            'recorded_at' => $recordedAt,
        ]);

        $device->last_seen_at = $recordedAt;
        $device->status = DeviceStatus::fromDriverStatus($status)->value;
        $device->save();

        $trip = Trip::query()->where('device_id', $device->id)->where('status', 'active')->latest('started_at')->first();
        if ($trip && $trip->isAtDestination((float) $latitude, (float) $longitude)) {
            $trip->update([
                'status' => 'completed',
                'completed_at' => $recordedAt,
            ]);
        }

        if ($status === DriverStatus::DROWSY || $status === DriverStatus::MICROSLEEP) {
            $device->alerts()->firstOrCreate([
                'device_id' => $device->id,
                'driver_status' => $status,
                'latitude' => (float) $latitude,
                'longitude' => (float) $longitude,
                'triggered_at' => $recordedAt,
            ]);
        }

        event(new DeviceTelemetryUpdated($device, $telemetry));

        return $telemetry;
    }

    public function dispatchFromMqtt(array $payload): void
    {
        ProcessDeviceTelemetry::dispatch($payload);
    }
}

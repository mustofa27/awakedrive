<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TripStatus;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Trip extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'device_id', 'driver_id', 'status', 'start_latitude', 'start_longitude',
        'finish_latitude', 'finish_longitude', 'completion_radius_meters', 'started_at', 'completed_at', 'parked_at',
    ];

    protected $casts = [
        'status' => TripStatus::class,
        'start_latitude' => 'float',
        'start_longitude' => 'float',
        'finish_latitude' => 'float',
        'finish_longitude' => 'float',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'parked_at' => 'datetime',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function device(): BelongsTo { return $this->belongsTo(Device::class); }
    public function driver(): BelongsTo { return $this->belongsTo(Driver::class); }

    public function isAtDestination(float $latitude, float $longitude): bool
    {
        $earthRadius = 6371000;
        $latitudeDelta = deg2rad($latitude - $this->finish_latitude);
        $longitudeDelta = deg2rad($longitude - $this->finish_longitude);
        $haversine = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($this->finish_latitude)) * cos(deg2rad($latitude)) * sin($longitudeDelta / 2) ** 2;

        return 2 * $earthRadius * asin(min(1, sqrt($haversine))) <= $this->completion_radius_meters;
    }

    protected static function booted(): void
    {
        static::saving(function (self $trip): void {
            $device = Device::query()->find($trip->device_id);
            $driver = Driver::query()->find($trip->driver_id);

            if (! $device || ! $driver || $device->company_id !== $trip->company_id || $driver->company_id !== $trip->company_id) {
                throw ValidationException::withMessages([
                    'company_id' => 'The trip device and driver must belong to the selected company.',
                ]);
            }
        });
    }
}
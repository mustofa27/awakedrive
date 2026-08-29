<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeviceStatus;
use Illuminate\Validation\ValidationException;
use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'driver_id',
        'device_uid',
        'name',
        'driver_name',
        'vehicle_plate',
        'status',
        'last_seen_at',
    ];

    protected $casts = [
        'status' => DeviceStatus::class,
        'last_seen_at' => 'datetime',
    ];

    protected static function newFactory(): DeviceFactory
    {
        return DeviceFactory::new();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function activeTrip(): HasOne
    {
        return $this->hasOne(Trip::class)->where('status', 'active')->latestOfMany('started_at');
    }

    protected static function booted(): void
    {
        static::saving(function (self $device): void {
            if (! $device->driver_id) {
                return;
            }

            $driver = Driver::query()->find($device->driver_id);

            if (! $driver || $driver->company_id !== $device->company_id) {
                throw ValidationException::withMessages([
                    'driver_id' => 'The selected driver must belong to the device company.',
                ]);
            }

            $device->driver_name = $driver->name;
        });
    }

    public function telemetry(): HasMany
    {
        return $this->hasMany(DeviceTelemetry::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(DeviceAlert::class);
    }

    public function latestTelemetry(): HasOne
    {
        return $this->hasOne(DeviceTelemetry::class)->latestOfMany('recorded_at');
    }
}

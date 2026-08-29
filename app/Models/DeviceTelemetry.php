<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DriverStatus;
use Database\Factories\DeviceTelemetryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceTelemetry extends Model
{
    use HasFactory;

    protected $table = 'device_telemetry';

    protected $fillable = [
        'device_id',
        'driver_status',
        'latitude',
        'longitude',
        'recorded_at',
    ];

    protected $casts = [
        'driver_status' => DriverStatus::class,
        'recorded_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    protected static function newFactory(): DeviceTelemetryFactory
    {
        return DeviceTelemetryFactory::new();
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}

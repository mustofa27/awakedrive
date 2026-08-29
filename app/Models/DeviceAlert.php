<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DriverStatus;
use Database\Factories\DeviceAlertFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'driver_status',
        'latitude',
        'longitude',
        'triggered_at',
        'acknowledged_at',
        'acknowledged_by',
    ];

    protected $casts = [
        'driver_status' => DriverStatus::class,
        'triggered_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    protected static function newFactory(): DeviceAlertFactory
    {
        return DeviceAlertFactory::new();
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}

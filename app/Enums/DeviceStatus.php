<?php

declare(strict_types=1);

namespace App\Enums;

enum DeviceStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case MAINTENANCE = 'maintenance';
    case OFFLINE = 'offline';

    public static function fromDriverStatus(DriverStatus $status): self
    {
        return match ($status) {
            DriverStatus::OFFLINE => self::OFFLINE,
            DriverStatus::MICROSLEEP, DriverStatus::DROWSY => self::ACTIVE,
            default => self::ACTIVE,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::MAINTENANCE => 'Maintenance',
            self::OFFLINE => 'Offline',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::INACTIVE => 'gray',
            self::MAINTENANCE => 'warning',
            self::OFFLINE => 'danger',
        };
    }
}

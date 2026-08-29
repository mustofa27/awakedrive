<?php

declare(strict_types=1);

namespace App\Enums;

enum DriverStatus: string
{
    case NORMAL = 'normal';
    case DROWSY = 'drowsy';
    case MICROSLEEP = 'microsleep';
    case OFFLINE = 'offline';

    public static function fromValue(string $value): ?self
    {
        return self::tryFrom($value);
    }

    public function label(): string
    {
        return match ($this) {
            self::NORMAL => 'Normal',
            self::DROWSY => 'Drowsy',
            self::MICROSLEEP => 'Microsleep',
            self::OFFLINE => 'Offline',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NORMAL => 'success',
            self::DROWSY => 'warning',
            self::MICROSLEEP => 'danger',
            self::OFFLINE => 'gray',
        };
    }
}

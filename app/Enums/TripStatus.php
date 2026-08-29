<?php

declare(strict_types=1);

namespace App\Enums;

enum TripStatus: string
{
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case PARKED = 'parked';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::COMPLETED => 'Completed',
            self::PARKED => 'Stopping / parking',
            self::CANCELLED => 'Cancelled',
        };
    }
}
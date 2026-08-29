<?php

declare(strict_types=1);

namespace App\Filament\Resources\DeviceAlertResource\Pages;

use App\Filament\Resources\DeviceAlertResource;
use Filament\Resources\Pages\ListRecords;

class ListDeviceAlerts extends ListRecords
{
    protected static string $resource = DeviceAlertResource::class;
}

<?php

declare(strict_types=1);

namespace App\Filament\Resources\DeviceResource\Pages;

use App\Filament\Resources\DeviceResource;
use Filament\Resources\Pages\EditRecord;

class EditDevice extends EditRecord
{
    protected static string $resource = DeviceResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! auth()->user()?->hasRole('super_admin')) {
            $data['company_id'] = auth()->user()?->company_id;
        }

        return $data;
    }
}

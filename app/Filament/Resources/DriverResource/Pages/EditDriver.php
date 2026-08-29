<?php

declare(strict_types=1);

namespace App\Filament\Resources\DriverResource\Pages;

use App\Filament\Resources\DriverResource;
use Filament\Resources\Pages\EditRecord;

class EditDriver extends EditRecord
{
    protected static string $resource = DriverResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! auth()->user()?->hasRole('super_admin')) {
            $data['company_id'] = auth()->user()?->company_id;
        }

        return $data;
    }
}
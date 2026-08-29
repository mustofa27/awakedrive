<?php

declare(strict_types=1);

namespace App\Filament\Resources\TripResource\Pages;

use App\Filament\Resources\TripResource;
use Filament\Resources\Pages\EditRecord;

class EditTrip extends EditRecord
{
    protected static string $resource = TripResource::class;
    protected function mutateFormDataBeforeSave(array $data): array { if (! auth()->user()?->hasRole('super_admin')) $data['company_id'] = auth()->user()?->company_id; return $data; }
}
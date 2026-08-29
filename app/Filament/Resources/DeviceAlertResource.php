<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\DriverStatus;
use App\Filament\Resources\DeviceAlertResource\Pages\CreateDeviceAlert;
use App\Filament\Resources\DeviceAlertResource\Pages\EditDeviceAlert;
use App\Filament\Resources\DeviceAlertResource\Pages\ListDeviceAlerts;
use App\Models\DeviceAlert;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DeviceAlertResource extends Resource
{
    protected static ?string $model = DeviceAlert::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user && ! $user->hasRole('super_admin')) {
            $query->whereHas('device', fn (Builder $deviceQuery) => $deviceQuery->where('company_id', $user->company_id));
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Alert')
                    ->schema([
                        Forms\Components\Select::make('device_id')
                            ->relationship('device', 'device_uid')
                            ->options(fn (): array => \App\Models\Device::query()
                                ->when(! auth()->user()?->hasRole('super_admin'), fn (Builder $query) => $query->where('company_id', auth()->user()?->company_id))
                                ->orderBy('device_uid')
                                ->pluck('device_uid', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('driver_status')
                            ->options(collect(DriverStatus::cases())->mapWithKeys(fn (DriverStatus $status) => [$status->value => $status->label()])->all())
                            ->required(),
                        Forms\Components\DateTimePicker::make('triggered_at')->required(),
                        Forms\Components\TextInput::make('latitude')->numeric(),
                        Forms\Components\TextInput::make('longitude')->numeric(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('device.device_uid')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('driver_status')
                    ->badge()
                    ->color(fn (DeviceAlert $record) => $record->driver_status instanceof DriverStatus
                        ? $record->driver_status->color()
                        : (DriverStatus::fromValue((string) $record->driver_status)?->color() ?? 'gray'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('triggered_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('acknowledged_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('driver_status')
                    ->options(collect(DriverStatus::cases())->mapWithKeys(fn (DriverStatus $status) => [$status->value => $status->label()])->all()),
            ])
            ->actions([
                Action::make('acknowledge')
                    ->label('Acknowledge')
                    ->visible(fn (DeviceAlert $record) => is_null($record->acknowledged_at))
                    ->action(function (DeviceAlert $record): void {
                        $record->update([
                            'acknowledged_at' => now(),
                            'acknowledged_by' => auth()->id(),
                        ]);

                        event(new \App\Events\DeviceAlertAcknowledged($record->fresh()));
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeviceAlerts::route('/'),
            'create' => CreateDeviceAlert::route('/create'),
            'edit' => EditDeviceAlert::route('/{record}/edit'),
        ];
    }
}

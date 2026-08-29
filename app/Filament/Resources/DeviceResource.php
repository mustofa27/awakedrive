<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\DeviceStatus;
use App\Filament\Resources\DeviceResource\Pages\CreateDevice;
use App\Filament\Resources\DeviceResource\Pages\EditDevice;
use App\Filament\Resources\DeviceResource\Pages\ListDevices;
use App\Models\Device;
use App\Models\Driver;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DeviceResource extends Resource
{
    protected static ?string $model = Device::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $recordTitleAttribute = 'device_uid';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user && ! $user->hasRole('super_admin')) {
            $query->where('company_id', $user->company_id);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Device details')
                    ->schema([
                        Forms\Components\Select::make('company_id')
                            ->relationship('company', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('driver_id', null))
                            ->disabled(fn (): bool => ! auth()->user()?->hasRole('super_admin'))
                            ->required(),
                        Forms\Components\TextInput::make('device_uid')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('name')
                            ->maxLength(255),
                        Forms\Components\Select::make('driver_id')
                            ->label('Assigned driver')
                            ->options(fn (Forms\Get $get): array => Driver::query()
                                ->where('company_id', $get('company_id'))
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Choose a driver to assign or reassign this device.'),
                        Forms\Components\TextInput::make('vehicle_plate')
                            ->maxLength(255),
                        Forms\Components\Select::make('status')
                            ->options(collect(DeviceStatus::cases())->mapWithKeys(fn (DeviceStatus $status) => [$status->value => $status->label()])->all())
                            ->required(),
                        Forms\Components\DateTimePicker::make('last_seen_at')
                            ->native(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('device_uid')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('company.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('driver.name')
                    ->label('Assigned driver')
                    ->placeholder('Unassigned')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('vehicle_plate')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (Device $record) => $record->status instanceof DeviceStatus
                        ? $record->status->color()
                        : (DeviceStatus::tryFrom((string) $record->status)?->color() ?? 'gray'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_seen_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(DeviceStatus::cases())->mapWithKeys(fn (DeviceStatus $status) => [$status->value => $status->label()])->all()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDevices::route('/'),
            'create' => CreateDevice::route('/create'),
            'edit' => EditDevice::route('/{record}/edit'),
        ];
    }
}

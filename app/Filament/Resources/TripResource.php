<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\TripStatus;
use App\Filament\Resources\TripResource\Pages\CreateTrip;
use App\Filament\Resources\TripResource\Pages\EditTrip;
use App\Filament\Resources\TripResource\Pages\ListTrips;
use App\Models\Device;
use App\Models\Driver;
use App\Models\Trip;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TripResource extends Resource
{
    protected static ?string $model = Trip::class;
    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?string $recordTitleAttribute = 'id';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();
        if ($user && ! $user->hasRole('super_admin')) $query->where('company_id', $user->company_id);
        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Trip assignment')->schema([
                Forms\Components\Select::make('company_id')
                    ->relationship('company', 'name')
                    ->default(fn (): ?int => auth()->user()?->company_id)
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required()
                    ->hidden(fn (): bool => ! auth()->user()?->hasRole('super_admin'))
                    ->afterStateUpdated(fn (Forms\Set $set) => [$set('device_id', null), $set('driver_id', null)]),
                Forms\Components\Select::make('device_id')->options(fn (Forms\Get $get) => Device::query()->where('company_id', $get('company_id'))->orderBy('device_uid')->pluck('device_uid', 'id'))->searchable()->required(),
                Forms\Components\Select::make('driver_id')->options(fn (Forms\Get $get) => Driver::query()->where('company_id', $get('company_id'))->where('is_active', true)->orderBy('name')->pluck('name', 'id'))->searchable()->required(),
                Forms\Components\Select::make('status')->options(collect(TripStatus::cases())->mapWithKeys(fn (TripStatus $status) => [$status->value => $status->label()]))->default(TripStatus::ACTIVE->value)->required(),
                Forms\Components\DateTimePicker::make('started_at')->default(now())->required(),
            ])->columns(2),
            Forms\Components\Section::make('Route locations')->description('Choose the trip origin and destination on the map. Telemetry completes the trip when the device reaches the destination radius.')->schema([
                Forms\Components\ViewField::make('route_location_picker')
                    ->view('filament.forms.components.trip-route-picker')
                    ->dehydrated(false)
                    ->columnSpanFull(),
                Forms\Components\Hidden::make('start_latitude')->required(),
                Forms\Components\Hidden::make('start_longitude')->required(),
                Forms\Components\Hidden::make('finish_latitude')->required(),
                Forms\Components\Hidden::make('finish_longitude')->required(),
                Forms\Components\TextInput::make('completion_radius_meters')->numeric()->integer()->minValue(25)->default(150)->required(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('device.device_uid')->label('Device')->searchable(),
            Tables\Columns\TextColumn::make('driver.name')->searchable(),
            Tables\Columns\TextColumn::make('company.name')->searchable(),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('started_at')->dateTime()->sortable(),
            Tables\Columns\TextColumn::make('completed_at')->dateTime(),
        ])->filters([Tables\Filters\SelectFilter::make('status')->options(collect(TripStatus::cases())->mapWithKeys(fn (TripStatus $status) => [$status->value => $status->label()]))])->actions([Tables\Actions\EditAction::make()])->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array { return ['index' => ListTrips::route('/'), 'create' => CreateTrip::route('/create'), 'edit' => EditTrip::route('/{record}/edit')]; }
}
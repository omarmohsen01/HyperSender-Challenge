<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TripResource\Pages;
use App\Filament\Resources\TripResource\RelationManagers;
use App\Models\Trip;
use App\Rules\NoOverlappingTrips;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Collection;
use App\Enums\TripStatusEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Set;

class TripResource extends Resource
{
    protected static ?string $model = Trip::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Trip Information')
                ->schema(self::getTripFormFields())
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(self::getTableColumns())
            ->filters(self::getTableFilters())
            ->actions(self::getTableActions())
            ->bulkActions(self::getBulkActions())
            ->defaultSort('created_at', 'desc');
    }

    private static function getTripFormFields(): array
    {
        return [
            TextInput::make('origin')
                ->label('Origin')
                ->required()
                ->maxLength(100),

            TextInput::make('destination')
                ->label('Destination')
                ->required()
                ->maxLength(100),

            Select::make('company_id')
                ->label('Company')
                ->relationship('company', 'name')
                ->searchable()
                ->required()
                ->live()
                ->afterStateUpdated(function (Set $set) {
                    $set('driver_id', null);
                    $set('vehicle_id', null);
                }),

            Select::make('vehicle_id')
                ->label('Vehicle')
                ->relationship(
                    name: 'vehicle',
                    titleAttribute: 'license_plate',
                    modifyQueryUsing: fn (Builder $query, Get $get) =>
                        $query->when(
                            $get('company_id'),
                            fn (Builder $query, $companyId) => $query->where('company_id', $companyId)
                        )
                )
                ->searchable()
                ->required()
                ->disabled(fn (Get $get): bool => !$get('company_id'))
                ->placeholder('Select a company first'),

            Select::make('driver_id')
                ->label('Driver')
                ->relationship(
                    name: 'driver',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn (Builder $query, Get $get) =>
                        $query->when(
                            $get('company_id'),
                            fn (Builder $query, $companyId) => $query->where('company_id', $companyId)
                        )
                )
                ->searchable()
                ->required()
                ->disabled(fn (Get $get): bool => !$get('company_id'))
                ->placeholder('Select a company first'),

            Select::make('status')
                ->label('Status')
                ->options(TripStatusEnum::getOptions())
                ->default(TripStatusEnum::SCHEDULED)
                ->required(),

            DateTimePicker::make('schedule_start')
                ->label('Schedule Start')
                ->required()
                ->live()
                ->afterStateUpdated(function (Get $get, Set $set) {
                    // Clear validation when schedule start changes
                    $set('schedule_end', null);
                }),

            DateTimePicker::make('schedule_end')
                ->label('Schedule End')
                ->rule('after:schedule_start')
                ->required()
                ->live()
                ->rules([
                    function (Get $get) {
                        return function (string $attribute, $value, \Closure $fail) use ($get) {
                            $driverId = $get('driver_id');
                            $vehicleId = $get('vehicle_id');
                            $scheduleStart = $get('schedule_start');
                            $scheduleEnd = $value;
                            $tripId = $get('id'); // For editing existing trips

                            if ($driverId && $vehicleId && $scheduleStart && $scheduleEnd) {
                                $rule = new NoOverlappingTrips($driverId,$vehicleId,$scheduleStart,$scheduleEnd,$tripId);
                                $rule->validate($attribute, $value, $fail);
                            }
                        };
                    }
                ]),

            DateTimePicker::make('actual_start')
                ->label('Actual Start')
                ->nullable(),

            DateTimePicker::make('actual_end')
                ->label('Actual End')
                ->rule('after:actual_start')
                ->nullable(),
        ];
    }
    private static function getTableColumns(): array
    {
        return [
            TextColumn::make('trip_number')
                ->label('Trip Number')
                ->copyable()
                ->toggleable(),

            TextColumn::make('origin')
                ->label('Route')
                ->formatStateUsing(fn($state, $record) =>
                    "<div>
                        <span class='font-medium'>" . \Illuminate\Support\Str::limit($record->origin, 30) . "</span>
                        <br>
                        <span class='text-gray-500'>→ " . \Illuminate\Support\Str::limit($record->destination, 30) . "</span>
                    </div>"
                )
                ->html()
                ->searchable(query: function ($query, $search) {
                    return $query->where('origin', 'like', "%{$search}%")
                                 ->orWhere('destination', 'like', "%{$search}%");
                })
                ->copyable()
                ->toggleable(),


            TextColumn::make('company.name')
                ->label('Company')
                ->searchable()
                ->sortable()
                ->limit(20)
                ->tooltip(fn($record) => $record->company?->name)
                ->toggleable(),

            TextColumn::make('vehicle.license_plate')
                ->label('Vehicle')
                ->searchable()
                ->sortable()
                ->limit(30)
                ->tooltip(fn($record) => $record->vehicle?->license_plate)
                ->toggleable(),

            TextColumn::make('driver.name')
                ->label('Driver')
                ->searchable()
                ->sortable()
                ->limit(30)
                ->tooltip(fn($record) => $record->driver?->name)
                ->toggleable(),

            TextColumn::make('schedule_start')
                ->label('Schedule')
                ->formatStateUsing(fn($state, $record) =>
                    "<div>
                        <span class='font-medium'>Start:</span> " . $record->schedule_start?->format('M j, Y H:i') . "<br>
                        <span class='font-medium'>End:</span> " . $record->schedule_end?->format('M j, Y H:i') . "
                    </div>"
                )
                ->html()
                ->sortable()
                ->toggleable(),

            TextColumn::make('duration')
                ->label('Duration')
                ->formatStateUsing(fn ($state, $record) => formatDuration($record->schedule_start, $record->schedule_end))
                ->toggleable(),


            TextColumn::make('actual_start')
                ->label('Actual')
                ->formatStateUsing(fn($state, $record) =>
                    "<div>
                        <span class='font-medium'>Start:</span> " . $record->actual_start?->format('M j, Y H:i') . "<br>
                        <span class='font-medium'>End:</span> " . $record->actual_end?->format('M j, Y H:i') . "
                    </div>"
                )
                ->html()
                ->sortable()
                ->toggleable(),

            TextColumn::make('status')
                ->label('Status')
                ->searchable()
                ->sortable()
                ->badge()
                ->color(fn(TripStatusEnum $state) => $state->getColor())
                ->formatStateUsing(fn($state) => $state->name()),

            TextColumn::make('created_at')
                ->label('Created')
                ->dateTime('M j, Y')
                ->sortable()
                ->since()
                ->toggleable(),
        ];
    }
    private static function getTableFilters(): array
    {
        return [
            SelectFilter::make('status')
                ->label('Status')
                ->options(TripStatusEnum::getOptions())
                ->multiple(),

            SelectFilter::make('company_id')
                ->label('Company')
                ->relationship('company', 'name')
                ->searchable()
                ->preload(),

            Filter::make('created_at')
                ->form([
                    DatePicker::make('created_from')
                        ->label('Created from'),
                    DatePicker::make('created_until')
                        ->label('Created until'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            $data['created_from'],
                            fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                        )
                        ->when(
                            $data['created_until'],
                            fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                        );
                }),
        ];
    }
    private static function getTableActions(): array
    {
        return [
            ActionGroup::make([
                EditAction::make(),
                self::getUpdateStatusAction(),
                DeleteAction::make()
                    ->requiresConfirmation(),
            ])
        ];
    }
    private static function getUpdateStatusAction(): Action
    {
        return Action::make('updateStatus')
            ->label('Update Status')
            ->icon('heroicon-o-adjustments-vertical')
            ->form([
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        TripStatusEnum::SCHEDULED->value => 'Scheduled',
                        TripStatusEnum::IN_PROGRESS->value => 'In Progress',
                        TripStatusEnum::COMPLETED->value => 'Completed',
                        TripStatusEnum::CANCELLED->value => 'Cancelled',
                    ])
                    ->required(),
            ])
            ->action(function (Trip $record, array $data): void {
                $record->update([
                    'status' => $data['status'],
                ]);
            })
            ->color('secondary')
            ->modalHeading('Update Trip Status')
            ->modalSubmitActionLabel('Save');
    }
    private static function getBulkActions(): array
    {
        return [
            BulkActionGroup::make([
                DeleteBulkAction::make()
                    ->requiresConfirmation(),

                BulkAction::make('updateStatus')
                    ->label('Update Status')
                    ->icon('heroicon-o-adjustments-vertical')
                    ->form([
                        Select::make('status')
                            ->label('New Status')
                            ->options(TripStatusEnum::getOptions())
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $records->each->update(['status' => $data['status']]);

                        Notification::make()
                            ->title('Bulk Status Update')
                            ->body("{$records->count()} Trips updated successfully")
                            ->success()
                            ->send();
                    }),
            ]),
        ];
    }
    private static function getCompanySelectField(): Select
    {
        return Select::make('company_id')
            ->label('Company')
            ->relationship('company', 'name')
            ->searchable()
            ->preload()
            ->required()
            ->createOptionForm(self::getCompanyFormFields())
            ->createOptionAction(fn($action) => $action
                ->modalHeading('Create New Company')
                ->modalSubmitActionLabel('Create Company')
                ->modalWidth('lg')
            )
            ->editOptionForm(self::getCompanyFormFields())
            ->editOptionAction(fn($action) => $action
                ->modalHeading('Edit Company')
                ->modalSubmitActionLabel('Update Company')
                ->modalWidth('lg')
            );
    }
    private static function getCompanyFormFields(): array
    {
        return [
            TextInput::make('name')
                ->label('Company Name')
                ->required()
                ->maxLength(255),

            TextInput::make('address')
                ->label('Address')
                ->maxLength(255),

            TextInput::make('phone')
                ->label('Phone')
                ->tel()
                ->maxLength(20),

            TextInput::make('email')
                ->label('Email')
                ->email()
                ->maxLength(255),
        ];
    }
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrips::route('/'),
            'create' => Pages\CreateTrip::route('/create'),
            'edit' => Pages\EditTrip::route('/{record}/edit'),
        ];
    }
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['company', 'vehicle', 'driver']);
    }
}

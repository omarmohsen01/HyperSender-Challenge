<?php

namespace App\Filament\Resources;

use App\Enums\VehicleStatusEnum;
use App\Enums\VehicleTypeEnum;
use App\Filament\Resources\VehicleResource\Pages;
use App\Filament\Resources\VehicleResource\RelationManagers;
use App\Models\Vehicle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
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

class VehicleResource extends Resource
{
    protected static ?string $model = Vehicle::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Fleet Management';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Vehicle Information')
                ->schema(self::getVehicleFormFields())
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

    private static function getVehicleFormFields(): array
    {
        return [
            TextInput::make('license_plate')
                ->label('License Plate')
                ->required()
                ->maxLength(20)
                ->autocapitalize('characters')
                ->unique(ignoreRecord: true),

            TextInput::make('model')
                ->label('Model')
                ->required()
                ->maxLength(50)
                ->unique(ignoreRecord: true)
                ->alphaNum(),

            TextInput::make('year')
                ->label('Year')
                ->required()
                ->numeric()
                ->minValue(1900)
                ->maxValue(date('Y') + 1)
                ->rules(['digits:4']),

            TextInput::make('capacity')
                ->label('Capacity')
                ->required()
                ->numeric()
                ->minValue(1)
                ->maxValue(10000),

            self::getCompanySelectField(),

            Select::make('status')
                ->label('Status')
                ->options(VehicleStatusEnum::getOptions())
                ->default(VehicleStatusEnum::AVAILABLE)
                ->required(),

            Select::make('type')
                ->label('Type')
                ->options(VehicleTypeEnum::getOptions())
                ->required(),
        ];
    }

    private static function getTableColumns(): array
    {
        return [
            TextColumn::make('license_plate')
                ->label('License Plate')
                ->copyable()
                ->toggleable(),

            TextColumn::make('model')
                ->label('Model')
                ->formatStateUsing(fn($state, $record) =>
                    "{$state}\n<span class='text-xs text-gray-500'>Year: {$record->year}</span>"
                )
                ->html()
                ->searchable()
                ->copyable()
                ->toggleable(),

            TextColumn::make('capacity')
                ->label('Capacity')
                ->searchable()
                ->copyable()
                ->toggleable(),

            TextColumn::make('company.name')
                ->label('Company')
                ->searchable()
                ->sortable()
                ->limit(30)
                ->tooltip(fn($record) => $record->company?->name)
                ->toggleable(),

            TextColumn::make('status')
                ->label('Status')
                ->searchable()
                ->sortable()
                ->badge()
                ->color(fn(VehicleStatusEnum $state) => $state->getColor())
                ->formatStateUsing(fn($state) => $state->name()),

            TextColumn::make('type')
                ->label('Type')
                ->searchable()
                ->sortable()
                ->badge()
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
                ->options(VehicleStatusEnum::getOptions())
                ->multiple(),

            SelectFilter::make('type')
                ->label('Type')
                ->options(VehicleTypeEnum::getOptions())
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
                        VehicleStatusEnum::AVAILABLE->value => 'Available',
                        VehicleStatusEnum::IN_USE->value => 'In Use',
                        VehicleStatusEnum::MAINTENANCE->value => 'Maintenance',
                        VehicleStatusEnum::OUT_OF_SERVICE->value => 'Out Of Service',
                    ])
                    ->required(),
            ])
            ->action(function (Vehicle $record, array $data): void {
                $record->update([
                    'status' => $data['status'],
                ]);
            })
            ->color('secondary')
            ->modalHeading('Update Vehicle Status')
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
                            ->options(VehicleStatusEnum::getOptions())
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $records->each->update(['status' => $data['status']]);

                        Notification::make()
                            ->title('Bulk Status Update')
                            ->body("{$records->count()} Vehicles updated successfully")
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
            'index' => Pages\ListVehicles::route('/'),
            'create' => Pages\CreateVehicle::route('/create'),
            'edit' => Pages\EditVehicle::route('/{record}/edit'),
        ];
    }
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}

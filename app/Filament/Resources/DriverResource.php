<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DriverResource\Pages;
use App\Filament\Resources\DriverResource\RelationManagers;
use App\Models\Driver;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Enums\DriverStatusEnum;
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

class DriverResource extends Resource
{
    protected static ?string $model = Driver::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $navigationGroup = 'Fleet Management';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Driver Information')
                ->schema(self::getDriverFormFields())
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

    private static function getDriverFormFields(): array
    {
        return [
            TextInput::make('name')
                ->label('Full Name')
                ->required()
                ->maxLength(255)
                ->autocapitalize('words'),

            TextInput::make('license_number')
                ->label('License Number')
                ->required()
                ->maxLength(50)
                ->unique(ignoreRecord: true)
                ->alphaNum(),

            TextInput::make('phone')
                ->label('Phone Number')
                ->tel()
                ->required()
                ->maxLength(20),

            TextInput::make('email')
                ->label('Email')
                ->email()
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),

            self::getCompanySelectField(),

            Select::make('status')
                ->label('Status')
                ->options(DriverStatusEnum::getOptions())
                ->default(DriverStatusEnum::ACTIVE)
                ->required(),
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

    private static function getTableColumns(): array
    {
        return [
            TextColumn::make('name')
                ->label('Full Name')
                ->searchable()
                ->sortable()
                ->limit(30)
                ->tooltip(fn($record) => $record->name),

            TextColumn::make('license_number')
                ->label('License No.')
                ->copyable()
                ->toggleable(),

            TextColumn::make('phone')
                ->label('Phone')
                ->searchable()
                ->copyable()
                ->toggleable(),

            TextColumn::make('email')
                ->label('Email')
                ->searchable()
                ->copyable()
                ->icon('heroicon-m-envelope')
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
                ->color(fn(DriverStatusEnum $state) => $state->getColor())
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
                ->options(DriverStatusEnum::getOptions())
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
                        DriverStatusEnum::ACTIVE->value => 'Active',
                        DriverStatusEnum::INACTIVE->value => 'Inactive',
                        DriverStatusEnum::ON_LEAVE->value => 'On Leave',
                    ])
                    ->required(),
            ])
            ->action(function (Driver $record, array $data): void {
                $record->update([
                    'status' => $data['status'],
                ]);
            })
            ->color('secondary')
            ->modalHeading('Update Driver Status')
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
                            ->options(DriverStatusEnum::getOptions())
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $records->each->update(['status' => $data['status']]);

                        Notification::make()
                            ->title('Bulk Status Update')
                            ->body("{$records->count()} drivers updated successfully")
                            ->success()
                            ->send();
                    }),
            ]),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDrivers::route('/'),
            'create' => Pages\CreateDriver::route('/create'),
            'edit' => Pages\EditDriver::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['company']);
    }
}

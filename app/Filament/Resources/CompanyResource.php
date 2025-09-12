<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompanyResource\Pages;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Models\Trip;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Tables\Actions\DeleteAction;
use Filament\Notifications\Notification;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationGroup = 'Management';
    protected static ?int $navigationSort = 1;


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Company Information')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Full Name')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('address')
                        ->label('Address')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('phone')
                        ->label('Phone Number')
                        ->tel()
                        ->required()
                        ->maxLength(20),

                    Forms\Components\TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->toggleable()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('address')
                    ->limit(30)
                    ->toggleable()
                    ->tooltip(fn ($record) => $record->address),

                Tables\Columns\TextColumn::make('phone')
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('email')
                    ->copyable()
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y - H:i')
                    ->toggleable()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('created_at', '>=', Carbon::parse($date)))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('created_at', '<=', Carbon::parse($date)));
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                self::getCustomDeleteAction(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    self::getCustomBulkDeleteAction(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompanies::route('/'),
            'create' => Pages\CreateCompany::route('/create'),
            'edit' => Pages\EditCompany::route('/{record}/edit'),
        ];
    }
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    private static function getCustomDeleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->requiresConfirmation()
            ->action(function (Company $record) {
                // Check for related records
                $driversCount = Driver::where('company_id', $record->id)->count();
                $vehiclesCount = Vehicle::where('company_id', $record->id)->count();
                $tripsCount = Trip::where('company_id', $record->id)->count();

                if ($driversCount > 0 || $vehiclesCount > 0 || $tripsCount > 0) {
                    $relatedRecords = [];
                    if ($driversCount > 0) $relatedRecords[] = "{$driversCount} driver(s)";
                    if ($vehiclesCount > 0) $relatedRecords[] = "{$vehiclesCount} vehicle(s)";
                    if ($tripsCount > 0) $relatedRecords[] = "{$tripsCount} trip(s)";

                    $message = "Cannot delete this company because it has " . implode(', ', $relatedRecords) . " associated with it. Please delete or reassign these records first.";

                    Notification::make()
                        ->title('Cannot Delete Company')
                        ->body($message)
                        ->danger()
                        ->send();

                    // Prevent deletion by not calling $record->delete()
                    return;
                }

                // If no related records, proceed with deletion
                $record->delete();

                Notification::make()
                    ->title('Company Deleted')
                    ->body('Company has been deleted successfully.')
                    ->success()
                    ->send();
            });
    }

    private static function getCustomBulkDeleteAction()
    {
        return Tables\Actions\BulkAction::make('delete')
            ->label('Delete Selected')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->action(function ($records) {
                $companiesWithRelations = [];
                $companiesToDelete = [];

                foreach ($records as $company) {
                    $driversCount = Driver::where('company_id', $company->id)->count();
                    $vehiclesCount = Vehicle::where('company_id', $company->id)->count();
                    $tripsCount = Trip::where('company_id', $company->id)->count();

                    if ($driversCount > 0 || $vehiclesCount > 0 || $tripsCount > 0) {
                        $relatedRecords = [];
                        if ($driversCount > 0) $relatedRecords[] = "{$driversCount} driver(s)";
                        if ($vehiclesCount > 0) $relatedRecords[] = "{$vehiclesCount} vehicle(s)";
                        if ($tripsCount > 0) $relatedRecords[] = "{$tripsCount} trip(s)";

                        $companiesWithRelations[] = "{$company->name}: " . implode(', ', $relatedRecords);
                    } else {
                        $companiesToDelete[] = $company;
                    }
                }

                if (!empty($companiesWithRelations)) {
                    $message = "Cannot delete the following companies because they have related records:\n\n" . implode("\n", $companiesWithRelations) . "\n\nPlease delete or reassign these records first.";

                    Notification::make()
                        ->title('Cannot Delete Companies')
                        ->body($message)
                        ->danger()
                        ->send();
                }

                // Delete only companies without related records
                if (!empty($companiesToDelete)) {
                    foreach ($companiesToDelete as $company) {
                        $company->delete();
                    }

                    $deletedCount = count($companiesToDelete);
                    $skippedCount = count($companiesWithRelations);

                    if ($skippedCount > 0) {
                        Notification::make()
                            ->title('Partial Deletion Complete')
                            ->body("Deleted {$deletedCount} companies. {$skippedCount} companies were skipped due to related records.")
                            ->warning()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Companies Deleted')
                            ->body("All {$deletedCount} selected companies have been deleted successfully.")
                            ->success()
                            ->send();
                    }
                }
            });
    }
}

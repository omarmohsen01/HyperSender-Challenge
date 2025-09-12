<?php

namespace App\Filament\Widgets;

use App\Enums\TripStatusEnum;
use App\Models\Trip;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class RecentTripsWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Recent Activity';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Trip::query()
                    ->with(['driver', 'vehicle', 'company'])
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('trip_number')
                    ->label('Trip #')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('origin')
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

                Tables\Columns\TextColumn::make('driver.name')
                    ->label('Driver')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('vehicle.license_plate')
                    ->label('Vehicle')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (TripStatusEnum $state) => $state->getColor())
                    ->formatStateUsing(fn ($state) => $state->name()),

                Tables\Columns\TextColumn::make('schedule_start')
                    ->label('Scheduled')
                    ->dateTime('M j, H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('actual_end')
                    ->label('Completed')
                    ->dateTime('M j, H:i')
                    ->sortable()
                    ->placeholder('Not completed'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated(false);
    }
}

<?php

namespace App\Enums;

enum TripStatusEnum: string {
    case SCHEDULED = 'scheduled';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    public function name(): string
    {
        return match ($this) {
            self::SCHEDULED => 'scheduled',
            self::IN_PROGRESS => 'in_progress',
            self::COMPLETED => 'completed',
            self::CANCELLED => 'cancelled',
        };
    }
    public static function all(): array
    {
        return array_map(fn ($enum) => [
            'value' => $enum->value,
            'name' => $enum->name(),
        ], self::cases());
    }
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
    public function getColor(): string
    {
        return match ($this) {
            self::SCHEDULED => 'primary',
            self::IN_PROGRESS => 'warning',
            self::COMPLETED => 'success',
            self::CANCELLED => 'danger',
        };
    }
    public static function getOptions(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn($case) => [$case->value => $case->name()])
            ->toArray();
    }
}

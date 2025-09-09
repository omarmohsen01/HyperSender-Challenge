<?php

namespace App\Enums;

enum VehicleTypeEnum: string {
    case CAR = 'car';
    case TRUCK = 'truck';
    case BUS = 'bus';
    case VAN = 'van';
    case OTHER = 'other';
    public function name(): string
    {
        return match ($this) {
            self::CAR => 'car',
            self::TRUCK => 'truck',
            self::BUS => 'bus',
            self::VAN => 'van',
            self::OTHER => 'other',
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
}

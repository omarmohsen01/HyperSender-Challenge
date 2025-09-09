<?php

namespace App\Enums;

enum VehicleStatusEnum: string {
    case AVAILABLE = 'available';
    case IN_USE = 'in_use';
    case MAINTENANCE = 'maintenance';
    case OUT_OF_SERVICE = 'out_of_service';
    public function name(): string
    {
        return match ($this) {
            self::AVAILABLE => 'available',
            self::IN_USE => 'in_use',
            self::MAINTENANCE => 'maintenance',
            self::OUT_OF_SERVICE => 'out_of_service',
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

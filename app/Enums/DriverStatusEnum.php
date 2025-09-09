<?php

namespace App\Enums;

enum DriverStatusEnum: string {
    case ACTIVE = 'active';
    case INACTIVE = 'in_active';
    case ON_LEAVE = 'on_leave';
    public function name(): string
    {
        return match ($this) {
            self::ACTIVE => 'active',
            self::INACTIVE => 'in_active',
            self::ON_LEAVE => 'on_leave',
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

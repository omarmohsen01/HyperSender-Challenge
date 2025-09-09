<?php

namespace App\Models;

use App\Enums\VehicleStatusEnum;
use App\Enums\VehicleTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'license_plate',
        'model',
        'year',
        'capacity',
        'status',
        'type',
        'company_id',
    ];

    protected $casts = [
        'status' => VehicleStatusEnum::class,
        'type' => VehicleTypeEnum::class,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }
}



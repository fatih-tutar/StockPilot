<?php

namespace App\Models;

use App\Models\Concerns\AssignsCurrentCompany;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use AssignsCurrentCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'license_plate',
        'driver_name',
        'description',
        'is_delivery_vehicle',
        'casco_expires_on',
        'insurance_expires_on',
        'inspection_due_on',
    ];

    protected function casts(): array
    {
        return [
            'is_delivery_vehicle' => 'boolean',
            'casco_expires_on' => 'date',
            'insurance_expires_on' => 'date',
            'inspection_due_on' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'model');
    }
}

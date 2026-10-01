<?php

namespace App\Models;

use App\Models\Concerns\AssignsCurrentCompany;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use AssignsCurrentCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'phone',
        'email',
        'address',
        'notes',
        'is_active',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}

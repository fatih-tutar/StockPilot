<?php

namespace App\Models;

use App\Enums\LeaveStatus;
use App\Models\Concerns\AssignsCurrentCompany;
use Database\Factories\LeaveFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Leave extends Model
{
    /** @use HasFactory<LeaveFactory> */
    use AssignsCurrentCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'user_id',
        'start_on',
        'return_on',
        'leave_days',
        'status',
        'in_office',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_on' => 'date',
            'return_on' => 'date',
            'leave_days' => 'integer',
            'status' => LeaveStatus::class,
            'in_office' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

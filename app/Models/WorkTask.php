<?php

namespace App\Models;

use App\Enums\WorkTaskStatus;
use App\Models\Concerns\AssignsCurrentCompany;
use Database\Factories\WorkTaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkTask extends Model
{
    /** @use HasFactory<WorkTaskFactory> */
    use AssignsCurrentCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'title',
        'due_on',
        'repeats_monthly',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'repeats_monthly' => 'boolean',
            'status' => WorkTaskStatus::class,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * A monthly task whose due month has passed spawns next month's copy and then stops repeating.
     * Opening the list catches up at most one year of missed months.
     */
    public static function rollMonthly(): void
    {
        $monthStart = now()->startOfMonth()->toDateString();

        for ($step = 0; $step < 24; $step++) {
            $task = static::query()
                ->where('repeats_monthly', true)
                ->whereNotNull('due_on')
                ->whereDate('due_on', '<', $monthStart)
                ->orderBy('due_on')
                ->first();

            if ($task === null) {
                return;
            }

            static::withoutEvents(function () use ($task): void {
                static::query()->create([
                    'company_id' => $task->company_id,
                    'title' => $task->title,
                    'due_on' => $task->due_on->copy()->addMonthNoOverflow()->toDateString(),
                    'repeats_monthly' => true,
                    'status' => WorkTaskStatus::Open,
                ]);
            });

            $task->forceFill(['repeats_monthly' => false])->save();
        }
    }
}

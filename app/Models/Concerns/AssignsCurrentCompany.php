<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait AssignsCurrentCompany
{
    public static function bootAssignsCurrentCompany(): void
    {
        static::addGlobalScope('currentCompany', function (Builder $builder): void {
            if (! auth()->hasUser()) {
                return;
            }

            $companyId = auth()->user()?->company_id;

            if ($companyId === null) {
                return;
            }

            $builder->where($builder->qualifyColumn('company_id'), $companyId);
        });

        static::creating(function (Model $model): void {
            $companyId = auth()->user()?->company_id;

            if ($companyId === null) {
                return;
            }

            $model->setAttribute('company_id', $companyId);
        });
    }
}

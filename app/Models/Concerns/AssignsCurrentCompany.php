<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

trait AssignsCurrentCompany
{
    public static function bootAssignsCurrentCompany(): void
    {
        static::creating(function (Model $model): void {
            $companyId = auth()->user()?->company_id;

            if ($companyId === null) {
                return;
            }

            $model->setAttribute('company_id', $companyId);
        });
    }
}

<?php

namespace App\Models\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $user = Auth::user();

            if ($user instanceof User && $user->tenant_id !== null) {
                $builder->where($builder->getModel()->getTable().'.tenant_id', $user->tenant_id);
            }
        });

        static::creating(function (Model $model): void {
            $user = Auth::user();

            if ($user instanceof User && $user->tenant_id !== null && empty($model->getAttribute('tenant_id'))) {
                $model->setAttribute('tenant_id', $user->tenant_id);
            }
        });
    }
}

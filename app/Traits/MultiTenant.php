<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait MultiTenant
{
    public static function bootMultiTenant(): void
    {
        if (request()->routeIs('api.admin.*') && auth('admin')->check()) {
            if (columnExists((new self)->getTable(), 'company_id')) {
                $user = auth('admin')->user();
                static::creating(function ($model) use ($user) {
                    $model->company_id = is_null($model->company_id) ? $user->company_id : $model->company_id;
                });

                // global scope
                static::addGlobalScope('company_scope', function (Builder $builder) use ($user) {
                    return $builder->where('company_id', $user->company_id);
                });
            }
        }
    }
}

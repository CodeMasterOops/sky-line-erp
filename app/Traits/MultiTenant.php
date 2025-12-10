<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait MultiTenant
{
    public static function bootMultiTenant()
    {
        if (request()->routeIs('api.vendor.*') && auth('vendor')->check()) {
            if (columnExists((new self)->getTable(), 'vendor_id')) {
                $user = auth('vendor')->user();
                static::creating(function ($model) use ($user) {
                    $model->vendor_id = is_null($model->vendor_id) ? $user->vendor_id : $model->vendor_id;
                });

                // global scope
                static::addGlobalScope('vendor_scope', function (Builder $builder) use ($user) {
                    return $builder->where('vendor_id', $user->vendor_id);
                });
            }
        }
    }
}

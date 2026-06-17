<?php

namespace App\Models;

use App\Traits\FlushCache;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use FlushCache;

    protected $fillable = [
        'key',
        'value',
    ];

    public function cacheKeys(): array
    {
        return ['setting'];
    }
}

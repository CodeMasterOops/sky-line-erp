<?php

namespace App\Traits;

use App\Models\CrmActivity;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasActivities
{
    public function activities(): MorphMany
    {
        return $this->morphMany(CrmActivity::class, 'subject')->latest('occurred_at');
    }
}

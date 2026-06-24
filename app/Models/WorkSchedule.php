<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;

class WorkSchedule extends Model
{
    use MultiTenant;

    protected $fillable = [
        'company_id',
        'name',
        'start_time',
        'end_time',
        'grace_minutes',
        'standard_hours_per_day',
        'overtime_multiplier',
        'overtime_enabled',
        'weekly_off_days',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'grace_minutes' => 'integer',
            'standard_hours_per_day' => 'float',
            'overtime_multiplier' => 'float',
            'overtime_enabled' => 'boolean',
            'weekly_off_days' => 'array',
            'is_default' => 'boolean',
        ];
    }

    /**
     * Weekly off day numbers (Carbon dayOfWeek: 0=Sun … 6=Sat). Defaults to Saturday.
     *
     * @return array<int, int>
     */
    public function weeklyOffDays(): array
    {
        return ! empty($this->weekly_off_days) ? array_map('intval', $this->weekly_off_days) : [6];
    }
}

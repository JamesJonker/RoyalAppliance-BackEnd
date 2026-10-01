<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkScheduleDay extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_schedule_id',
        'day_of_week',
        'day_fraction',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'day_fraction' => 'decimal:2',
    ];

    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(
            WorkSchedule::class
        );
    }
}
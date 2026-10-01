<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Schedule days.
     */
    public function days(): HasMany
    {
        return $this->hasMany(WorkScheduleDay::class);
    }

    /**
     * Staff assigned to this schedule.
     */
    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }
}
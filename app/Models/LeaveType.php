<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_paid',
        'is_active',
        'calculation_method',
        'cycle_months',
    ];

    protected $casts = [
        'is_paid' => 'boolean',
        'is_active' => 'boolean',
        'cycle_months' => 'integer',
    ];

    public function entitlementRules(): HasMany
    {
        return $this->hasMany(
            LeaveEntitlementRule::class
        );
    }

    public function staffLeave(): HasMany
    {
        return $this->hasMany(
            StaffLeave::class
        );
    }
}
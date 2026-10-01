<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveEntitlementRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'leave_type_id',
        'minimum_service_years',
        'maximum_service_years',
        'days_entitled',
        'is_active',
    ];

    protected $casts = [
        'days_entitled' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Leave type belonging to this rule.
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
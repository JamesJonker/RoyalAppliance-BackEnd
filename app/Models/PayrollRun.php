<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PayrollRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_period_id',
        'run_number',
        'status',

        'employee_count',

        'total_gross',
        'total_paye',
        'total_employee_uif',
        'total_employer_uif',
        'total_sdl',
        'total_deductions',
        'total_net',
        'total_employer_cost',

        'calculation_started_at',
        'calculation_completed_at',

        'approved_by',
        'approved_at',

        'paid_by',
        'paid_at',

        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',

        'notes',
    ];

    protected $casts = [
        'run_number' => 'integer',
        'employee_count' => 'integer',

        'total_gross' => 'decimal:2',
        'total_paye' => 'decimal:2',
        'total_employee_uif' => 'decimal:2',
        'total_employer_uif' => 'decimal:2',
        'total_sdl' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'total_net' => 'decimal:2',
        'total_employer_cost' => 'decimal:2',

        'calculation_started_at' => 'datetime',
        'calculation_completed_at' => 'datetime',

        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(
            PayrollPeriod::class,
            'payroll_period_id'
        );
    }

    public function payrollItems(): HasMany
    {
        return $this->hasMany(
            PayrollItem::class,
            'payroll_run_id'
        );
    }

    public function paymentRuns(): HasMany
    {
        return $this->hasMany(
            PaymentRun::class,
            'payroll_run_id'
        );
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'approved_by'
        );
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'paid_by'
        );
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'cancelled_by'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Status Helpers
    |--------------------------------------------------------------------------
    */

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isCalculating(): bool
    {
        return $this->status === 'calculating';
    }

    public function isCalculated(): bool
    {
        return $this->status === 'calculated';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /*
    |--------------------------------------------------------------------------
    | Workflow
    |--------------------------------------------------------------------------
    */

    public function canCalculate(): bool
    {
        return in_array(
            $this->status,
            [
                'draft',
                'calculating',
            ],
            true
        );
    }

    public function canApprove(): bool
    {
        return $this->status === 'calculated';
    }

    /*
     * Direct payroll payment is deliberately no longer the
     * normal payment workflow.
     *
     * Payroll must go through PaymentRun.
     */
    public function canPay(): bool
    {
        return $this->status === 'approved';
    }

    public function canCancel(): bool
    {
        return in_array(
            $this->status,
            [
                'draft',
                'calculated',
            ],
            true
        );
    }

    public function isLocked(): bool
    {
        return in_array(
            $this->status,
            [
                'approved',
                'paid',
                'cancelled',
            ],
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Display
    |--------------------------------------------------------------------------
    */

    public function getDisplayNameAttribute(): string
    {
        return 'Payroll Run #' . $this->run_number;
    }

    public function getPeriodDisplayAttribute(): string
    {
        if (!$this->payrollPeriod) {
            return '';
        }

        return $this->payrollPeriod->display_name;
    }

    /*
    |--------------------------------------------------------------------------
    | Reset Totals
    |--------------------------------------------------------------------------
    */

    public function resetTotals(): void
    {
        $this->employee_count = 0;

        $this->total_gross = 0;
        $this->total_paye = 0;
        $this->total_employee_uif = 0;
        $this->total_employer_uif = 0;
        $this->total_sdl = 0;
        $this->total_deductions = 0;
        $this->total_net = 0;
        $this->total_employer_cost = 0;
    }
    public function staffMembers(): BelongsToMany
{
    return $this->belongsToMany(
        Staff::class,
        'payroll_run_staff',
        'payroll_run_id',
        'staff_id'
    )->withTimestamps();
}
}
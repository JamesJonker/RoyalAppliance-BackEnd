<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | Mass Assignment
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'financial_year_start',
        'financial_month',
        'calendar_month',
        'calendar_year',
        'period_start',
        'period_end',
        'pay_date',
        'status',
        'approved_by',
        'approved_at',
        'paid_by',
        'paid_at',
        'closed_by',
        'closed_at',
        'notes',
    ];

    /*
    |--------------------------------------------------------------------------
    | Casts
    |--------------------------------------------------------------------------
    */

    protected $casts = [
        'financial_year_start' => 'integer',
        'financial_month' => 'integer',
        'calendar_month' => 'integer',
        'calendar_year' => 'integer',

        'period_start' => 'date',
        'period_end' => 'date',
        'pay_date' => 'date',

        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Payroll Runs
    |--------------------------------------------------------------------------
    |
    | One payroll period can have more than one payroll run.
    |
    | Example:
    |
    | September 2026
    |     ├── Payroll Run #1
    |     └── Payroll Run #2
    |
    | This allows us to retain a history if a payroll needs to be
    | recalculated before final approval.
    |
    */

    public function payrollRuns(): HasMany
    {
        return $this->hasMany(PayrollRun::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Approved By
    |--------------------------------------------------------------------------
    */

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Paid By
    |--------------------------------------------------------------------------
    */

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Closed By
    |--------------------------------------------------------------------------
    */

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Financial Year Label
    |--------------------------------------------------------------------------
    |
    | Example:
    |
    | financial_year_start = 2026
    |
    | returns:
    |
    | 2026/2027
    |
    */

    public function getFinancialYearAttribute(): string
    {
        return $this->financial_year_start . '/' .
            ($this->financial_year_start + 1);
    }

    /*
    |--------------------------------------------------------------------------
    | Display Name
    |--------------------------------------------------------------------------
    |
    | Example:
    |
    | September 2026
    |
    */

    public function getDisplayNameAttribute(): string
    {
        return $this->period_start->format('F Y');
    }

    /*
    |--------------------------------------------------------------------------
    | Financial Month Name
    |--------------------------------------------------------------------------
    */

    public function getFinancialMonthNameAttribute(): string
    {
        return $this->period_start->format('F');
    }

    /*
    |--------------------------------------------------------------------------
    | Status Helpers
    |--------------------------------------------------------------------------
    */

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
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

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /*
    |--------------------------------------------------------------------------
    | Permission Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Can payroll be calculated?
     */
    public function canCalculate(): bool
    {
        return in_array($this->status, [
            'open',
            'processing',
        ], true);
    }

    /**
     * Can payroll be approved?
     */
    public function canApprove(): bool
    {
        return $this->status === 'calculated';
    }

    /**
     * Can payroll be paid?
     */
    public function canPay(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Can payroll be closed?
     */
    public function canClose(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Is this period locked?
     *
     * Approved, paid and closed payroll should not be
     * casually recalculated.
     */
    public function isLocked(): bool
    {
        return in_array($this->status, [
            'approved',
            'paid',
            'closed',
        ], true);
    }
}
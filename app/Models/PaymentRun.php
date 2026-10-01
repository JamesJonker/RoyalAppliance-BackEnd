<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_run_id',
        'run_number',
        'status',

        'employee_count',
        'total_amount',
        'valid_count',
        'invalid_count',

        'validation_started_at',
        'validation_completed_at',

        'approved_by',
        'approved_at',

        'export_file_name',
        'exported_at',
        'exported_by',

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
        'total_amount' => 'decimal:2',

        'valid_count' => 'integer',
        'invalid_count' => 'integer',

        'validation_started_at' => 'datetime',
        'validation_completed_at' => 'datetime',

        'approved_at' => 'datetime',

        'exported_at' => 'datetime',

        'paid_at' => 'datetime',

        'cancelled_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(
            PayrollRun::class,
            'payroll_run_id'
        );
    }

    public function paymentItems(): HasMany
    {
        return $this->hasMany(
            PaymentItem::class,
            'payment_run_id'
        );
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'approved_by'
        );
    }

    public function exportedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'exported_by'
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

    public function isValidating(): bool
    {
        return $this->status === 'validating';
    }

    public function isValidated(): bool
    {
        return $this->status === 'validated';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isExported(): bool
    {
        return $this->status === 'exported';
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
    | Workflow Helpers
    |--------------------------------------------------------------------------
    */

public function canValidate(): bool
{
    return in_array(
        $this->status,
        [
            'draft',
            'validating',
            'validated',
        ],
        true
    );
}

    public function canApprove(): bool
    {
        return $this->status === 'validated'
            && (int) $this->invalid_count === 0
            && (int) $this->valid_count > 0;
    }

    public function canExport(): bool
    {
        return $this->status === 'approved';
    }

    public function canMarkAsPaid(): bool
    {
        return $this->status === 'exported';
    }

    public function canCancel(): bool
    {
        return in_array(
            $this->status,
            [
                'draft',
                'validated',
                'approved',
            ],
            true
        );
    }

    public function isLocked(): bool
    {
        return in_array(
            $this->status,
            [
                'exported',
                'paid',
                'cancelled',
            ],
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Totals
    |--------------------------------------------------------------------------
    */

    public function recalculateTotals(): void
    {
        /*
         * employee_count represents every payment item
         * in this payment run.
         */
        $this->employee_count = $this->paymentItems()
            ->count();

        /*
         * Valid payments include payments already marked
         * as paid.
         */
        $this->valid_count = $this->paymentItems()
            ->whereIn(
                'status',
                [
                    'valid',
                    'paid',
                ]
            )
            ->count();

        /*
         * Invalid payments.
         */
        $this->invalid_count = $this->paymentItems()
            ->where(
                'status',
                'invalid'
            )
            ->count();

        /*
         * Total amount is the amount that will actually
         * be paid.
         */
        $this->total_amount = $this->paymentItems()
            ->whereIn(
                'status',
                [
                    'valid',
                    'paid',
                ]
            )
            ->sum('payment_amount');

        $this->save();
    }

    public function resetTotals(): void
    {
        $this->employee_count = 0;
        $this->total_amount = 0;
        $this->valid_count = 0;
        $this->invalid_count = 0;

        $this->validation_started_at = null;
        $this->validation_completed_at = null;

        $this->save();
    }

    /*
    |--------------------------------------------------------------------------
    | Display Helpers
    |--------------------------------------------------------------------------
    */

    public function getDisplayNameAttribute(): string
    {
        return 'Payment Run #' . $this->run_number;
    }

    public function getPeriodDisplayAttribute(): ?string
    {
        if (
            !$this->payrollRun ||
            !$this->payrollRun->payrollPeriod
        ) {
            return null;
        }

        return $this->payrollRun
            ->payrollPeriod
            ->display_name;
    }
}
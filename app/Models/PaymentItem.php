<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_run_id',
        'payroll_item_id',
        'staff_id',

        /*
        |--------------------------------------------------------------------------
        | Employee Snapshot
        |--------------------------------------------------------------------------
        */

        'employee_number',
        'first_name',
        'last_name',

        /*
        |--------------------------------------------------------------------------
        | Bank Snapshot
        |--------------------------------------------------------------------------
        */

        'bank_name',
        'account_holder_name',
        'account_number',
        'account_type',
        'branch_code',
        'payment_method',

        /*
        |--------------------------------------------------------------------------
        | Amounts
        |--------------------------------------------------------------------------
        */

        'payroll_net_salary',
        'payment_amount',

        /*
        |--------------------------------------------------------------------------
        | Status
        |--------------------------------------------------------------------------
        */

        'status',
        'validation_errors',

        'validated_at',
        'paid_at',

        'notes',
    ];

    protected $casts = [
        'payroll_net_salary' => 'decimal:2',
        'payment_amount' => 'decimal:2',

        'validated_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function paymentRun(): BelongsTo
    {
        return $this->belongsTo(
            PaymentRun::class,
            'payment_run_id'
        );
    }

    public function payrollItem(): BelongsTo
    {
        return $this->belongsTo(
            PayrollItem::class,
            'payroll_item_id'
        );
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(
            Staff::class,
            'staff_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Status Helpers
    |--------------------------------------------------------------------------
    */

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isValid(): bool
    {
        return $this->status === 'valid';
    }

    public function isInvalid(): bool
    {
        return $this->status === 'invalid';
    }

    public function isExcluded(): bool
    {
        return $this->status === 'excluded';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /*
    |--------------------------------------------------------------------------
    | Payment Helpers
    |--------------------------------------------------------------------------
    */

    public function amountMatchesPayroll(): bool
    {
        return round(
            (float) $this->payroll_net_salary,
            2
        ) === round(
            (float) $this->payment_amount,
            2
        );
    }

    public function hasBankDetails(): bool
    {
        return !empty($this->bank_name)
            && !empty($this->account_holder_name)
            && !empty($this->account_number)
            && !empty($this->account_type)
            && !empty($this->branch_code)
            && !empty($this->payment_method);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getEmployeeNameAttribute(): string
    {
        return trim(
            ($this->first_name ?? '') .
            ' ' .
            ($this->last_name ?? '')
        );
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format(
            (float) $this->payment_amount,
            2,
            '.',
            ','
        );
    }

    public function getMaskedAccountNumberAttribute(): string
    {
        $account = (string) $this->account_number;

        if (strlen($account) <= 4) {
            return $account;
        }

        return str_repeat(
            '*',
            strlen($account) - 4
        ) . substr(
            $account,
            -4
        );
    }
}
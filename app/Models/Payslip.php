<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    use HasFactory;


    protected $fillable = [

        'staff_id',

        'month',
        'year',

        'gross_salary',

        'paye',
        'uif',

        'deductions',
        'net_salary',

        'ytd_gross',
        'ytd_deductions',
        'ytd_net',

        'generated_at',
        'pdf_path',

        'pay_date',
        'period_ending',

        /*
        |--------------------------------------------------------------------------
        | FINAL / PRORATED PAYSLIP
        |--------------------------------------------------------------------------
        */

        'is_prorated',
        'termination_date',

        'days_worked',
        'daily_rate',
        'worked_gross',

        'leave_days_due',
        'leave_daily_rate',

        'gross_leave_pay',

        'leave_paye',
        'leave_uif',

        'net_leave_pay',

        'gross_remuneration',

    ];


    protected $casts = [


        'gross_salary' =>
            'decimal:2',

        'paye' =>
            'decimal:2',

        'uif' =>
            'decimal:2',

        'deductions' =>
            'decimal:2',

        'net_salary' =>
            'decimal:2',

        'ytd_gross' =>
            'decimal:2',

        'ytd_deductions' =>
            'decimal:2',

        'ytd_net' =>
            'decimal:2',

        'generated_at' =>
            'datetime',

        'pay_date' =>
            'date',

        'period_ending' =>
            'date',

        'is_prorated' =>
            'boolean',

        'termination_date' =>
            'date',

        'days_worked' =>
            'decimal:2',

        'daily_rate' =>
            'decimal:2',

        'worked_gross' =>
            'decimal:2',

        'leave_days_due' =>
            'decimal:2',

        'leave_daily_rate' =>
            'decimal:2',

        'gross_leave_pay' =>
            'decimal:2',

        'leave_paye' =>
            'decimal:2',

        'leave_uif' =>
            'decimal:2',

        'net_leave_pay' =>
            'decimal:2',

        'gross_remuneration' =>
            'decimal:2',

    ];


    public function staff(): BelongsTo
    {
        return $this->belongsTo(
            Staff::class,
            'staff_id'
        );
    }
}
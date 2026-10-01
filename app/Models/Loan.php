<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id',
        'amount',
        'months',
        'monthly_deduction',
        'remaining_amount',
        'months_remaining',
        'start_month',
        'status',
    ];


    public function staff()
    {
        return $this->belongsTo(
            Staff::class,
            'staff_id'
        );
    }


    public function repayments()
    {
        return $this->hasMany(
            LoanRepayment::class,
            'loan_id'
        );
    }


    public function isActive()
    {
        return $this->status === 'active';
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanRepayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id',
        'month_number',
        'deduction_amount',
        'status',
    ];


    public function loan()
    {
        return $this->belongsTo(
            Loan::class,
            'loan_id'
        );
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffBankDetails extends Model
{
    use HasFactory;

    protected $table = 'staff_bank_details';

    protected $fillable = [
        'staff_id',
        'bank_name',
        'account_holder_name',
        'account_number',
        'account_type',
        'branch_code',
        'payment_method',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(
            Staff::class,
            'staff_id'
        );
    }
}
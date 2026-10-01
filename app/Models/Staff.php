<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Staff extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_number',

        'first_name',
        'last_name',

        'Id_number',
        'tax_number',

        'passport_number',
        'passport_country',

        'date_of_birth',

        'contact_no',
        'email',

        'position',
        'employment_type',

        'branch',

        'salary',
        'start_date',

        'work_schedule_id',

        'street',
        'complex',
        'suburb',
        'postal_code',
    ];

    protected $casts = [
        'start_date' => 'date',
        'date_of_birth' => 'date',
        'salary' => 'decimal:2',
    ];

    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(
            WorkSchedule::class
        );
    }

    public function bankDetails(): HasOne
    {
        return $this->hasOne(
            StaffBankDetails::class,
            'staff_id'
        );
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(
            LeaveBalance::class
        );
    }

    public function loans(): HasMany
    {
        return $this->hasMany(
            Loan::class
        );
    }

    public function leave(): HasMany
    {
        return $this->hasMany(
            StaffLeave::class
        );
    }
}
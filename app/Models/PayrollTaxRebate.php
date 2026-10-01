<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollTaxRebate extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_tax_year_id',
        'rebate_type',
        'minimum_age',
        'rebate_amount',
        'tax_threshold',
    ];

    protected $casts = [
        'minimum_age' => 'integer',
        'rebate_amount' => 'decimal:2',
        'tax_threshold' => 'decimal:2',
    ];

    /**
     * Tax year this rebate belongs to.
     */
    public function taxYear(): BelongsTo
    {
        return $this->belongsTo(
            PayrollTaxYear::class,
            'payroll_tax_year_id'
        );
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollTaxBracket extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_tax_year_id',
        'lower_limit',
        'upper_limit',
        'base_tax',
        'marginal_rate',
        'threshold',
        'bracket_order',
    ];

    protected $casts = [
        'lower_limit' => 'decimal:2',
        'upper_limit' => 'decimal:2',
        'base_tax' => 'decimal:2',
        'marginal_rate' => 'decimal:6',
        'threshold' => 'decimal:2',
        'bracket_order' => 'integer',
    ];

    /**
     * Tax year this bracket belongs to.
     */
    public function taxYear(): BelongsTo
    {
        return $this->belongsTo(
            PayrollTaxYear::class,
            'payroll_tax_year_id'
        );
    }
}
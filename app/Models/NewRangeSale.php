<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewRangeSale extends Model
{
    use HasFactory;

    protected $table = 'new_range_sales';

    protected $fillable = [
        'sales_import_id',
        'product_id',
        'branch_id',
        'sale_date',
        'original_plu',
        'description',
        'quantity',
        'sales_excl',
        'sales_incl',
        'unit_cost_excl',
        'total_cost_excl',
        'gross_profit',
        'gp_percentage',
        'day_period',
        'source_sheet',
        'source_row',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'quantity' => 'decimal:2',
        'sales_excl' => 'decimal:2',
        'sales_incl' => 'decimal:2',
        'unit_cost_excl' => 'decimal:2',
        'total_cost_excl' => 'decimal:2',
        'gross_profit' => 'decimal:2',
        'gp_percentage' => 'decimal:4',
        'source_row' => 'integer',
    ];

    public function salesImport(): BelongsTo
    {
        return $this->belongsTo(
            NewRangeSalesImport::class,
            'sales_import_id'
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(
            NewRangeProduct::class,
            'product_id'
        );
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(
            NewRangeBranch::class,
            'branch_id'
        );
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewRangeStockMovement extends Model
{
    use HasFactory;

    protected $table = 'new_range_stock_movements';

    protected $fillable = [
        'product_id',
        'branch_id',
        'movement_type',
        'movement_date',
        'quantity',
        'reference_number',
        'unit_cost_excl',
        'unit_cost_incl',
        'transfer_group_id',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'movement_date' => 'date',
        'quantity' => 'decimal:2',
        'unit_cost_excl' => 'decimal:2',
        'unit_cost_incl' => 'decimal:2',
    ];

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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }
}
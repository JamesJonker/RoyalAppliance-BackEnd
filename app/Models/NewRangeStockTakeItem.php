<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewRangeStockTakeItem extends Model
{
    use HasFactory;


    protected $fillable = [

        'stock_take_id',

        'product_id',

        'system_quantity',

        'physical_quantity',

        'variance_quantity',

    ];


    protected $casts = [

        'system_quantity' =>
            'decimal:2',

        'physical_quantity' =>
            'decimal:2',

        'variance_quantity' =>
            'decimal:2',

    ];


    public function stockTake(): BelongsTo
    {
        return $this->belongsTo(
            NewRangeStockTake::class,
            'stock_take_id'
        );
    }


    public function product(): BelongsTo
    {
        return $this->belongsTo(
            NewRangeProduct::class,
            'product_id'
        );
    }
}
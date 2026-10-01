<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewRangeMinimumStockLevel extends Model
{
    use HasFactory;


    protected $fillable = [

        'product_id',
        'branch_id',

        'minimum_quantity',
        'maximum_quantity',

    ];


    protected $casts = [

        'minimum_quantity' =>
            'decimal:2',

        'maximum_quantity' =>
            'decimal:2',

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
}
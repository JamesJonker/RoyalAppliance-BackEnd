<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewRangeOrderItem extends Model
{
    use HasFactory;


    protected $fillable = [

        'order_id',

        'product_id',

        'branch_id',

        'brand',

        'system_soh',

        'minimum_quantity',

        'maximum_quantity',

        'suggested_quantity',

        'order_quantity',

    ];


    protected $casts = [

        'system_soh' =>
            'decimal:2',

        'minimum_quantity' =>
            'decimal:2',

        'maximum_quantity' =>
            'decimal:2',

        'suggested_quantity' =>
            'decimal:2',

        'order_quantity' =>
            'decimal:2',

    ];
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewRangeOrder extends Model
{
    use HasFactory;


    public const STATUS_DRAFT =
        'draft';

    public const STATUS_EXPORTED =
        'exported';


    protected $fillable = [

        'order_number',

        'order_date',

        'status',

        'notes',

        'created_by',

        'exported_at',

    ];


    protected $casts = [

        'order_date' =>
            'date',

        'exported_at' =>
            'datetime',

    ];


    public function items(): HasMany
    {
        return $this->hasMany(
            NewRangeOrderItem::class,
            'order_id'
        );
    }
}
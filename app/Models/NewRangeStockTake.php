<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewRangeStockTake extends Model
{
    use HasFactory;


    public const STATUS_DRAFT =
        'draft';

    public const STATUS_COMPLETED =
        'completed';

    public const STATUS_CANCELLED =
        'cancelled';


    protected $fillable = [

        'branch_id',

        'stock_take_number',

        'stock_take_date',

        'status',

        'notes',

        'created_by',

        'completed_by',

        'completed_at',

        'cancelled_by',

        'cancelled_at',

        'cancellation_reason',

    ];


    protected $casts = [

        'stock_take_date' =>
            'date',

        'completed_at' =>
            'datetime',

        'cancelled_at' =>
            'datetime',

    ];


    public function branch(): BelongsTo
    {
        return $this->belongsTo(
            NewRangeBranch::class,
            'branch_id'
        );
    }


    public function items(): HasMany
    {
        return $this->hasMany(
            NewRangeStockTakeItem::class,
            'stock_take_id'
        );
    }


    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }


    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'completed_by'
        );
    }


    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'cancelled_by'
        );
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewRangeIbt extends Model
{
    use HasFactory;


    protected $fillable = [

        'transfer_group_id',
        'reference',

        'from_branch_id',
        'to_branch_id',

        'transfer_date',

        'status',

        'created_by',
        'sent_at',

        'received_by',
        'received_at',

        'notes',

    ];


    protected $casts = [

        'transfer_date' =>
            'date',

        'sent_at' =>
            'datetime',

        'received_at' =>
            'datetime',

    ];


    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(
            NewRangeBranch::class,
            'from_branch_id'
        );
    }


    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(
            NewRangeBranch::class,
            'to_branch_id'
        );
    }


    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }


    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'received_by'
        );
    }
}
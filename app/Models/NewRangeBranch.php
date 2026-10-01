<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewRangeBranch extends Model
{
    use HasFactory;

    protected $table = 'new_range_branches';

    protected $fillable = [
        'name',
        'sales_sheet_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function stockMovements(): HasMany
    {
        return $this->hasMany(
            NewRangeStockMovement::class,
            'branch_id'
        );
    }

    public function sales(): HasMany
    {
        return $this->hasMany(
            NewRangeSale::class,
            'branch_id'
        );
    }
}
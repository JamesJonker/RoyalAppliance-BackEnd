<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewRangeProduct extends Model
{
    use HasFactory;

    protected $table = 'new_range_products';

    protected $fillable = [
        'model_number',
        'description',
        'cost_excl',
        'cost_incl',
        'retail_incl',
        'is_active',
    ];

    protected $casts = [
        'cost_excl' => 'decimal:2',
        'cost_incl' => 'decimal:2',
        'retail_incl' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function stockMovements(): HasMany
    {
        return $this->hasMany(
            NewRangeStockMovement::class,
            'product_id'
        );
    }

    public function sales(): HasMany
    {
        return $this->hasMany(
            NewRangeSale::class,
            'product_id'
        );
    }
}
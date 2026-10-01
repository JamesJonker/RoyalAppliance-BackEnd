<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockLevel extends Model
{
    public function shop() {
        return $this->belongsTo(Shop::class);
    }
    
    public function productVariant() {
        return $this->belongsTo(ProductVariant::class);
    }
    
    use HasFactory;
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    public function stockLevels() {
        return $this->hasMany(StockLevel::class);
    }
    
    use HasFactory;
}

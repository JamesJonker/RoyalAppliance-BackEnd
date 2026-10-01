<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Condition extends Model
{
    protected $fillable = ['brand_id', 'code', 'name'];
    
    public function brand() {
        return $this->belongsTo(Brand::class);
    }
    
    public function productVariants() {
        return $this->hasMany(ProductVariant::class);
    }
    use HasFactory;
}

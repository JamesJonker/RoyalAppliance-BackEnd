<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    public function productVariants() {
        return $this->hasMany(ProductVariant::class);
    }
    use HasFactory;
}

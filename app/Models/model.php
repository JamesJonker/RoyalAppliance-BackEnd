<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class model extends Model
{
    protected $fillable = [
        'brand_id',
        'Model_no',
        'Description',
    ];

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }
    use HasFactory;
}

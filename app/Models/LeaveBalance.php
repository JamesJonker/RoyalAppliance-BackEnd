<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveBalance extends Model
{
    protected $fillable = ['staff_id', 'type', 'balance'];

    public function staff() {
        return $this->belongsTo(Staff::class);
    }
}

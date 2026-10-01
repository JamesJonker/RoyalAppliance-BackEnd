<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewRangeSalesImport extends Model
{
    use HasFactory;

    protected $table = 'new_range_sales_imports';

    protected $fillable = [
        'source_filename',
        'file_hash',
        'sales_date',
        'status',
        'rows_read',
        'rows_imported',
        'rows_skipped',
        'rows_failed',
        'imported_by',
        'imported_at',
        'notes',
    ];

    protected $casts = [
        'sales_date' => 'date',
        'imported_at' => 'datetime',
        'rows_read' => 'integer',
        'rows_imported' => 'integer',
        'rows_skipped' => 'integer',
        'rows_failed' => 'integer',
    ];

    public function sales(): HasMany
    {
        return $this->hasMany(
            NewRangeSale::class,
            'sales_import_id'
        );
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'imported_by'
        );
    }
}
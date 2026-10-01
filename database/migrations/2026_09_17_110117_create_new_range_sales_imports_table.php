<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('new_range_sales_imports', function (Blueprint $table) {

            $table->id();


            /*
            |--------------------------------------------------------------------------
            | Source Workbook
            |--------------------------------------------------------------------------
            */

            $table->string(
                'source_filename'
            );


            /*
            |--------------------------------------------------------------------------
            | Duplicate File Protection
            |--------------------------------------------------------------------------
            |
            | SHA-256 hash of the uploaded workbook.
            |
            | This prevents the exact same workbook from accidentally
            | being imported twice.
            |
            */

            $table->string(
                'file_hash',
                64
            )->unique();


            /*
            |--------------------------------------------------------------------------
            | Sales Date
            |--------------------------------------------------------------------------
            |
            | This can be null because individual rows also contain their
            | actual sale date.
            |
            */

            $table->date(
                'sales_date'
            )->nullable();


            /*
            |--------------------------------------------------------------------------
            | Import Status
            |--------------------------------------------------------------------------
            |
            | Expected values:
            |
            | processing
            | completed
            | completed_with_errors
            | failed
            |
            */

            $table->string(
                'status',
                30
            )->default('processing');


            /*
            |--------------------------------------------------------------------------
            | Import Statistics
            |--------------------------------------------------------------------------
            */

            $table->unsignedInteger(
                'rows_read'
            )->default(0);

            $table->unsignedInteger(
                'rows_imported'
            )->default(0);

            $table->unsignedInteger(
                'rows_skipped'
            )->default(0);

            $table->unsignedInteger(
                'rows_failed'
            )->default(0);


            /*
            |--------------------------------------------------------------------------
            | User Audit
            |--------------------------------------------------------------------------
            */

            $table->foreignId(
                'imported_by'
            )
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();


            /*
            |--------------------------------------------------------------------------
            | Import Date / Time
            |--------------------------------------------------------------------------
            */

            $table->timestamp(
                'imported_at'
            )->nullable();


            /*
            |--------------------------------------------------------------------------
            | Notes
            |--------------------------------------------------------------------------
            */

            $table->text(
                'notes'
            )->nullable();

            $table->timestamps();


            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index(
                'sales_date',
                'nr_import_sales_date_idx'
            );

            $table->index(
                'status',
                'nr_import_status_idx'
            );
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(
            'new_range_sales_imports'
        );
    }
};
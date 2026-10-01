<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'new_range_stock_takes',
            function (Blueprint $table) {

                $table
                    ->foreignId('cancelled_by')
                    ->nullable()
                    ->after('completed_at')
                    ->constrained('users')
                    ->nullOnDelete();

                $table
                    ->timestamp('cancelled_at')
                    ->nullable()
                    ->after('cancelled_by');

                $table
                    ->text('cancellation_reason')
                    ->nullable()
                    ->after('cancelled_at');

            }
        );
    }


    public function down(): void
    {
        Schema::table(
            'new_range_stock_takes',
            function (Blueprint $table) {

                $table->dropForeign([
                    'cancelled_by'
                ]);

                $table->dropColumn([
                    'cancelled_by',
                    'cancelled_at',
                    'cancellation_reason',
                ]);

            }
        );
    }
};
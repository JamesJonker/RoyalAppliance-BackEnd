<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_leave', function (Blueprint $table) {

            /*
             * full = normal scheduled day
             * half = half of the scheduled day
             */
            $table->string('duration_type')
                ->default('full')
                ->after('days_taken');

            /*
             * Optional:
             *
             * morning
             * afternoon
             *
             * This gives us flexibility later.
             */
            $table->string('half_day_period')
                ->nullable()
                ->after('duration_type');
        });
    }

    public function down(): void
    {
        Schema::table('staff_leave', function (Blueprint $table) {

            $table->dropColumn([
                'duration_type',
                'half_day_period',
            ]);

        });
    }
};
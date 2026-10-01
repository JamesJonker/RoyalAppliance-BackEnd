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
        Schema::create('work_schedule_days', function (Blueprint $table) {
            $table->id();

            $table->foreignId('work_schedule_id')
                ->constrained('work_schedules')
                ->onDelete('cascade');

            /*
             * ISO day of week:
             *
             * 1 = Monday
             * 2 = Tuesday
             * 3 = Wednesday
             * 4 = Thursday
             * 5 = Friday
             * 6 = Saturday
             * 7 = Sunday
             */
            $table->unsignedTinyInteger('day_of_week');

            /*
             * 0.00 = non-working day
             * 0.50 = half day
             * 1.00 = full day
             */
            $table->decimal('day_fraction', 3, 2)->default(0);

            $table->timestamps();

            /*
             * Only one entry per day for each schedule.
             */
            $table->unique(
                ['work_schedule_id', 'day_of_week'],
                'schedule_day_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_schedule_days');
    }
};
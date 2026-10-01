<?php

namespace Database\Seeders;

use App\Models\WorkSchedule;
use Illuminate\Database\Seeder;

class WorkScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /*
         * ==========================================
         * PROTEC / WAREHOUSE
         * ==========================================
         */

        $protec = WorkSchedule::updateOrCreate(
            [
                'name' => 'Protec Warehouse',
            ],
            [
                'description' =>
                    'Monday to Friday. No weekend leave days.',
                'is_active' => true,
            ]
        );

        $protecDays = [
            1 => 1.00, // Monday
            2 => 1.00, // Tuesday
            3 => 1.00, // Wednesday
            4 => 1.00, // Thursday
            5 => 1.00, // Friday
            6 => 0.00, // Saturday
            7 => 0.00, // Sunday
        ];

        foreach ($protecDays as $dayOfWeek => $fraction) {
            $protec->days()->updateOrCreate(
                [
                    'day_of_week' => $dayOfWeek,
                ],
                [
                    'day_fraction' => $fraction,
                ]
            );
        }


        /*
         * ==========================================
         * STORE
         * ==========================================
         */

        $store = WorkSchedule::updateOrCreate(
            [
                'name' => 'Store',
            ],
            [
                'description' =>
                    'Monday to Friday full day and Saturday half day.',
                'is_active' => true,
            ]
        );

        $storeDays = [
            1 => 1.00, // Monday
            2 => 1.00, // Tuesday
            3 => 1.00, // Wednesday
            4 => 1.00, // Thursday
            5 => 1.00, // Friday
            6 => 0.50, // Saturday
            7 => 0.00, // Sunday
        ];

        foreach ($storeDays as $dayOfWeek => $fraction) {
            $store->days()->updateOrCreate(
                [
                    'day_of_week' => $dayOfWeek,
                ],
                [
                    'day_fraction' => $fraction,
                ]
            );
        }
    }
}
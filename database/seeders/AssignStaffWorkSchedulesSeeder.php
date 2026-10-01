<?php

namespace Database\Seeders;

use App\Models\Staff;
use App\Models\WorkSchedule;
use Illuminate\Database\Seeder;

class AssignStaffWorkSchedulesSeeder extends Seeder
{
    /**
     * Assign work schedules to existing staff
     * based on their branch.
     */
    public function run(): void
    {
        /*
         * Get the schedules we created earlier.
         */
        $storeSchedule = WorkSchedule::where(
            'name',
            'Store'
        )->firstOrFail();

        $protecSchedule = WorkSchedule::where(
            'name',
            'Protec Warehouse'
        )->firstOrFail();


        /*
         * ==========================================
         * CHINA GATE = STORE
         * ==========================================
         */
        Staff::where('branch', 'China Gate')
            ->update([
                'work_schedule_id' => $storeSchedule->id,
            ]);


        /*
         * ==========================================
         * PROTEC PARK = PROTEC WAREHOUSE
         * ==========================================
         */
        Staff::where('branch', 'Protec Park')
            ->update([
                'work_schedule_id' => $protecSchedule->id,
            ]);
    }
}
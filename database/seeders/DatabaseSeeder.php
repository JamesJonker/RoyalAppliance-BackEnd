<?php

namespace Database\Seeders;
use Database\Seeders\LeaveSeeder;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{

    public function run()
    {
       // $this->call(MasterDataSeeder::class);
        
        $this->call([
            LeaveSeeder::class,
        ]);

        $this->call([
            WorkScheduleSeeder::class,
        ]);
                $this->call([
            WorkScheduleSeeder::class,
            AssignStaffWorkSchedulesSeeder::class,
        ]);
                $this->call([
            RolePermissionSeeder::class,
        ]);
        $this->call([
            StatutorySettingsSeeder::class,
        ]);
    }
}


<?php

namespace Database\Seeders;

use App\Models\LeaveEntitlementRule;
use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /*
         * ==========================================
         * ANNUAL LEAVE
         * ==========================================
         */

        $annualLeave = LeaveType::updateOrCreate(
            [
                'code' => 'ANNUAL',
            ],
            [
                'name' => 'Annual Leave',
                'description' => 'Annual paid leave entitlement.',
                'is_paid' => true,
                'is_active' => true,
            ]
        );

        /*
         * 1 - 4 years = 16 days
         */
        LeaveEntitlementRule::updateOrCreate(
            [
                'leave_type_id' => $annualLeave->id,
                'minimum_service_years' => 1,
            ],
            [
                'maximum_service_years' => 4,
                'days_entitled' => 16,
                'is_active' => true,
            ]
        );

        /*
         * 5 - 9 years = 18 days
         */
        LeaveEntitlementRule::updateOrCreate(
            [
                'leave_type_id' => $annualLeave->id,
                'minimum_service_years' => 5,
            ],
            [
                'maximum_service_years' => 9,
                'days_entitled' => 18,
                'is_active' => true,
            ]
        );

        /*
         * 10+ years = 21 days
         */
        LeaveEntitlementRule::updateOrCreate(
            [
                'leave_type_id' => $annualLeave->id,
                'minimum_service_years' => 10,
            ],
            [
                'maximum_service_years' => null,
                'days_entitled' => 21,
                'is_active' => true,
            ]
        );

        /*
         * ==========================================
         * SICK LEAVE
         * ==========================================
         *
         * We create the leave type now so the system
         * supports it, but we are NOT assigning an
         * entitlement rule yet.
         *
         * We can configure its rules separately later.
         */

        LeaveType::updateOrCreate(
            [
                'code' => 'SICK',
            ],
            [
                'name' => 'Sick Leave',
                'description' => 'Sick leave.',
                'is_paid' => true,
                'is_active' => true,
            ]
        );

        /*
         * ==========================================
         * FAMILY RESPONSIBILITY LEAVE
         * ==========================================
         */

        LeaveType::updateOrCreate(
            [
                'code' => 'FAMILY',
            ],
            [
                'name' => 'Family Responsibility Leave',
                'description' => 'Family responsibility leave.',
                'is_paid' => true,
                'is_active' => true,
            ]
        );

        /*
         * ==========================================
         * UNPAID LEAVE
         * ==========================================
         */

        LeaveType::updateOrCreate(
            [
                'code' => 'UNPAID',
            ],
            [
                'name' => 'Unpaid Leave',
                'description' => 'Unpaid leave.',
                'is_paid' => false,
                'is_active' => true,
            ]
        );
    }
}
<?php

namespace Database\Seeders;

use App\Models\StatutorySetting;
use Illuminate\Database\Seeder;

class StatutorySettingsSeeder extends Seeder
{
    /**
     * Seed the statutory payroll settings.
     */
    public function run(): void
    {
        $effectiveFrom = '2026-03-01';

        $settings = [

            /*
            |--------------------------------------------------------------------------
            | UIF
            |--------------------------------------------------------------------------
            */

            [
                'setting_key' => 'uif_employee_rate',
                'setting_name' => 'UIF Employee Contribution Rate',
                'category' => 'UIF',
                'value' => '0.01',
                'value_type' => 'decimal',
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                'is_active' => true,
                'description' => 'Employee UIF contribution rate of 1%.',
            ],

            [
                'setting_key' => 'uif_employer_rate',
                'setting_name' => 'UIF Employer Contribution Rate',
                'category' => 'UIF',
                'value' => '0.01',
                'value_type' => 'decimal',
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                'is_active' => true,
                'description' => 'Employer UIF contribution rate of 1%.',
            ],

            [
                'setting_key' => 'uif_monthly_ceiling',
                'setting_name' => 'UIF Monthly Earnings Ceiling',
                'category' => 'UIF',
                'value' => '17712.00',
                'value_type' => 'decimal',
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                'is_active' => true,
                'description' => 'Maximum monthly remuneration used when calculating UIF contributions.',
            ],

            /*
            |--------------------------------------------------------------------------
            | SDL
            |--------------------------------------------------------------------------
            */

            [
                'setting_key' => 'sdl_employer_rate',
                'setting_name' => 'SDL Employer Contribution Rate',
                'category' => 'SDL',
                'value' => '0.01',
                'value_type' => 'decimal',
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                'is_active' => true,
                'description' => 'Skills Development Levy rate of 1%.',
            ],

            [
                'setting_key' => 'sdl_annual_exemption_threshold',
                'setting_name' => 'SDL Annual Payroll Exemption Threshold',
                'category' => 'SDL',
                'value' => '500000.00',
                'value_type' => 'decimal',
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                'is_active' => true,
                'description' => 'Employers with annual remuneration below this threshold are exempt from SDL.',
            ],

        ];

        foreach ($settings as $setting) {
            StatutorySetting::updateOrCreate(
                [
                    'setting_key' => $setting['setting_key'],
                    'effective_from' => $setting['effective_from'],
                ],
                $setting
            );
        }
    }
}
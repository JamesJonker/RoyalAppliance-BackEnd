<?php

namespace Database\Seeders;

use App\Models\PayrollTaxBracket;
use App\Models\PayrollTaxRebate;
use App\Models\PayrollTaxYear;
use Illuminate\Database\Seeder;


class PayrollTaxSeeder extends Seeder
{
    /**
     * Seed the South African PAYE tax tables.
     */
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | 2026/2027 Tax Year
        |--------------------------------------------------------------------------
        |
        | SARS tax year:
        |
        | 1 March 2026
        | to
        | 28 February 2027
        |
        */

        $taxYear = PayrollTaxYear::updateOrCreate(
            [
                'tax_year' => '2026/2027',
            ],
            [
                'starts_on' => '2026-03-01',
                'ends_on' => '2027-02-28',
                'is_active' => true,
                'description' => 'South African individual income tax year 2026/2027.',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Tax brackets
        |--------------------------------------------------------------------------
        */

        $brackets = [

            [
                'lower_limit' => 0.00,
                'upper_limit' => 245100.00,
                'base_tax' => 0.00,
                'marginal_rate' => 0.18,
                'threshold' => 0.00,
                'bracket_order' => 1,
            ],

            [
                'lower_limit' => 245100.01,
                'upper_limit' => 383100.00,
                'base_tax' => 44118.00,
                'marginal_rate' => 0.26,
                'threshold' => 245100.00,
                'bracket_order' => 2,
            ],

            [
                'lower_limit' => 383100.01,
                'upper_limit' => 530200.00,
                'base_tax' => 79998.00,
                'marginal_rate' => 0.31,
                'threshold' => 383100.00,
                'bracket_order' => 3,
            ],

            [
                'lower_limit' => 530200.01,
                'upper_limit' => 695800.00,
                'base_tax' => 125599.00,
                'marginal_rate' => 0.36,
                'threshold' => 530200.00,
                'bracket_order' => 4,
            ],

            [
                'lower_limit' => 695800.01,
                'upper_limit' => 887000.00,
                'base_tax' => 185215.00,
                'marginal_rate' => 0.39,
                'threshold' => 695800.00,
                'bracket_order' => 5,
            ],

            [
                'lower_limit' => 887000.01,
                'upper_limit' => 1878600.00,
                'base_tax' => 259783.00,
                'marginal_rate' => 0.41,
                'threshold' => 887000.00,
                'bracket_order' => 6,
            ],

            [
                'lower_limit' => 1878600.01,
                'upper_limit' => null,
                'base_tax' => 666339.00,
                'marginal_rate' => 0.45,
                'threshold' => 1878600.00,
                'bracket_order' => 7,
            ],

        ];

        foreach ($brackets as $bracket) {

            PayrollTaxBracket::updateOrCreate(
                [
                    'payroll_tax_year_id' => $taxYear->id,
                    'bracket_order' => $bracket['bracket_order'],
                ],
                [
                    'lower_limit' => $bracket['lower_limit'],
                    'upper_limit' => $bracket['upper_limit'],
                    'base_tax' => $bracket['base_tax'],
                    'marginal_rate' => $bracket['marginal_rate'],
                    'threshold' => $bracket['threshold'],
                ]
            );
        }
                /*
        |--------------------------------------------------------------------------
        | Tax rebates and thresholds
        |--------------------------------------------------------------------------
        */

        $rebates = [

            [
                'rebate_type' => 'primary',
                'minimum_age' => 0,
                'rebate_amount' => 17820.00,
                'tax_threshold' => 99000.00,
            ],

            [
                'rebate_type' => 'secondary',
                'minimum_age' => 65,
                'rebate_amount' => 9765.00,
                'tax_threshold' => 153250.00,
            ],

            [
                'rebate_type' => 'tertiary',
                'minimum_age' => 75,
                'rebate_amount' => 3249.00,
                'tax_threshold' => 171300.00,
            ],

        ];

        foreach ($rebates as $rebate) {

            PayrollTaxRebate::updateOrCreate(
                [
                    'payroll_tax_year_id' => $taxYear->id,
                    'rebate_type' => $rebate['rebate_type'],
                ],
                [
                    'minimum_age' => $rebate['minimum_age'],
                    'rebate_amount' => $rebate['rebate_amount'],
                    'tax_threshold' => $rebate['tax_threshold'],
                ]
            );
        }
    }
}
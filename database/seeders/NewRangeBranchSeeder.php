<?php

namespace Database\Seeders;

use App\Models\NewRangeBranch;
use Illuminate\Database\Seeder;

class NewRangeBranchSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [

            [
                'name' => 'Protec',
                'sales_sheet_name' => 'Protec Sales',
            ],

            [
                'name' => 'China Gate',
                'sales_sheet_name' => 'China Gate Sales',
            ],

            [
                'name' => 'West',
                'sales_sheet_name' => 'West Sales',
            ],

            [
                'name' => 'Bosworth',
                'sales_sheet_name' => 'Bosworth Sales',
            ],

            [
                'name' => 'Soweto',
                'sales_sheet_name' => 'Soweto Sales',
            ],
        ];

        foreach ($branches as $branch) {

            NewRangeBranch::updateOrCreate(
                [
                    'name' => $branch['name']
                ],
                [
                    'sales_sheet_name' =>
                        $branch['sales_sheet_name'],

                    'is_active' => true,
                ]
            );
        }
    }
}
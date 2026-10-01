<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MasterDataSeeder extends Seeder
{
    public function run()
    {
        // Brands
        $brands = [
            'AEG', 'Alliance', 'Beko', 'Candy', 'Defy', 'Grundig', 'Hisense',
            'Kelvinator', 'Mercury', 'Media', 'Mora', 'MTN', 'Samsung',
            'Toshiba', 'Univa', 'Vivo', 'Whirlpool'
        ];

        foreach ($brands as $brand) {
            DB::table('brands')->insert([
                'name' => $brand,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Categories
        $categories = [
            'Aircon', 'Cell', 'Cooking', 'Freezer', 'Fridge',
            'Small', 'Entertainment', 'Washing'
        ];

        $categoryIds = [];
        foreach ($categories as $category) {
            $id = DB::table('categories')->insertGetId([
                'name' => $category,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $categoryIds[$category] = $id;
        }

        // Subcategories
        $subcategories = [
            'Cooking' => [
                'Gass Freestanding', 'Electric freestanding Stove', 'Gass/Electric Stove',
                'Oven', 'Hob', 'Extractor', 'Microwave', 'Warmer drawer',
                '2 Plate mini Oven', 'Airfryer',
            ],
            'Freezer' => ['Chest freezer', 'Upright'],
            'Fridge' => ['Combi', 'Side by side', 'Uprite'],
            'Entertainment' => ['TV', 'Sound Bar', 'Radio'],
            'Washing' => ['Front Loaders', 'Top Loaders', 'Dish Washer', 'Dryers', 'Twin Tubs'],
        ];

        foreach ($subcategories as $category => $subs) {
            $categoryId = $categoryIds[$category] ?? null;
            if ($categoryId) {
                foreach ($subs as $sub) {
                    DB::table('subcategories')->insert([
                        'category_id' => $categoryId,
                        'name' => $sub,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // Conditions per brand
        $brandConditions = [
            'Defy' => ['RS SERVICE', 'RF NEW IN BOX', 'RF5 BOX DAMMAGE'],
            'Beko' => ['RS SERVICE', 'RF NEW IN BOX', 'RF5 BOX DAMMAGE'],
            'Grundig' => ['RS SERVICE', 'RF NEW IN BOX', 'RF5 BOX DAMMAGE'],
            'Hisense' => ['HB2 NEW IN BOX', 'HB3 NEW BOX DAMMAGE', 'HS SERVICE', 'S SERVICE BER'],
            'Univa' => ['RFC NEW', 'UN SECONDS'],
        ];

        $conditionIds = [];

        foreach ($brandConditions as $brand => $conditions) {
            $brandId = DB::table('brands')->where('name', $brand)->value('id');
            foreach ($conditions as $condition) {
                $code = explode(' ', $condition)[0];  // get first word as code
                $id = DB::table('conditions')->insertGetId([
                    'brand_id' => $brandId,
                    'name' => $condition,
                    'code' => $code,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $conditionIds["$brand|$condition"] = $id;
            }
        }
        
        

        // Gradings (assigning to Defy RS SERVICE condition)
        $defyRsServiceId = $conditionIds['Defy|RS SERVICE'] ?? null;
        $gradings = ['A', 'A-', 'B', 'B+', 'B-', 'C', 'C+', 'C-'];

        if ($defyRsServiceId) {
            foreach ($gradings as $grading) {
                DB::table('gradings')->insert([
                    'name' => $grading,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}

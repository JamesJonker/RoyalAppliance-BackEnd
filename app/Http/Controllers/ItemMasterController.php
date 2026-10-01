<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Brand;
use App\Models\Category;
use App\Models\SubCategory;
use App\Models\Grading;

class ItemMasterController extends Controller
{
    public function index()
    {
        return response()->json([
            'brands' => Brand::all(),
            'categories' => Category::all(),
            'sub_categories' => SubCategory::all(),
            'gradings' => Grading::all(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'brands' => 'array',
            'categories' => 'array',
            'sub_categories' => 'array',
            'gradings' => 'array',
        ]);

        foreach ($request->brands ?? [] as $brand) {
            Brand::create(['name' => $brand]);
        }

        foreach ($request->categories ?? [] as $category) {
            Category::create($category);
        }

        foreach ($request->sub_categories ?? [] as $subCategory) {
            SubCategory::create($subCategory);
        }

        foreach ($request->gradings ?? [] as $grading) {
            Grading::create(['name' => $grading]);
        }

        return response()->json(['message' => 'Items stored successfully']);
    }
}

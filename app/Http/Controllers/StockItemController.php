<?php

namespace App\Http\Controllers;

use App\Models\StockItem;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Condition;
use App\Models\StockItemCondition;
use App\Models\StockItemConditionGrading;
use App\Models\Grading;
use App\Services\StockItemService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockItemController extends Controller
{
    protected $service;

    public function __construct(StockItemService $service)
    {
        $this->service = $service;
    }

    // public function index()
    // {
    //     return StockItem::with([
    //         'brand',
    //         'category',
    //         'subcategory',
    //         'stockItemConditions.condition',
    //         'stockItemConditions.gradings'
    //     ])->get();
    // }
    public function storeBulk(Request $request)
{
    DB::beginTransaction();

    try {
        foreach ($request->all() as $itemData) {
            // $stockItem = StockItem::create([
            //     'brand_id' => $itemData['brand_id'],
            //     'category_id' => $itemData['category_id'],
            //     'subcategory_id' => $itemData['subcategory_id'],
            //     'grading_id' => null, // Grading handled in nested structure
            //     'model' => $itemData['model'],
            //     'description' => $itemData['description'],
            //     'cost_excl' => null,
            //     'cost_incl' => null,
            //     'price' => null,
            // ]);

            foreach ($itemData['conditions'] as $conditionId => $conditionData) {
                // $condition = StockItemCondition::create([
                //     'stock_item_id' => $stockItem->id,
                //     'condition_id' => $conditionId,
                //     'cost_excl' => $conditionData['cost_excl'],
                //     'cost_incl' => $conditionData['cost_incl'],
                // ]);

                foreach ($conditionData as $gradingId => $price) {
                    if (in_array($gradingId, ['cost_excl', 'cost_incl'])) {
                        continue;
                    }

                    // StockItemConditionGrading::create([
                    //     'stock_item_id' => $stockItem->id,
                    //     'stock_item_condition_id' => $condition->id,
                    //     'grading_id' => $gradingId,
                    //     'price' => $price,
                    // ]);
                }
            }
        }

        DB::commit();

        return response()->json([
            'message' => 'Bulk stock items saved successfully'
        ]);

    } catch (\Exception $e) {
        DB::rollBack();

        return response()->json([
            'message' => 'Error saving bulk stock items',
            'error' => $e->getMessage(),
        ], 500);
    }
}

    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            // Create the stock item
            // $stockItem = StockItem::create([
            //     'brand_id' => $request->brand_id,
            //     'category_id' => $request->category_id,
            //     'subcategory_id' => $request->subcategory_id,
            //     'grading_id' => $request->grading_id,
            //     'model' => $request->model,
            //     'description' => $request->description,
            //     'cost_excl' => $request->cost_excl,
            //     'cost_incl' => $request->cost_incl,
            //     'price' => $request->price,
            // ]);

            // If no conditions provided, ensure cost_excl and cost_incl are NOT null
            if (empty($request->conditions)) {
                // if (is_null($stockItem->cost_excl) || is_null($stockItem->cost_incl)) {
                //     throw new \Exception('cost_excl and cost_incl are required when no conditions are provided.');
                // }
            }

            // Handle conditions if provided
            foreach ($request->conditions as $conditionId => $conditionData) {
                // $condition = StockItemCondition::create([
                //     'stock_item_id' => $stockItem->id,
                //     'condition_id' => $conditionId,
                //     'cost_excl' => $conditionData['cost_excl'],
                //     'cost_incl' => $conditionData['cost_incl'],
                // ]);

                foreach ($conditionData as $key => $value) {
                    if (in_array($key, ['cost_excl', 'cost_incl'])) {
                        continue;
                    }

                    // StockItemConditionGrading::create([
                    //     'stock_item_id' => $stockItem->id,  
                    //     'stock_item_condition_id' => $condition->id,
                    //     'grading_id' => $key,
                    //     'price' => $value,
                    // ]);
                }
            }

            DB::commit();

            // return response()->json([
            //     'message' => 'Stock item saved successfully',
            //     'data' => $stockItem->load([
            //         'brand',
            //         'category',
            //         'subcategory',
            //         'stockItemConditions.condition',
            //         'stockItemConditions.gradings'
            //     ]),
            // ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error saving stock item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // public function show($id)
    // {
    //     $stockItem = StockItem::with([
    //         'brand',
    //         'category',
    //         'subcategory',
    //         'stockItemConditions.condition',
    //         'stockItemConditions.gradings'
    //     ])->findOrFail($id);

    //     return response()->json($stockItem);
    // }

    public function update(Request $request, $id)
    {
        // $item = StockItem::findOrFail($id);

        $validated = $request->validate([
            'model' => 'sometimes|string',
            'description' => 'nullable|string',
            'cost_excl' => 'sometimes|numeric|nullable',
            'cost_incl' => 'sometimes|numeric|nullable',
            'price' => 'sometimes|numeric|nullable',
        ]);

        // $item->update($validated);

        return response()->json();
    }

    public function destroy($id)
    {
        // $item = StockItem::findOrFail($id);
        // $item->delete();

        return response()->json(null, 204);
    }

    public function metadata()
    {
        return response()->json([
            'brands' => Brand::all(),
            // 'categories' => Category::all(),
            // 'subcategories' => Subcategory::all(),
            // 'conditions' => Condition::all(),
            // 'gradings' => Grading::all(),
        ]);
    }

    public function getConditionsForBrand($brandId)
    {
        $conditions = Condition::where('brand_id', $brandId)->get();
        return response()->json($conditions);
    }
}

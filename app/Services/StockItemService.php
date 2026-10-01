<?php

namespace App\Services;

use App\Models\StockItem;
use Illuminate\Support\Facades\DB;

class StockItemService
{
    public function createStockItem(array $data)
    {
        return DB::transaction(function () use ($data) {
            $stockItem = StockItem::create([
                'brand_id' => $data['brand_id'],
                'category_id' => $data['category_id'],
                'subcategory_id' => $data['subcategory_id'],
                'grading_id' => $data['grading_id'] ?? null,
                'model' => $data['model'],
                'description' => $data['description'],
                'cost_excl' => $data['cost_excl'] ?? 0,
                'cost_incl' => $data['cost_incl'] ?? 0,
                'price' => $data['price'] ?? 0,
            ]);

            if (!empty($data['conditions'])) {
                foreach ($data['conditions'] as $conditionId => $conditionData) {
                    $stockItemCondition = $stockItem->conditions()->create([
                        'condition_id' => $conditionId,
                        'cost_excl' => $conditionData['cost_excl'],
                        'cost_incl' => $conditionData['cost_incl'],
                    ]);

                    if (!empty($conditionData['gradings'])) {
                        foreach ($conditionData['gradings'] as $gradingId => $price) {
                            $stockItemCondition->gradings()->create([
                                'grading_id' => $gradingId,
                                'price' => $price,
                            ]);
                        }
                    }
                }
            }

            return $stockItem;
        });
    }
}

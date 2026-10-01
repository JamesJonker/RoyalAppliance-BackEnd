<?php

namespace App\Http\Controllers;

use App\Models\NewRangeBranch;
use App\Models\NewRangeMinimumStockLevel;
use App\Models\NewRangeProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

class NewRangeMinimumStockLevelController extends Controller
{
    /**
     * =========================================================
     * LIST MINIMUM / MAXIMUM STOCK LEVELS
     * =========================================================
     *
     * GET /api/new-range/minimum-stock-levels
     *
     */
    public function index(): JsonResponse
    {
        try {

            /*
            |--------------------------------------------------------------------------
            | Branches
            |--------------------------------------------------------------------------
            */

            $branches =
                NewRangeBranch::query()
                    ->when(
                        Schema::hasColumn(
                            'new_range_branches',
                            'is_active'
                        ),
                        fn ($query) =>
                            $query->where(
                                'is_active',
                                true
                            )
                    )
                    ->orderBy(
                        'name'
                    )
                    ->get([
                        'id',
                        'name'
                    ]);


            /*
            |--------------------------------------------------------------------------
            | Products
            |--------------------------------------------------------------------------
            */

            $productQuery =
                NewRangeProduct::query();


            if (
                Schema::hasColumn(
                    'new_range_products',
                    'is_active'
                )
            ) {

                $productQuery
                    ->where(
                        'is_active',
                        true
                    );

            } elseif (
                Schema::hasColumn(
                    'new_range_products',
                    'active'
                )
            ) {

                $productQuery
                    ->where(
                        'active',
                        true
                    );
            }


            $products =
                $productQuery
                    ->orderBy(
                        'model_number'
                    )
                    ->get([
                        'id',
                        'model_number',
                        'description'
                    ]);


            /*
            |--------------------------------------------------------------------------
            | Existing stock levels
            |--------------------------------------------------------------------------
            */

            $levels =
                NewRangeMinimumStockLevel::query()
                    ->get();


            /*
            |--------------------------------------------------------------------------
            | Lookup Map
            |--------------------------------------------------------------------------
            |
            | Example:
            |
            | 28|4 =>
            | [
            |   minimum_quantity => 4,
            |   maximum_quantity => 10
            | ]
            |
            */

            $levelMap =
                [];


            foreach (
                $levels
                as $level
            ) {

                $key =
                    $level->product_id
                    . '|'
                    . $level->branch_id;


                $levelMap[
                    $key
                ] = [

                    'minimum_quantity' =>
                        (float)
                        $level->minimum_quantity,

                    'maximum_quantity' =>
                        $level->maximum_quantity !== null
                            ? (float)
                                $level->maximum_quantity
                            : 0,

                ];
            }


            /*
            |--------------------------------------------------------------------------
            | Build matrix rows
            |--------------------------------------------------------------------------
            */

            $rows =
                [];


            foreach (
                $products
                as $product
            ) {

                $branchLevels =
                    [];


                foreach (
                    $branches
                    as $branch
                ) {

                    $key =
                        $product->id
                        . '|'
                        . $branch->id;


                    $level =
                        $levelMap[
                            $key
                        ]
                        ??
                        [
                            'minimum_quantity' => 0,
                            'maximum_quantity' => 0,
                        ];


                    $branchLevels[
                        $branch->id
                    ] = [

                        'minimum_quantity' =>
                            (float)
                            $level[
                                'minimum_quantity'
                            ],

                        'maximum_quantity' =>
                            (float)
                            $level[
                                'maximum_quantity'
                            ],

                    ];
                }


                $rows[] = [

                    'product_id' =>
                        $product->id,

                    'model_number' =>
                        $product->model_number,

                    'description' =>
                        $product->description,

                    'branches' =>
                        $branchLevels,

                ];
            }


            return response()->json([

                'branches' =>
                    $branches,

                'products' =>
                    $products,

                'rows' =>
                    $rows,

            ]);

        } catch (
            Throwable $exception
        ) {

            return response()->json([

                'message' =>
                    'Unable to load minimum and maximum stock levels.',

                'error' =>
                    $exception->getMessage(),

            ], 500);
        }
    }


    /**
     * =========================================================
     * SAVE MINIMUM / MAXIMUM STOCK LEVELS
     * =========================================================
     *
     * PUT /api/new-range/minimum-stock-levels
     *
     * Payload:
     *
     * {
     *   "levels": [
     *      {
     *          "product_id": 28,
     *          "branch_id": 4,
     *          "minimum_quantity": 4,
     *          "maximum_quantity": 10
     *      }
     *   ]
     * }
     *
     */
    public function update(
        Request $request
    ): JsonResponse {

        try {

            /*
            |--------------------------------------------------------------------------
            | Validation
            |--------------------------------------------------------------------------
            */

            $validated =
                $request->validate([

                    'levels' =>
                        [
                            'required',
                            'array'
                        ],

                    'levels.*.product_id' =>
                        [
                            'required',
                            'integer',
                            'exists:new_range_products,id'
                        ],

                    'levels.*.branch_id' =>
                        [
                            'required',
                            'integer',
                            'exists:new_range_branches,id'
                        ],

                    'levels.*.minimum_quantity' =>
                        [
                            'required',
                            'numeric',
                            'min:0'
                        ],

                    'levels.*.maximum_quantity' =>
                        [
                            'nullable',
                            'numeric',
                            'min:0'
                        ],

                ]);


            /*
            |--------------------------------------------------------------------------
            | Additional validation
            |--------------------------------------------------------------------------
            |
            | Maximum should not be less than minimum.
            |
            */

            foreach (
                $validated['levels']
                as $index => $row
            ) {

                $minimum =
                    (float)
                    $row['minimum_quantity'];


                $maximum =
                    isset(
                        $row['maximum_quantity']
                    )
                    &&
                    $row['maximum_quantity'] !== null
                        ? (float)
                            $row['maximum_quantity']
                        : 0;


                if (
                    $maximum > 0
                    &&
                    $maximum < $minimum
                ) {

                    throw ValidationException::withMessages([

                        "levels.$index.maximum_quantity" =>
                            'Maximum quantity cannot be less than minimum quantity.',

                    ]);
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Save in one transaction
            |--------------------------------------------------------------------------
            */

            $saved =
                DB::transaction(
                    function () use (
                        $validated
                    ) {

                        $count =
                            0;


                        foreach (
                            $validated[
                                'levels'
                            ]
                            as $row
                        ) {

                            $maximumQuantity =
                                isset(
                                    $row[
                                        'maximum_quantity'
                                    ]
                                )
                                &&
                                $row[
                                    'maximum_quantity'
                                ] !== null
                                    ? $row[
                                        'maximum_quantity'
                                    ]
                                    : 0;


                            NewRangeMinimumStockLevel::updateOrCreate(

                                [
                                    'product_id' =>
                                        $row[
                                            'product_id'
                                        ],

                                    'branch_id' =>
                                        $row[
                                            'branch_id'
                                        ],
                                ],

                                [
                                    'minimum_quantity' =>
                                        $row[
                                            'minimum_quantity'
                                        ],

                                    'maximum_quantity' =>
                                        $maximumQuantity,
                                ]

                            );


                            $count++;
                        }


                        return
                            $count;
                    }
                );


            return response()->json([

                'message' =>
                    'Minimum and maximum stock levels saved successfully.',

                'records_saved' =>
                    $saved,

            ]);

        } catch (
            ValidationException $exception
        ) {

            throw
                $exception;

        } catch (
            Throwable $exception
        ) {

            return response()->json([

                'message' =>
                    'Unable to save minimum and maximum stock levels.',

                'error' =>
                    $exception->getMessage(),

            ], 500);
        }
    }
}
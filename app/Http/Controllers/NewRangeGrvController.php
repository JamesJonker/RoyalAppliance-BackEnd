<?php

namespace App\Http\Controllers;

use App\Models\NewRangeBranch;
use App\Models\NewRangeProduct;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

class NewRangeGrvController extends Controller
{
    /**
     * =========================================================
     * CREATE GRV
     * =========================================================
     *
     * POST /api/new-range/grv
     *
     */
    public function store(
        Request $request
    ): JsonResponse {

        try {

            $validated =
                $request->validate([

                    'branch_id' => [
                        'required',
                        'integer',
                        'exists:new_range_branches,id',
                    ],

                    'movement_date' => [
                        'required',
                        'date',
                    ],

                    'reference' => [
                        'required',
                        'string',
                        'max:255',
                    ],

                    'notes' => [
                        'nullable',
                        'string',
                        'max:2000',
                    ],

                    'update_product_cost' => [
                        'nullable',
                        'boolean',
                    ],

                    'items' => [
                        'required',
                        'array',
                        'min:1',
                    ],

                    'items.*.product_id' => [
                        'required',
                        'integer',
                        'exists:new_range_products,id',
                    ],

                    'items.*.quantity' => [
                        'required',
                        'numeric',
                        'gt:0',
                    ],

                    'items.*.unit_cost_excl' => [
                        'nullable',
                        'numeric',
                        'min:0',
                    ],

                    'items.*.unit_cost_incl' => [
                        'nullable',
                        'numeric',
                        'min:0',
                    ],

                ]);


            $quantityColumn =
                $this->getQuantityColumn();


            $referenceColumn =
                $this->getReferenceColumn();


            /*
            |--------------------------------------------------------------------------
            | Prevent duplicate GRV reference for same branch
            |--------------------------------------------------------------------------
            */

            $duplicateQuery =
                DB::table(
                    'new_range_stock_movements'
                )
                ->where(
                    'branch_id',
                    $validated['branch_id']
                )
                ->where(
                    'movement_type',
                    'GRV'
                )
                ->where(
                    $referenceColumn,
                    $validated['reference']
                );


            if (
                $duplicateQuery->exists()
            ) {

                return response()->json([

                    'message' =>
                        'A GRV with this reference already exists for this branch.',

                ], 422);

            }


            $movementDate =
                Carbon::parse(
                    $validated['movement_date']
                )->toDateString();


            $updateProductCost =
                $validated[
                    'update_product_cost'
                ] ?? true;


            $createdBy =
                $request->user()?->id;


            $result =
                DB::transaction(
                    function () use (
                        $validated,
                        $quantityColumn,
                        $referenceColumn,
                        $movementDate,
                        $updateProductCost,
                        $createdBy
                    ) {

                        $created =
                            [];

                        $totalUnits =
                            0;

                        $totalValueExcl =
                            0;

                        $totalValueIncl =
                            0;


                        foreach (
                            $validated['items']
                            as $item
                        ) {

                            $product =
                                NewRangeProduct::findOrFail(
                                    $item['product_id']
                                );


                            $quantity =
                                (float)
                                $item['quantity'];


                            /*
                            |--------------------------------------------------------------------------
                            | Determine cost
                            |--------------------------------------------------------------------------
                            */

                            $unitCostExcl =
                                isset(
                                    $item['unit_cost_excl']
                                )
                                    ? (float)
                                        $item['unit_cost_excl']
                                    : null;


                            $unitCostIncl =
                                isset(
                                    $item['unit_cost_incl']
                                )
                                    ? (float)
                                        $item['unit_cost_incl']
                                    : null;


                            if (
                                $unitCostExcl === null
                                &&
                                $unitCostIncl === null
                            ) {

                                $unitCostExcl =
                                    (float)
                                    (
                                        $product->cost_excl
                                        ?? 0
                                    );


                                $unitCostIncl =
                                    (float)
                                    (
                                        $product->cost_incl
                                        ?? 0
                                    );

                            } elseif (
                                $unitCostExcl === null
                            ) {

                                $unitCostExcl =
                                    round(
                                        $unitCostIncl / 1.15,
                                        2
                                    );

                            } elseif (
                                $unitCostIncl === null
                            ) {

                                $unitCostIncl =
                                    round(
                                        $unitCostExcl * 1.15,
                                        2
                                    );

                            }


                            /*
                            |--------------------------------------------------------------------------
                            | Movement
                            |--------------------------------------------------------------------------
                            */

                            $movementData = [

                                'product_id' =>
                                    $product->id,

                                'branch_id' =>
                                    $validated['branch_id'],

                                'movement_type' =>
                                    'GRV',

                                'movement_date' =>
                                    $movementDate,

                                $quantityColumn =>
                                    $quantity,

                                $referenceColumn =>
                                    $validated['reference'],

                                'unit_cost_excl' =>
                                    $unitCostExcl,

                                'unit_cost_incl' =>
                                    $unitCostIncl,

                                'created_by' =>
                                    $createdBy,

                                'notes' =>
                                    $validated['notes']
                                    ?? null,

                                'created_at' =>
                                    now(),

                                'updated_at' =>
                                    now(),

                            ];


                            /*
                            |--------------------------------------------------------------------------
                            | Remove columns if DB does not contain them
                            |--------------------------------------------------------------------------
                            */

                            $movementData =
                                $this->filterMovementColumns(
                                    $movementData
                                );


                            $movementId =
                                DB::table(
                                    'new_range_stock_movements'
                                )
                                ->insertGetId(
                                    $movementData
                                );


                            /*
                            |--------------------------------------------------------------------------
                            | Update current product cost
                            |--------------------------------------------------------------------------
                            */

                            if (
                                $updateProductCost
                            ) {

                                $productUpdate =
                                    [];


                                if (
                                    Schema::hasColumn(
                                        'new_range_products',
                                        'cost_excl'
                                    )
                                ) {

                                    $productUpdate[
                                        'cost_excl'
                                    ] =
                                        $unitCostExcl;

                                }


                                if (
                                    Schema::hasColumn(
                                        'new_range_products',
                                        'cost_incl'
                                    )
                                ) {

                                    $productUpdate[
                                        'cost_incl'
                                    ] =
                                        $unitCostIncl;

                                }


                                if (
                                    !empty(
                                        $productUpdate
                                    )
                                ) {

                                    $product->update(
                                        $productUpdate
                                    );

                                }

                            }


                            $totalUnits +=
                                $quantity;


                            $totalValueExcl +=
                                (
                                    $quantity
                                    *
                                    $unitCostExcl
                                );


                            $totalValueIncl +=
                                (
                                    $quantity
                                    *
                                    $unitCostIncl
                                );


                            $created[] = [

                                'movement_id' =>
                                    $movementId,

                                'product_id' =>
                                    $product->id,

                                'model_number' =>
                                    $product->model_number,

                                'quantity' =>
                                    $quantity,

                                'unit_cost_excl' =>
                                    $unitCostExcl,

                                'unit_cost_incl' =>
                                    $unitCostIncl,

                            ];

                        }


                        return [

                            'items' =>
                                $created,

                            'total_units' =>
                                $totalUnits,

                            'total_value_excl' =>
                                round(
                                    $totalValueExcl,
                                    2
                                ),

                            'total_value_incl' =>
                                round(
                                    $totalValueIncl,
                                    2
                                ),

                        ];

                    }
                );


            $branch =
                NewRangeBranch::find(
                    $validated['branch_id']
                );


            return response()->json([

                'message' =>
                    'GRV created successfully.',

                'grv' => [

                    'branch_id' =>
                        $validated['branch_id'],

                    'branch_name' =>
                        $branch?->name,

                    'movement_date' =>
                        $movementDate,

                    'reference' =>
                        $validated['reference'],

                    'total_units' =>
                        $result['total_units'],

                    'total_value_excl' =>
                        $result['total_value_excl'],

                    'total_value_incl' =>
                        $result['total_value_incl'],

                    'items' =>
                        $result['items'],

                ],

            ], 201);

        } catch (
            ValidationException $exception
        ) {

            throw $exception;

        } catch (
            Throwable $exception
        ) {

            return response()->json([

                'message' =>
                    'Unable to create GRV.',

                'error' =>
                    $exception->getMessage(),

            ], 500);

        }
    }


    /**
     * =========================================================
     * QUANTITY COLUMN
     * =========================================================
     */

    private function getQuantityColumn(): string
    {
        if (
            Schema::hasColumn(
                'new_range_stock_movements',
                'quantity'
            )
        ) {

            return 'quantity';

        }


        if (
            Schema::hasColumn(
                'new_range_stock_movements',
                'qty'
            )
        ) {

            return 'qty';

        }


        throw new \RuntimeException(
            'Stock movement quantity column was not found.'
        );
    }


    /**
     * =========================================================
     * REFERENCE COLUMN
     * =========================================================
     */

    private function getReferenceColumn(): string
    {
        if (
            Schema::hasColumn(
                'new_range_stock_movements',
                'reference_number'
            )
        ) {

            return 'reference_number';

        }


        if (
            Schema::hasColumn(
                'new_range_stock_movements',
                'reference'
            )
        ) {

            return 'reference';

        }


        throw new \RuntimeException(
            'Stock movement reference column was not found.'
        );
    }


    /**
     * =========================================================
     * FILTER EXISTING MOVEMENT COLUMNS
     * =========================================================
     */

    private function filterMovementColumns(
        array $data
    ): array {

        return collect(
            $data
        )
        ->filter(
            fn (
                $value,
                $column
            ) =>
                Schema::hasColumn(
                    'new_range_stock_movements',
                    $column
                )
        )
        ->all();
    }
}
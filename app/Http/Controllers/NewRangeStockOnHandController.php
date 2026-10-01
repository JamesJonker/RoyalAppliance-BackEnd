<?php

namespace App\Http\Controllers;

use App\Models\NewRangeBranch;
use App\Models\NewRangeProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NewRangeStockOnHandController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Opening balance date
    |--------------------------------------------------------------------------
    |
    | Historical opening stock represents the position entering
    | 18 September 2026.
    |
    | Historical sales before this date are already reflected in the
    | opening stock and MUST NOT be deducted again.
    |
    */

    private const OPENING_STOCK_DATE =
        '2026-09-18';


    /**
     * =========================================================
     * STOCK ON HAND
     * =========================================================
     *
     * GET /api/new-range/stock-on-hand
     *
     * Optional filters:
     *
     * branch_id
     * product_id
     * include_zero = 1
     *
     */
    public function index(
        Request $request
    ): JsonResponse {

        /*
        |--------------------------------------------------------------------------
        | Filters
        |--------------------------------------------------------------------------
        */

        $branchId =
            $request->filled(
                'branch_id'
            )
                ? (int)
                    $request->branch_id
                : null;


        $productId =
            $request->filled(
                'product_id'
            )
                ? (int)
                    $request->product_id
                : null;


        $includeZero =
            $request->boolean(
                'include_zero',
                false
            );


        /*
        |--------------------------------------------------------------------------
        | Determine quantity column
        |--------------------------------------------------------------------------
        */

        $quantityColumn =
            $this->getQuantityColumn();


        /*
        |--------------------------------------------------------------------------
        | Branches
        |--------------------------------------------------------------------------
        */

        $branches =
            NewRangeBranch::query()
                ->where(
                    'is_active',
                    true
                )
                ->when(
                    $branchId,
                    fn ($query) =>
                        $query->where(
                            'id',
                            $branchId
                        )
                )
                ->orderBy(
                    'name'
                )
                ->get();


        /*
        |--------------------------------------------------------------------------
        | Products
        |--------------------------------------------------------------------------
        */

        $products =
            NewRangeProduct::query()
                ->when(
                    Schema::hasColumn(
                        'new_range_products',
                        'is_active'
                    ),
                    fn ($query) =>
                        $query->where(
                            'is_active',
                            true
                        )
                )
                ->when(
                    Schema::hasColumn(
                        'new_range_products',
                        'active'
                    ),
                    fn ($query) =>
                        $query->where(
                            'active',
                            true
                        )
                )
                ->when(
                    $productId,
                    fn ($query) =>
                        $query->where(
                            'id',
                            $productId
                        )
                )
                ->orderBy(
                    'model_number'
                )
                ->get();


        /*
        |--------------------------------------------------------------------------
        | Stock movements
        |--------------------------------------------------------------------------
        |
        | OPENING        +
        | GRV            +
        | IBT_IN         +
        | IBT_OUT        -
        | ADJUSTMENT_IN  +
        | ADJUSTMENT_OUT -
        |
        */

        $movements =
            DB::table(
                'new_range_stock_movements'
            )
                ->select(
                    'branch_id',
                    'product_id',
                    'movement_type',
                    DB::raw(
                        "SUM({$quantityColumn}) AS total_quantity"
                    )
                )
                ->whereDate(
                    'movement_date',
                    '>=',
                    self::OPENING_STOCK_DATE
                )
                ->when(
                    $branchId,
                    fn ($query) =>
                        $query->where(
                            'branch_id',
                            $branchId
                        )
                )
                ->when(
                    $productId,
                    fn ($query) =>
                        $query->where(
                            'product_id',
                            $productId
                        )
                )
                ->groupBy(
                    'branch_id',
                    'product_id',
                    'movement_type'
                )
                ->get();


        /*
        |--------------------------------------------------------------------------
        | Convert movements into lookup
        |--------------------------------------------------------------------------
        */

        $movementMap =
            [];


        foreach (
            $movements as $movement
        ) {

            $key =
                $movement->branch_id
                . '|'
                . $movement->product_id;


            if (
                !isset(
                    $movementMap[
                        $key
                    ]
                )
            ) {

                $movementMap[
                    $key
                ] = [

                    'opening' =>
                        0,

                    'grv' =>
                        0,

                    'ibt_in' =>
                        0,

                    'ibt_out' =>
                        0,

                    'adjustment_in' =>
                        0,

                    'adjustment_out' =>
                        0,

                ];

            }


            $qty =
                (float)
                $movement
                    ->total_quantity;


            switch (
                strtoupper(
                    $movement
                        ->movement_type
                )
            ) {

                case 'OPENING':

                    $movementMap[
                        $key
                    ][
                        'opening'
                    ] +=
                        $qty;

                    break;


                case 'GRV':

                    $movementMap[
                        $key
                    ][
                        'grv'
                    ] +=
                        $qty;

                    break;


                case 'IBT_IN':

                    $movementMap[
                        $key
                    ][
                        'ibt_in'
                    ] +=
                        $qty;

                    break;


                case 'IBT_OUT':

                    $movementMap[
                        $key
                    ][
                        'ibt_out'
                    ] +=
                        $qty;

                    break;


                case 'ADJUSTMENT_IN':

                    $movementMap[
                        $key
                    ][
                        'adjustment_in'
                    ] +=
                        $qty;

                    break;


                case 'ADJUSTMENT_OUT':

                    $movementMap[
                        $key
                    ][
                        'adjustment_out'
                    ] +=
                        $qty;

                    break;

            }

        }


        /*
        |--------------------------------------------------------------------------
        | Sales from opening date onward
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        |
        | Historical sales before 18 September 2026 are intentionally
        | ignored here because they are already reflected in OPENING.
        |
        */

        $sales =
            DB::table(
                'new_range_sales'
            )
                ->select(
                    'branch_id',
                    'product_id',
                    DB::raw(
                        'SUM(quantity) AS quantity_sold'
                    )
                )
                ->whereDate(
                    'sale_date',
                    '>=',
                    self::OPENING_STOCK_DATE
                )
                ->when(
                    $branchId,
                    fn ($query) =>
                        $query->where(
                            'branch_id',
                            $branchId
                        )
                )
                ->when(
                    $productId,
                    fn ($query) =>
                        $query->where(
                            'product_id',
                            $productId
                        )
                )
                ->groupBy(
                    'branch_id',
                    'product_id'
                )
                ->get();


        /*
        |--------------------------------------------------------------------------
        | Sales lookup
        |--------------------------------------------------------------------------
        */

        $salesMap =
            [];


        foreach (
            $sales as $sale
        ) {

            $key =
                $sale->branch_id
                . '|'
                . $sale->product_id;


            $salesMap[
                $key
            ] =
                (float)
                $sale
                    ->quantity_sold;

        }


        /*
        |--------------------------------------------------------------------------
        | Build SOH rows
        |--------------------------------------------------------------------------
        */

        $rows =
            [];


        $branchTotals =
            [];


        $summary = [

            'opening_units' =>
                0,

            'grv_units' =>
                0,

            'ibt_in_units' =>
                0,

            'ibt_out_units' =>
                0,

            'sales_units' =>
                0,

            'adjustment_in_units' =>
                0,

            'adjustment_out_units' =>
                0,

            'units_on_hand' =>
                0,

            'current_cost_value_excl' =>
                0,

            'current_cost_value_incl' =>
                0,

        ];


        foreach (
            $branches as $branch
        ) {

            /*
            |--------------------------------------------------------------------------
            | Branch totals
            |--------------------------------------------------------------------------
            */

            $branchTotals[
                $branch->id
            ] = [

                'branch_id' =>
                    $branch->id,

                'branch_name' =>
                    $branch->name,

                'opening_units' =>
                    0,

                'grv_units' =>
                    0,

                'ibt_in_units' =>
                    0,

                'ibt_out_units' =>
                    0,

                'sales_units' =>
                    0,

                'adjustment_in_units' =>
                    0,

                'adjustment_out_units' =>
                    0,

                'units_on_hand' =>
                    0,

                'current_cost_value_excl' =>
                    0,

                'current_cost_value_incl' =>
                    0,

            ];


            foreach (
                $products as $product
            ) {

                $key =
                    $branch->id
                    . '|'
                    . $product->id;


                $movement =
                    $movementMap[
                        $key
                    ] ?? [

                        'opening' =>
                            0,

                        'grv' =>
                            0,

                        'ibt_in' =>
                            0,

                        'ibt_out' =>
                            0,

                        'adjustment_in' =>
                            0,

                        'adjustment_out' =>
                            0,

                    ];


                $salesQty =
                    $salesMap[
                        $key
                    ] ?? 0;


                /*
                |--------------------------------------------------------------------------
                | SOH calculation
                |--------------------------------------------------------------------------
                */

                $soh =
                    $movement[
                        'opening'
                    ]
                    +
                    $movement[
                        'grv'
                    ]
                    +
                    $movement[
                        'ibt_in'
                    ]
                    -
                    $movement[
                        'ibt_out'
                    ]
                    -
                    $salesQty
                    +
                    $movement[
                        'adjustment_in'
                    ]
                    -
                    $movement[
                        'adjustment_out'
                    ];


                /*
                |--------------------------------------------------------------------------
                | Current product cost
                |--------------------------------------------------------------------------
                |
                | This is an indicative CURRENT stock valuation.
                |
                | It does not rewrite the historical cost snapshots.
                |
                */

                $currentCostExcl =
                    (float)
                    (
                        $product
                            ->cost_excl
                        ?? 0
                    );


                $currentCostIncl =
                    (float)
                    (
                        $product
                            ->cost_incl
                        ?? 0
                    );


                $stockValueExcl =
                    round(
                        $soh *
                        $currentCostExcl,
                        2
                    );


                $stockValueIncl =
                    round(
                        $soh *
                        $currentCostIncl,
                        2
                    );


                /*
                |--------------------------------------------------------------------------
                | Hide zero rows unless requested
                |--------------------------------------------------------------------------
                */

                if (
                    !$includeZero &&
                    abs(
                        $soh
                    ) < 0.0001
                ) {

                    continue;

                }


                $rows[] = [

                    'branch_id' =>
                        $branch->id,

                    'branch_name' =>
                        $branch->name,

                    'product_id' =>
                        $product->id,

                    'model_number' =>
                        $product
                            ->model_number,

                    'description' =>
                        $product
                            ->description,

                    'opening' =>
                        round(
                            $movement[
                                'opening'
                            ],
                            2
                        ),

                    'grv' =>
                        round(
                            $movement[
                                'grv'
                            ],
                            2
                        ),

                    'ibt_in' =>
                        round(
                            $movement[
                                'ibt_in'
                            ],
                            2
                        ),

                    'ibt_out' =>
                        round(
                            $movement[
                                'ibt_out'
                            ],
                            2
                        ),

                    'sales' =>
                        round(
                            $salesQty,
                            2
                        ),

                    'adjustment_in' =>
                        round(
                            $movement[
                                'adjustment_in'
                            ],
                            2
                        ),

                    'adjustment_out' =>
                        round(
                            $movement[
                                'adjustment_out'
                            ],
                            2
                        ),

                    'stock_on_hand' =>
                        round(
                            $soh,
                            2
                        ),

                    'current_unit_cost_excl' =>
                        round(
                            $currentCostExcl,
                            2
                        ),

                    'current_unit_cost_incl' =>
                        round(
                            $currentCostIncl,
                            2
                        ),

                    'current_stock_value_excl' =>
                        $stockValueExcl,

                    'current_stock_value_incl' =>
                        $stockValueIncl,

                ];


                /*
                |--------------------------------------------------------------------------
                | Summary totals
                |--------------------------------------------------------------------------
                */

                $summary[
                    'opening_units'
                ] +=
                    $movement[
                        'opening'
                    ];


                $summary[
                    'grv_units'
                ] +=
                    $movement[
                        'grv'
                    ];


                $summary[
                    'ibt_in_units'
                ] +=
                    $movement[
                        'ibt_in'
                    ];


                $summary[
                    'ibt_out_units'
                ] +=
                    $movement[
                        'ibt_out'
                    ];


                $summary[
                    'sales_units'
                ] +=
                    $salesQty;


                $summary[
                    'adjustment_in_units'
                ] +=
                    $movement[
                        'adjustment_in'
                    ];


                $summary[
                    'adjustment_out_units'
                ] +=
                    $movement[
                        'adjustment_out'
                    ];


                $summary[
                    'units_on_hand'
                ] +=
                    $soh;


                $summary[
                    'current_cost_value_excl'
                ] +=
                    $stockValueExcl;


                $summary[
                    'current_cost_value_incl'
                ] +=
                    $stockValueIncl;


                /*
                |--------------------------------------------------------------------------
                | Branch totals
                |--------------------------------------------------------------------------
                */

                $branchTotals[
                    $branch->id
                ][
                    'opening_units'
                ] +=
                    $movement[
                        'opening'
                    ];


                $branchTotals[
                    $branch->id
                ][
                    'grv_units'
                ] +=
                    $movement[
                        'grv'
                    ];


                $branchTotals[
                    $branch->id
                ][
                    'ibt_in_units'
                ] +=
                    $movement[
                        'ibt_in'
                    ];


                $branchTotals[
                    $branch->id
                ][
                    'ibt_out_units'
                ] +=
                    $movement[
                        'ibt_out'
                    ];


                $branchTotals[
                    $branch->id
                ][
                    'sales_units'
                ] +=
                    $salesQty;


                $branchTotals[
                    $branch->id
                ][
                    'adjustment_in_units'
                ] +=
                    $movement[
                        'adjustment_in'
                    ];


                $branchTotals[
                    $branch->id
                ][
                    'adjustment_out_units'
                ] +=
                    $movement[
                        'adjustment_out'
                    ];


                $branchTotals[
                    $branch->id
                ][
                    'units_on_hand'
                ] +=
                    $soh;


                $branchTotals[
                    $branch->id
                ][
                    'current_cost_value_excl'
                ] +=
                    $stockValueExcl;


                $branchTotals[
                    $branch->id
                ][
                    'current_cost_value_incl'
                ] +=
                    $stockValueIncl;

            }

        }


        /*
        |--------------------------------------------------------------------------
        | Round overall summary
        |--------------------------------------------------------------------------
        */

        foreach (
            $summary as $key => $value
        ) {

            $summary[
                $key
            ] =
                round(
                    $value,
                    2
                );

        }


        /*
        |--------------------------------------------------------------------------
        | Round branch totals
        |--------------------------------------------------------------------------
        */

        foreach (
            $branchTotals as $id => $branch
        ) {

            foreach (
                [
                    'opening_units',
                    'grv_units',
                    'ibt_in_units',
                    'ibt_out_units',
                    'sales_units',
                    'adjustment_in_units',
                    'adjustment_out_units',
                    'units_on_hand',
                    'current_cost_value_excl',
                    'current_cost_value_incl',
                ]
                as $field
            ) {

                $branchTotals[
                    $id
                ][
                    $field
                ] =
                    round(
                        $branch[
                            $field
                        ],
                        2
                    );

            }

        }


        /*
        |--------------------------------------------------------------------------
        | Sort rows
        |--------------------------------------------------------------------------
        */

        usort(
            $rows,
            function (
                array $a,
                array $b
            ) {

                $branchCompare =
                    strcmp(
                        $a[
                            'branch_name'
                        ],
                        $b[
                            'branch_name'
                        ]
                    );


                if (
                    $branchCompare !== 0
                ) {

                    return
                        $branchCompare;

                }


                return strcmp(
                    $a[
                        'model_number'
                    ],
                    $b[
                        'model_number'
                    ]
                );

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return response()->json([

            'as_of_date' =>
                now()
                    ->toDateString(),

            'opening_stock_date' =>
                self::OPENING_STOCK_DATE,

            'summary' =>
                $summary,

            'by_branch' =>
                array_values(
                    $branchTotals
                ),

            'stock' =>
                $rows,

            'options' => [

                'branches' =>
                    NewRangeBranch::query()
                        ->where(
                            'is_active',
                            true
                        )
                        ->orderBy(
                            'name'
                        )
                        ->get([
                            'id',
                            'name',
                        ]),

                'products' =>
                    NewRangeProduct::query()
                        ->orderBy(
                            'model_number'
                        )
                        ->get([
                            'id',
                            'model_number',
                            'description',
                        ]),

            ],

        ]);

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

            return
                'quantity';

        }


        if (
            Schema::hasColumn(
                'new_range_stock_movements',
                'qty'
            )
        ) {

            return
                'qty';

        }


        throw new \RuntimeException(
            'new_range_stock_movements does not contain a quantity or qty column.'
        );
    }
}
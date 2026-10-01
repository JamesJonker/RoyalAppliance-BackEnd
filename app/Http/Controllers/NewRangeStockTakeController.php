<?php

namespace App\Http\Controllers;

use App\Models\NewRangeProduct;
use App\Models\NewRangeStockTake;
use App\Models\NewRangeStockTakeItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

class NewRangeStockTakeController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | OPENING STOCK DATE
    |--------------------------------------------------------------------------
    |
    | This must match NewRangeStockOnHandController.
    |
    */

    private const OPENING_STOCK_DATE = '2026-09-18';


    /**
     * =========================================================
     * LIST STOCK TAKES
     * =========================================================
     *
     * GET /api/new-range/stock-takes
     *
     */
    public function index(
        Request $request
    ): JsonResponse {

        try {

            $query =
                NewRangeStockTake::query();


            if (
                $request->filled(
                    'branch_id'
                )
            ) {

                $query->where(
                    'branch_id',
                    (int) $request->branch_id
                );

            }


            if (
                $request->filled(
                    'status'
                )
            ) {

                $query->where(
                    'status',
                    $request->status
                );

            }


            $stockTakes =
                $query
                    ->orderByDesc(
                        'stock_take_date'
                    )
                    ->orderByDesc(
                        'id'
                    )
                    ->get();


            /*
            |--------------------------------------------------------------------------
            | Format History
            |--------------------------------------------------------------------------
            */

            $history =
                $stockTakes
                    ->map(
                        function (
                            NewRangeStockTake $stockTake
                        ) {

                            return [

                                'id' =>
                                    $stockTake->id,

                                'stock_take_number' =>
                                    $stockTake->stock_take_number,

                                'branch_id' =>
                                    $stockTake->branch_id,

                                'branch_name' =>
                                    $this->getBranchName(
                                        $stockTake->branch_id
                                    ),

                                'stock_take_date' =>
                                    optional(
                                        $stockTake->stock_take_date
                                    )
                                        ->format(
                                            'Y-m-d'
                                        ),

                                'status' =>
                                    $stockTake->status,

                                'notes' =>
                                    $stockTake->notes,

                                'created_at' =>
                                    optional(
                                        $stockTake->created_at
                                    )
                                        ->format(
                                            'Y-m-d H:i:s'
                                        ),

                                'completed_at' =>
                                    optional(
                                        $stockTake->completed_at
                                    )
                                        ->format(
                                            'Y-m-d H:i:s'
                                        ),

                                'cancelled_at' =>
                                    optional(
                                        $stockTake->cancelled_at
                                    )
                                        ->format(
                                            'Y-m-d H:i:s'
                                        ),

                                'cancellation_reason' =>
                                    $stockTake
                                        ->cancellation_reason,

                                'items_count' =>
                                    NewRangeStockTakeItem::query()
                                        ->where(
                                            'stock_take_id',
                                            $stockTake->id
                                        )
                                        ->count(),

                            ];

                        }
                    )
                    ->values();


            return response()->json([

                'stock_takes' =>
                    $history,

            ]);

        } catch (
            Throwable $exception
        ) {

            return response()->json([

                'message' =>
                    'Unable to load stock take history.',

                'error' =>
                    $exception->getMessage(),

            ], 500);

        }

    }


    /**
     * =========================================================
     * START STOCK TAKE
     * =========================================================
     *
     * POST /api/new-range/stock-takes
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

                    'stock_take_date' => [
                        'nullable',
                        'date',
                    ],

                    'notes' => [
                        'nullable',
                        'string',
                    ],

                ]);


            $branchId =
                (int)
                $validated[
                    'branch_id'
                ];


            /*
            |--------------------------------------------------------------------------
            | Check Existing Draft
            |--------------------------------------------------------------------------
            */

            /** @var NewRangeStockTake|null $existingDraft */
            $existingDraft =
                NewRangeStockTake::query()

                    ->where(
                        'branch_id',
                        $branchId
                    )

                    ->where(
                        'status',
                        NewRangeStockTake::STATUS_DRAFT
                    )

                    ->first();


            if (
                $existingDraft
            ) {

                return response()->json([

                    'message' =>
                        'This branch already has an open stock take.',

                    'stock_take_id' =>
                        $existingDraft->id,

                ], 422);

            }


            /*
            |--------------------------------------------------------------------------
            | Create Stock Take
            |--------------------------------------------------------------------------
            */

            /** @var NewRangeStockTake $stockTake */
            $stockTake =
                DB::transaction(

                    function () use (
                        $request,
                        $validated,
                        $branchId
                    ): NewRangeStockTake {

                        /*
                        |--------------------------------------------------------------------------
                        | Header
                        |--------------------------------------------------------------------------
                        */

                        /** @var NewRangeStockTake $newStockTake */
                        $newStockTake =
                            NewRangeStockTake::create([

                                'branch_id' =>
                                    $branchId,

                                'stock_take_number' =>
                                    $this
                                        ->generateStockTakeNumber(),

                                'stock_take_date' =>
                                    $validated[
                                        'stock_take_date'
                                    ]
                                    ??
                                    now()->toDateString(),

                                'status' =>
                                    NewRangeStockTake::STATUS_DRAFT,

                                'notes' =>
                                    $validated[
                                        'notes'
                                    ]
                                    ??
                                    null,

                                'created_by' =>
                                    $request
                                        ->user()
                                        ?->id,

                            ]);


                        /*
                        |--------------------------------------------------------------------------
                        | Current System SOH
                        |--------------------------------------------------------------------------
                        */

                        $stockRows =
                            $this
                                ->getSystemStockForBranch(
                                    $branchId
                                );


                        /*
                        |--------------------------------------------------------------------------
                        | Snapshot Items
                        |--------------------------------------------------------------------------
                        */

                        foreach (
                            $stockRows
                            as $stockRow
                        ) {

                            NewRangeStockTakeItem::create([

                                'stock_take_id' =>
                                    $newStockTake->id,

                                'product_id' =>
                                    $stockRow[
                                        'product_id'
                                    ],

                                'system_quantity' =>
                                    $stockRow[
                                        'stock_on_hand'
                                    ],

                                'physical_quantity' =>
                                    null,

                                'variance_quantity' =>
                                    null,

                            ]);

                        }


                        return $newStockTake;

                    }

                );


            return response()->json([

                'message' =>
                    'Stock take started successfully.',

                'stock_take' =>
                    $this->formatStockTake(
                        $stockTake
                    ),

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
                    'Unable to start stock take.',

                'error' =>
                    $exception->getMessage(),

            ], 500);

        }

    }


    /**
     * =========================================================
     * SHOW STOCK TAKE
     * =========================================================
     *
     * GET /api/new-range/stock-takes/{id}
     *
     */
    public function show(
        int $id
    ): JsonResponse {

        try {

            /** @var NewRangeStockTake $stockTake */
            $stockTake =
                NewRangeStockTake::query()
                    ->findOrFail(
                        $id
                    );


            return response()->json([

                'stock_take' =>
                    $this->formatStockTake(
                        $stockTake
                    ),

            ]);

        } catch (
            Throwable $exception
        ) {

            return response()->json([

                'message' =>
                    'Unable to load stock take.',

                'error' =>
                    $exception->getMessage(),

            ], 500);

        }

    }


    /**
     * =========================================================
     * SAVE STOCK TAKE
     * =========================================================
     *
     * PUT /api/new-range/stock-takes/{id}
     *
     */
    public function update(
        Request $request,
        int $id
    ): JsonResponse {

        try {

            $validated =
                $request->validate([

                    'notes' => [
                        'nullable',
                        'string',
                    ],

                    'items' => [
                        'required',
                        'array',
                    ],

                    'items.*.product_id' => [
                        'required',
                        'integer',
                    ],

                    'items.*.physical_quantity' => [
                        'nullable',
                        'numeric',
                        'min:0',
                    ],

                ]);


            /** @var NewRangeStockTake $stockTake */
            $stockTake =
                NewRangeStockTake::query()
                    ->findOrFail(
                        $id
                    );


            /*
            |--------------------------------------------------------------------------
            | Only Draft Can Be Edited
            |--------------------------------------------------------------------------
            */

            if (
                $stockTake->status
                !==
                NewRangeStockTake::STATUS_DRAFT
            ) {

                return response()->json([

                    'message' =>
                        'Only draft stock takes can be edited.',

                ], 422);

            }


            /*
            |--------------------------------------------------------------------------
            | Save
            |--------------------------------------------------------------------------
            */

            DB::transaction(

                function () use (
                    $stockTake,
                    $validated
                ): void {

                    /*
                    |--------------------------------------------------------------------------
                    | Notes
                    |--------------------------------------------------------------------------
                    */

                    if (
                        array_key_exists(
                            'notes',
                            $validated
                        )
                    ) {

                        $stockTake->update([

                            'notes' =>
                                $validated[
                                    'notes'
                                ],

                        ]);

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Items
                    |--------------------------------------------------------------------------
                    */

                    foreach (
                        $validated[
                            'items'
                        ]
                        as $row
                    ) {

                        /** @var NewRangeStockTakeItem|null $item */
                        $item =
                            NewRangeStockTakeItem::query()

                                ->where(
                                    'stock_take_id',
                                    $stockTake->id
                                )

                                ->where(
                                    'product_id',
                                    $row[
                                        'product_id'
                                    ]
                                )

                                ->first();


                        if (
                            !$item
                        ) {

                            continue;

                        }


                        $physicalQuantity =
                            $row[
                                'physical_quantity'
                            ]
                            ??
                            null;


                        /*
                        |--------------------------------------------------------------------------
                        | Not Counted Yet
                        |--------------------------------------------------------------------------
                        */

                        if (
                            $physicalQuantity
                            ===
                            null
                        ) {

                            $item->update([

                                'physical_quantity' =>
                                    null,

                                'variance_quantity' =>
                                    null,

                            ]);


                            continue;

                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Variance
                        |--------------------------------------------------------------------------
                        */

                        $physical =
                            (float)
                            $physicalQuantity;


                        $system =
                            (float)
                            $item
                                ->system_quantity;


                        $variance =
                            $physical
                            -
                            $system;


                        $item->update([

                            'physical_quantity' =>
                                $physical,

                            'variance_quantity' =>
                                round(
                                    $variance,
                                    2
                                ),

                        ]);

                    }

                }

            );


            /*
            |--------------------------------------------------------------------------
            | Fresh Model
            |--------------------------------------------------------------------------
            */

            /** @var NewRangeStockTake $stockTake */
            $stockTake =
                NewRangeStockTake::query()
                    ->findOrFail(
                        $id
                    );


            return response()->json([

                'message' =>
                    'Stock take saved successfully.',

                'stock_take' =>
                    $this->formatStockTake(
                        $stockTake
                    ),

            ]);

        } catch (
            ValidationException $exception
        ) {

            throw $exception;

        } catch (
            Throwable $exception
        ) {

            return response()->json([

                'message' =>
                    'Unable to save stock take.',

                'error' =>
                    $exception->getMessage(),

            ], 500);

        }

    }


    /**
     * =========================================================
     * COMPLETE STOCK TAKE
     * =========================================================
     *
     * POST /api/new-range/stock-takes/{id}/complete
     *
     */
    public function complete(
        Request $request,
        int $id
    ): JsonResponse {

        try {

            /*
            |--------------------------------------------------------------------------
            | Stock Take
            |--------------------------------------------------------------------------
            */

            /** @var NewRangeStockTake $stockTake */
            $stockTake =
                NewRangeStockTake::query()
                    ->findOrFail(
                        $id
                    );


            /*
            |--------------------------------------------------------------------------
            | Must Still Be Draft
            |--------------------------------------------------------------------------
            */

            if (
                $stockTake->status
                !==
                NewRangeStockTake::STATUS_DRAFT
            ) {

                return response()->json([

                    'message' =>
                        'Only a draft stock take can be completed.',

                ], 422);

            }


            /*
            |--------------------------------------------------------------------------
            | Count Uncounted Items
            |--------------------------------------------------------------------------
            |
            | IMPORTANT:
            |
            | We query NewRangeStockTakeItem directly here.
            |
            | We DO NOT use:
            |
            | $stockTake->items
            | $stockTake->items()
            |
            | This avoids the Intelephense relationship type warning.
            |
            */

            $uncountedItemsCount =
                NewRangeStockTakeItem::query()

                    ->where(
                        'stock_take_id',
                        $stockTake->id
                    )

                    ->whereNull(
                        'physical_quantity'
                    )

                    ->count();


            if (
                $uncountedItemsCount > 0
            ) {

                return response()->json([

                    'message' =>
                        'Every product must have a physical count before the stock take can be completed.',

                    'uncounted_items' =>
                        $uncountedItemsCount,

                ], 422);

            }


            /*
            |--------------------------------------------------------------------------
            | Complete
            |--------------------------------------------------------------------------
            */

            $stockTake->update([

                'status' =>
                    NewRangeStockTake::STATUS_COMPLETED,

                'completed_by' =>
                    $request
                        ->user()
                        ?->id,

                'completed_at' =>
                    now(),

            ]);


            /*
            |--------------------------------------------------------------------------
            | Reload
            |--------------------------------------------------------------------------
            */

            /** @var NewRangeStockTake $stockTake */
            $stockTake =
                NewRangeStockTake::query()
                    ->findOrFail(
                        $id
                    );


            return response()->json([

                'message' =>
                    'Stock take completed successfully.',

                'stock_take' =>
                    $this->formatStockTake(
                        $stockTake
                    ),

            ]);

        } catch (
            Throwable $exception
        ) {

            return response()->json([

                'message' =>
                    'Unable to complete stock take.',

                'error' =>
                    $exception->getMessage(),

            ], 500);

        }

    }


    /**
     * =========================================================
     * CANCEL STOCK TAKE
     * =========================================================
     *
     * POST /api/new-range/stock-takes/{id}/cancel
     *
     */
    public function cancel(
        Request $request,
        int $id
    ): JsonResponse {

        try {

            $validated =
                $request->validate([

                    'reason' => [
                        'nullable',
                        'string',
                        'max:1000',
                    ],

                ]);


            /** @var NewRangeStockTake $stockTake */
            $stockTake =
                NewRangeStockTake::query()
                    ->findOrFail(
                        $id
                    );


            /*
            |--------------------------------------------------------------------------
            | Only Draft Can Be Cancelled
            |--------------------------------------------------------------------------
            */

            if (
                $stockTake->status
                !==
                NewRangeStockTake::STATUS_DRAFT
            ) {

                return response()->json([

                    'message' =>
                        'Only a draft stock take can be cancelled.',

                ], 422);

            }


            /*
            |--------------------------------------------------------------------------
            | Cancel
            |--------------------------------------------------------------------------
            */

            $stockTake->update([

                'status' =>
                    NewRangeStockTake::STATUS_CANCELLED,

                'cancelled_by' =>
                    $request
                        ->user()
                        ?->id,

                'cancelled_at' =>
                    now(),

                'cancellation_reason' =>
                    $validated[
                        'reason'
                    ]
                    ??
                    null,

            ]);


            /*
            |--------------------------------------------------------------------------
            | Fresh Model
            |--------------------------------------------------------------------------
            */

            /** @var NewRangeStockTake $stockTake */
            $stockTake =
                NewRangeStockTake::query()
                    ->findOrFail(
                        $id
                    );


            return response()->json([

                'message' =>
                    'Stock take cancelled successfully.',

                'stock_take' =>
                    $this->formatStockTake(
                        $stockTake
                    ),

            ]);

        } catch (
            ValidationException $exception
        ) {

            throw $exception;

        } catch (
            Throwable $exception
        ) {

            return response()->json([

                'message' =>
                    'Unable to cancel stock take.',

                'error' =>
                    $exception->getMessage(),

            ], 500);

        }

    }


    /**
     * =========================================================
     * FORMAT STOCK TAKE
     * =========================================================
     */
    private function formatStockTake(
        NewRangeStockTake $stockTake
    ): array {

        /*
        |--------------------------------------------------------------------------
        | Items
        |--------------------------------------------------------------------------
        |
        | Query directly instead of using $stockTake->items.
        |
        */

        $items =
            NewRangeStockTakeItem::query()

                ->where(
                    'stock_take_id',
                    $stockTake->id
                )

                ->orderBy(
                    'product_id'
                )

                ->get();


        /*
        |--------------------------------------------------------------------------
        | Product Lookup
        |--------------------------------------------------------------------------
        */

        $productIds =
            $items
                ->pluck(
                    'product_id'
                )
                ->unique()
                ->values()
                ->all();


        $products =
            NewRangeProduct::query()

                ->whereIn(
                    'id',
                    $productIds
                )

                ->get([
                    'id',
                    'model_number',
                    'description',
                ]);


        $productMap =
            [];


        foreach (
            $products
            as $product
        ) {

            $productMap[
                $product->id
            ] = [

                'model_number' =>
                    $product->model_number,

                'description' =>
                    $product->description,

            ];

        }


        /*
        |--------------------------------------------------------------------------
        | Formatted Items
        |--------------------------------------------------------------------------
        */

        $formattedItems =
            [];


        $countedProducts =
            0;


        $varianceProducts =
            0;


        $shortProducts =
            0;


        $overProducts =
            0;


        $systemTotal =
            0;


        $physicalTotal =
            0;


        $varianceTotal =
            0;


        foreach (
            $items
            as $item
        ) {

            $systemQuantity =
                (float)
                $item
                    ->system_quantity;


            $physicalQuantity =
                $item
                    ->physical_quantity
                !==
                null

                    ? (float)
                        $item
                            ->physical_quantity

                    : null;


            $varianceQuantity =
                $item
                    ->variance_quantity
                !==
                null

                    ? (float)
                        $item
                            ->variance_quantity

                    : null;


            /*
            |--------------------------------------------------------------------------
            | Summary
            |--------------------------------------------------------------------------
            */

            $systemTotal +=
                $systemQuantity;


            if (
                $physicalQuantity
                !==
                null
            ) {

                $countedProducts++;


                $physicalTotal +=
                    $physicalQuantity;


                $varianceTotal +=
                    $varianceQuantity
                    ??
                    0;


                if (
                    $varianceQuantity
                    !==
                    null
                    &&
                    abs(
                        $varianceQuantity
                    )
                    >
                    0.0001
                ) {

                    $varianceProducts++;

                }


                if (
                    $varianceQuantity
                    !==
                    null
                    &&
                    $varianceQuantity
                    <
                    0
                ) {

                    $shortProducts++;

                }


                if (
                    $varianceQuantity
                    !==
                    null
                    &&
                    $varianceQuantity
                    >
                    0
                ) {

                    $overProducts++;

                }

            }


            $product =
                $productMap[
                    $item->product_id
                ]
                ??
                [

                    'model_number' =>
                        '',

                    'description' =>
                        '',

                ];


            $formattedItems[] = [

                'id' =>
                    $item->id,

                'product_id' =>
                    $item->product_id,

                'model_number' =>
                    $product[
                        'model_number'
                    ],

                'description' =>
                    $product[
                        'description'
                    ],

                'system_quantity' =>
                    $systemQuantity,

                'physical_quantity' =>
                    $physicalQuantity,

                'variance_quantity' =>
                    $varianceQuantity,

            ];

        }


        /*
        |--------------------------------------------------------------------------
        | Sort By Model Number
        |--------------------------------------------------------------------------
        */

        usort(

            $formattedItems,

            function (
                array $a,
                array $b
            ): int {

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
        | Branch
        |--------------------------------------------------------------------------
        */

        $branchName =
            $this->getBranchName(
                $stockTake->branch_id
            );


        /*
        |--------------------------------------------------------------------------
        | User Names
        |--------------------------------------------------------------------------
        */

        $createdBy =
            $this->getUserName(
                $stockTake->created_by
            );


        $completedBy =
            $this->getUserName(
                $stockTake->completed_by
            );


        $cancelledBy =
            $this->getUserName(
                $stockTake->cancelled_by
            );


        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return [

            'id' =>
                $stockTake->id,

            'stock_take_number' =>
                $stockTake
                    ->stock_take_number,

            'branch_id' =>
                $stockTake
                    ->branch_id,

            'branch_name' =>
                $branchName,

            'stock_take_date' =>
                optional(
                    $stockTake
                        ->stock_take_date
                )
                    ->format(
                        'Y-m-d'
                    ),

            'status' =>
                $stockTake
                    ->status,

            'notes' =>
                $stockTake
                    ->notes,

            'created_by' =>
                $createdBy,

            'completed_by' =>
                $completedBy,

            'completed_at' =>
                optional(
                    $stockTake
                        ->completed_at
                )
                    ->format(
                        'Y-m-d H:i:s'
                    ),

            'cancelled_by' =>
                $cancelledBy,

            'cancelled_at' =>
                optional(
                    $stockTake
                        ->cancelled_at
                )
                    ->format(
                        'Y-m-d H:i:s'
                    ),

            'cancellation_reason' =>
                $stockTake
                    ->cancellation_reason,

            'summary' => [

                'total_products' =>
                    count(
                        $formattedItems
                    ),

                'counted_products' =>
                    $countedProducts,

                'uncounted_products' =>

                    count(
                        $formattedItems
                    )

                    -

                    $countedProducts,

                'variance_products' =>
                    $varianceProducts,

                'short_products' =>
                    $shortProducts,

                'over_products' =>
                    $overProducts,

                'system_total' =>
                    round(
                        $systemTotal,
                        2
                    ),

                'physical_total' =>
                    round(
                        $physicalTotal,
                        2
                    ),

                'variance_total' =>
                    round(
                        $varianceTotal,
                        2
                    ),

            ],

            'items' =>
                $formattedItems,

        ];

    }


    /**
     * =========================================================
     * SYSTEM STOCK FOR BRANCH
     * =========================================================
     */
    private function getSystemStockForBranch(
        int $branchId
    ): array {

        /*
        |--------------------------------------------------------------------------
        | Quantity Column
        |--------------------------------------------------------------------------
        */

        $quantityColumn =
            $this
                ->getQuantityColumn();


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

            $productQuery->where(
                'is_active',
                true
            );

        } elseif (
            Schema::hasColumn(
                'new_range_products',
                'active'
            )
        ) {

            $productQuery->where(
                'active',
                true
            );

        }


        $products =
            $productQuery

                ->where(
                    'model_number',
                    'like',
                    '%-NEW'
                )

                ->orderBy(
                    'model_number'
                )

                ->get();


        /*
        |--------------------------------------------------------------------------
        | Movements
        |--------------------------------------------------------------------------
        */

        $movements =
            DB::table(
                'new_range_stock_movements'
            )

                ->select(

                    'product_id',

                    'movement_type',

                    DB::raw(
                        "SUM({$quantityColumn}) AS total_quantity"
                    )

                )

                ->where(
                    'branch_id',
                    $branchId
                )

                ->whereDate(
                    'movement_date',
                    '>=',
                    self::OPENING_STOCK_DATE
                )

                ->groupBy(
                    'product_id',
                    'movement_type'
                )

                ->get();


        /*
        |--------------------------------------------------------------------------
        | Movement Map
        |--------------------------------------------------------------------------
        */

        $movementMap =
            [];


        foreach (
            $movements
            as $movement
        ) {

            $productId =
                (int)
                $movement
                    ->product_id;


            if (
                !isset(
                    $movementMap[
                        $productId
                    ]
                )
            ) {

                $movementMap[
                    $productId
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


            $quantity =
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
                        $productId
                    ][
                        'opening'
                    ] +=
                        $quantity;

                    break;


                case 'GRV':

                    $movementMap[
                        $productId
                    ][
                        'grv'
                    ] +=
                        $quantity;

                    break;


                case 'IBT_IN':

                    $movementMap[
                        $productId
                    ][
                        'ibt_in'
                    ] +=
                        $quantity;

                    break;


                case 'IBT_OUT':

                    $movementMap[
                        $productId
                    ][
                        'ibt_out'
                    ] +=
                        $quantity;

                    break;


                case 'ADJUSTMENT_IN':

                    $movementMap[
                        $productId
                    ][
                        'adjustment_in'
                    ] +=
                        $quantity;

                    break;


                case 'ADJUSTMENT_OUT':

                    $movementMap[
                        $productId
                    ][
                        'adjustment_out'
                    ] +=
                        $quantity;

                    break;

            }

        }


        /*
        |--------------------------------------------------------------------------
        | Sales
        |--------------------------------------------------------------------------
        */

        $sales =
            DB::table(
                'new_range_sales'
            )

                ->select(

                    'product_id',

                    DB::raw(
                        'SUM(quantity) AS quantity_sold'
                    )

                )

                ->where(
                    'branch_id',
                    $branchId
                )

                ->whereDate(
                    'sale_date',
                    '>=',
                    self::OPENING_STOCK_DATE
                )

                ->groupBy(
                    'product_id'
                )

                ->get();


        $salesMap =
            [];


        foreach (
            $sales
            as $sale
        ) {

            $salesMap[
                (int)
                $sale
                    ->product_id
            ] =
                (float)
                $sale
                    ->quantity_sold;

        }


        /*
        |--------------------------------------------------------------------------
        | Build Snapshot
        |--------------------------------------------------------------------------
        */

        $rows =
            [];


        foreach (
            $products
            as $product
        ) {

            $movement =
                $movementMap[
                    $product->id
                ]
                ??
                [

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


            $salesQuantity =
                $salesMap[
                    $product->id
                ]
                ??
                0;


            $stockOnHand =

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

                $salesQuantity

                +

                $movement[
                    'adjustment_in'
                ]

                -

                $movement[
                    'adjustment_out'
                ];


            $rows[] = [

                'product_id' =>
                    $product->id,

                'model_number' =>
                    $product
                        ->model_number,

                'description' =>
                    $product
                        ->description,

                'stock_on_hand' =>
                    round(
                        $stockOnHand,
                        2
                    ),

            ];

        }


        return $rows;

    }


    /**
     * =========================================================
     * BRANCH NAME
     * =========================================================
     */
    private function getBranchName(
        int $branchId
    ): ?string {

        return DB::table(
            'new_range_branches'
        )

            ->where(
                'id',
                $branchId
            )

            ->value(
                'name'
            );

    }


    /**
     * =========================================================
     * USER NAME
     * =========================================================
     */
    private function getUserName(
        ?int $userId
    ): ?string {

        if (
            !$userId
        ) {

            return null;

        }


        return DB::table(
            'users'
        )

            ->where(
                'id',
                $userId
            )

            ->value(
                'name'
            );

    }


    /**
     * =========================================================
     * STOCK TAKE NUMBER
     * =========================================================
     */
    private function generateStockTakeNumber(): string
    {
        do {

            $number =

                'ST-'

                .

                now()->format(
                    'Ymd'
                )

                .

                '-'

                .

                random_int(
                    100000,
                    999999
                );


            $exists =
                NewRangeStockTake::query()

                    ->where(
                        'stock_take_number',
                        $number
                    )

                    ->exists();

        } while (
            $exists
        );


        return $number;

    }


    /**
     * =========================================================
     * MOVEMENT QUANTITY COLUMN
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
            'new_range_stock_movements does not contain a quantity or qty column.'
        );

    }
}
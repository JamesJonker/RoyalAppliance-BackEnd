<?php

namespace App\Http\Controllers;

use App\Models\NewRangeBranch;
use App\Models\NewRangeIbt;
use App\Models\NewRangeProduct;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class NewRangeIbtController extends Controller
{
    private const OPENING_STOCK_DATE =
        '2026-09-18';


    /**
     * =========================================================
     * CREATE IBT OUT
     * =========================================================
     *
     * POST /api/new-range/ibt/out
     *
     */
    public function storeOut(
        Request $request
    ): JsonResponse {

        try {

            $validated =
                $request->validate([

                    'from_branch_id' => [
                        'required',
                        'integer',
                        'exists:new_range_branches,id',
                    ],

                    'to_branch_id' => [
                        'required',
                        'integer',
                        'different:from_branch_id',
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

                ]);


            /*
            |--------------------------------------------------------------------------
            | Duplicate reference
            |--------------------------------------------------------------------------
            */

            if (
                NewRangeIbt::where(
                    'reference',
                    $validated['reference']
                )->exists()
            ) {

                return response()->json([

                    'message' =>
                        'An IBT with this reference already exists.',

                ], 422);

            }


            $quantityColumn =
                $this->getQuantityColumn();


            $referenceColumn =
                $this->getReferenceColumn();


            $movementDate =
                Carbon::parse(
                    $validated['movement_date']
                )
                ->toDateString();


            $transferGroupId =
                (string)
                Str::uuid();


            $createdBy =
                $request->user()?->id;


            /*
            |--------------------------------------------------------------------------
            | Check for duplicate products
            |--------------------------------------------------------------------------
            */

            $productIds =
                collect(
                    $validated['items']
                )
                ->pluck(
                    'product_id'
                );


            if (
                $productIds->duplicates()
                    ->isNotEmpty()
            ) {

                return response()->json([

                    'message' =>
                        'The same product cannot be added twice to one IBT.',

                ], 422);

            }


            $result =
                DB::transaction(
                    function () use (
                        $validated,
                        $quantityColumn,
                        $referenceColumn,
                        $movementDate,
                        $transferGroupId,
                        $createdBy
                    ) {

                        /*
                        |--------------------------------------------------------------------------
                        | IBT Header
                        |--------------------------------------------------------------------------
                        */

                        $ibt =
                            NewRangeIbt::create([

                                'transfer_group_id' =>
                                    $transferGroupId,

                                'reference' =>
                                    $validated['reference'],

                                'from_branch_id' =>
                                    $validated[
                                        'from_branch_id'
                                    ],

                                'to_branch_id' =>
                                    $validated[
                                        'to_branch_id'
                                    ],

                                'transfer_date' =>
                                    $movementDate,

                                'status' =>
                                    'OUT',

                                'created_by' =>
                                    $createdBy,

                                'sent_at' =>
                                    now(),

                                'notes' =>
                                    $validated['notes']
                                    ?? null,

                            ]);


                        $createdItems =
                            [];


                        $totalUnits =
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
                            | Current source branch stock
                            |--------------------------------------------------------------------------
                            */

                            $available =
                                $this->calculateStockOnHand(
                                    $product->id,
                                    $validated[
                                        'from_branch_id'
                                    ],
                                    $quantityColumn
                                );


                            if (
                                $quantity >
                                $available
                            ) {

                                throw ValidationException::withMessages([

                                    'items' => [

                                        $product->model_number
                                        .
                                        ' only has '
                                        .
                                        $available
                                        .
                                        ' units available at the sending branch.',

                                    ],

                                ]);

                            }


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


                            /*
                            |--------------------------------------------------------------------------
                            | Create IBT OUT movement
                            |--------------------------------------------------------------------------
                            */

                            $movement = [

                                'product_id' =>
                                    $product->id,

                                'branch_id' =>
                                    $validated[
                                        'from_branch_id'
                                    ],

                                'movement_type' =>
                                    'IBT_OUT',

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

                                'transfer_group_id' =>
                                    $transferGroupId,

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


                            $movement =
                                $this->filterMovementColumns(
                                    $movement
                                );


                            $movementId =
                                DB::table(
                                    'new_range_stock_movements'
                                )
                                ->insertGetId(
                                    $movement
                                );


                            $createdItems[] = [

                                'movement_id' =>
                                    $movementId,

                                'product_id' =>
                                    $product->id,

                                'model_number' =>
                                    $product->model_number,

                                'description' =>
                                    $product->description,

                                'quantity' =>
                                    $quantity,

                                'unit_cost_excl' =>
                                    $unitCostExcl,

                                'unit_cost_incl' =>
                                    $unitCostIncl,

                            ];


                            $totalUnits +=
                                $quantity;

                        }


                        return [

                            'ibt' =>
                                $ibt,

                            'items' =>
                                $createdItems,

                            'total_units' =>
                                $totalUnits,

                        ];

                    }
                );


            return response()->json([

                'message' =>
                    'IBT OUT created successfully.',

                'ibt' => [

                    'id' =>
                        $result['ibt']->id,

                    'transfer_group_id' =>
                        $transferGroupId,

                    'reference' =>
                        $validated['reference'],

                    'movement_date' =>
                        $movementDate,

                    'status' =>
                        'OUT',

                    'from_branch_id' =>
                        $validated[
                            'from_branch_id'
                        ],

                    'from_branch_name' =>
                        NewRangeBranch::find(
                            $validated[
                                'from_branch_id'
                            ]
                        )?->name,

                    'to_branch_id' =>
                        $validated[
                            'to_branch_id'
                        ],

                    'to_branch_name' =>
                        NewRangeBranch::find(
                            $validated[
                                'to_branch_id'
                            ]
                        )?->name,

                    'total_units' =>
                        $result['total_units'],

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
                    'Unable to create IBT OUT.',

                'error' =>
                    $exception->getMessage(),

            ], 500);

        }
    }


    /**
     * =========================================================
     * PENDING IBTS
     * =========================================================
     *
     * GET /api/new-range/ibt/pending
     *
     */
    public function pending(): JsonResponse
    {
        try {

            $quantityColumn =
                $this->getQuantityColumn();


            $referenceColumn =
                $this->getReferenceColumn();


            $ibts =
                NewRangeIbt::query()
                    ->with([
                        'fromBranch:id,name',
                        'toBranch:id,name',
                    ])
                    ->where(
                        'status',
                        'OUT'
                    )
                    ->orderBy(
                        'transfer_date'
                    )
                    ->orderBy(
                        'id'
                    )
                    ->get();


            $rows =
                [];


            foreach (
                $ibts
                as $ibt
            ) {

                $movements =
                    DB::table(
                        'new_range_stock_movements as movement'
                    )
                    ->join(
                        'new_range_products as product',
                        'product.id',
                        '=',
                        'movement.product_id'
                    )
                    ->where(
                        'movement.transfer_group_id',
                        $ibt->transfer_group_id
                    )
                    ->where(
                        'movement.movement_type',
                        'IBT_OUT'
                    )
                    ->select([

                        'movement.id',
                        'movement.product_id',

                        'product.model_number',
                        'product.description',

                        DB::raw(
                            "movement.{$quantityColumn} as quantity"
                        ),

                        'movement.unit_cost_excl',
                        'movement.unit_cost_incl',

                    ])
                    ->get();


                $totalUnits =
                    $movements->sum(
                        fn ($row) =>
                            (float)
                            $row->quantity
                    );


                $rows[] = [

                    'id' =>
                        $ibt->id,

                    'transfer_group_id' =>
                        $ibt->transfer_group_id,

                    'reference' =>
                        $ibt->reference,

                    'transfer_date' =>
                        optional(
                            $ibt->transfer_date
                        )->format(
                            'Y-m-d'
                        ),

                    'status' =>
                        $ibt->status,

                    'from_branch_id' =>
                        $ibt->from_branch_id,

                    'from_branch_name' =>
                        $ibt->fromBranch?->name,

                    'to_branch_id' =>
                        $ibt->to_branch_id,

                    'to_branch_name' =>
                        $ibt->toBranch?->name,

                    'total_units' =>
                        $totalUnits,

                    'notes' =>
                        $ibt->notes,

                    'items' =>
                        $movements,

                ];

            }


            return response()->json([

                'message' =>
                    'Pending IBTs loaded successfully.',

                'ibts' =>
                    $rows,

            ]);

        } catch (
            Throwable $exception
        ) {

            return response()->json([

                'message' =>
                    'Unable to load pending IBTs.',

                'error' =>
                    $exception->getMessage(),

            ], 500);

        }
    }


    /**
     * =========================================================
     * RECEIVE IBT / IBT IN
     * =========================================================
     *
     * POST /api/new-range/ibt/{ibt}/in
     *
     */
    public function receive(
        Request $request,
        NewRangeIbt $ibt
    ): JsonResponse {

        try {

            if (
                $ibt->status !==
                'OUT'
            ) {

                return response()->json([

                    'message' =>
                        'This IBT has already been received or is no longer outstanding.',

                ], 422);

            }


            $validated =
                $request->validate([

                    'movement_date' => [
                        'required',
                        'date',
                    ],

                    'notes' => [
                        'nullable',
                        'string',
                        'max:2000',
                    ],

                ]);


            $quantityColumn =
                $this->getQuantityColumn();


            $referenceColumn =
                $this->getReferenceColumn();


            $movementDate =
                Carbon::parse(
                    $validated['movement_date']
                )
                ->toDateString();


            $receivedBy =
                $request->user()?->id;


            /*
            |--------------------------------------------------------------------------
            | Safety check
            |--------------------------------------------------------------------------
            */

            $alreadyReceived =
                DB::table(
                    'new_range_stock_movements'
                )
                ->where(
                    'transfer_group_id',
                    $ibt->transfer_group_id
                )
                ->where(
                    'movement_type',
                    'IBT_IN'
                )
                ->exists();


            if (
                $alreadyReceived
            ) {

                return response()->json([

                    'message' =>
                        'This IBT has already been received.',

                ], 422);

            }


            $result =
                DB::transaction(
                    function () use (
                        $ibt,
                        $validated,
                        $quantityColumn,
                        $referenceColumn,
                        $movementDate,
                        $receivedBy
                    ) {

                        /*
                        |--------------------------------------------------------------------------
                        | Get original IBT OUT movements
                        |--------------------------------------------------------------------------
                        */

                        $outMovements =
                            DB::table(
                                'new_range_stock_movements'
                            )
                            ->where(
                                'transfer_group_id',
                                $ibt->transfer_group_id
                            )
                            ->where(
                                'movement_type',
                                'IBT_OUT'
                            )
                            ->get();


                        if (
                            $outMovements->isEmpty()
                        ) {

                            throw new \RuntimeException(
                                'No IBT OUT stock movements were found for this transfer.'
                            );

                        }


                        $receivedItems =
                            [];


                        $totalUnits =
                            0;


                        foreach (
                            $outMovements
                            as $outMovement
                        ) {

                            $quantity =
                                (float)
                                $outMovement->{$quantityColumn};


                            $movement = [

                                'product_id' =>
                                    $outMovement->product_id,

                                'branch_id' =>
                                    $ibt->to_branch_id,

                                'movement_type' =>
                                    'IBT_IN',

                                'movement_date' =>
                                    $movementDate,

                                $quantityColumn =>
                                    $quantity,

                                $referenceColumn =>
                                    $ibt->reference,

                                'unit_cost_excl' =>
                                    $outMovement->unit_cost_excl,

                                'unit_cost_incl' =>
                                    $outMovement->unit_cost_incl,

                                'transfer_group_id' =>
                                    $ibt->transfer_group_id,

                                'created_by' =>
                                    $receivedBy,

                                'notes' =>
                                    $validated['notes']
                                    ??
                                    $ibt->notes,

                                'created_at' =>
                                    now(),

                                'updated_at' =>
                                    now(),

                            ];


                            $movement =
                                $this->filterMovementColumns(
                                    $movement
                                );


                            $movementId =
                                DB::table(
                                    'new_range_stock_movements'
                                )
                                ->insertGetId(
                                    $movement
                                );


                            $product =
                                NewRangeProduct::find(
                                    $outMovement->product_id
                                );


                            $receivedItems[] = [

                                'movement_id' =>
                                    $movementId,

                                'product_id' =>
                                    $outMovement->product_id,

                                'model_number' =>
                                    $product?->model_number,

                                'description' =>
                                    $product?->description,

                                'quantity' =>
                                    $quantity,

                            ];


                            $totalUnits +=
                                $quantity;

                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Mark IBT received
                        |--------------------------------------------------------------------------
                        */

                        $ibt->update([

                            'status' =>
                                'RECEIVED',

                            'received_by' =>
                                $receivedBy,

                            'received_at' =>
                                now(),

                        ]);


                        return [

                            'items' =>
                                $receivedItems,

                            'total_units' =>
                                $totalUnits,

                        ];

                    }
                );


            return response()->json([

                'message' =>
                    'IBT IN completed successfully.',

                'ibt' => [

                    'id' =>
                        $ibt->id,

                    'transfer_group_id' =>
                        $ibt->transfer_group_id,

                    'reference' =>
                        $ibt->reference,

                    'status' =>
                        'RECEIVED',

                    'movement_date' =>
                        $movementDate,

                    'from_branch_id' =>
                        $ibt->from_branch_id,

                    'from_branch_name' =>
                        $ibt->fromBranch?->name,

                    'to_branch_id' =>
                        $ibt->to_branch_id,

                    'to_branch_name' =>
                        $ibt->toBranch?->name,

                    'total_units' =>
                        $result['total_units'],

                    'items' =>
                        $result['items'],

                ],

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
                    'Unable to complete IBT IN.',

                'error' =>
                    $exception->getMessage(),

            ], 500);

        }
    }


    /**
     * =========================================================
     * CALCULATE CURRENT SOH
     * =========================================================
     */

    private function calculateStockOnHand(
        int $productId,
        int $branchId,
        string $quantityColumn
    ): float {

        $movements =
            DB::table(
                'new_range_stock_movements'
            )
            ->where(
                'product_id',
                $productId
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
            ->select([
                'movement_type',
                $quantityColumn,
            ])
            ->get();


        $stock =
            0;


        foreach (
            $movements
            as $movement
        ) {

            $quantity =
                (float)
                $movement->{$quantityColumn};


            switch (
                $movement->movement_type
            ) {

                case 'OPENING':
                case 'GRV':
                case 'IBT_IN':
                case 'ADJUSTMENT_IN':

                    $stock +=
                        $quantity;

                    break;


                case 'IBT_OUT':
                case 'ADJUSTMENT_OUT':

                    $stock -=
                        $quantity;

                    break;

            }

        }


        /*
        |--------------------------------------------------------------------------
        | Live sales only
        |--------------------------------------------------------------------------
        */

        $sales =
            DB::table(
                'new_range_sales'
            )
            ->where(
                'product_id',
                $productId
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
            ->sum(
                'quantity'
            );


        $stock -=
            (float)
            $sales;


        return round(
            $stock,
            2
        );
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
     * FILTER MOVEMENT COLUMNS
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
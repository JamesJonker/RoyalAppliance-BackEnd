<?php

namespace App\Http\Controllers;

use App\Models\NewRangeBranch;
use App\Models\NewRangeMinimumStockLevel;
use App\Models\NewRangeOrder;
use App\Models\NewRangeOrderItem;
use App\Models\NewRangeProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class NewRangeOrderController extends Controller
{
    private const OPENING_STOCK_DATE =
        '2026-09-18';


    /**
     * =========================================================
     * GENERATE NEW ORDER
     * =========================================================
     *
     * POST /api/new-range/orders/generate
     *
     */
    public function generate(
        Request $request
    ): JsonResponse {

        try {

            /** @var NewRangeOrder $order */
            $order =
                DB::transaction(

                    function () use (
                        $request
                    ): NewRangeOrder {

                        /*
                        |--------------------------------------------------------------------------
                        | Order Header
                        |--------------------------------------------------------------------------
                        */

                        $order =
                            NewRangeOrder::create([

                                'order_number' =>
                                    $this
                                        ->generateOrderNumber(),

                                'order_date' =>
                                    now()
                                        ->toDateString(),

                                'status' =>
                                    NewRangeOrder::STATUS_DRAFT,

                                'created_by' =>
                                    $request
                                        ->user()
                                        ?->id,

                            ]);


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

                        ->whereRaw(
                            'LOWER(name) NOT LIKE ?',
                            ['%protec%']
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
                        | Minimum / Maximum Lookup
                        |--------------------------------------------------------------------------
                        */

                        $levels =
                            NewRangeMinimumStockLevel::query()
                                ->get();


                        $levelMap =
                            [];


                        foreach (
                            $levels
                            as $level
                        ) {

                            $key =
                                $level->branch_id
                                .
                                '|'
                                .
                                $level->product_id;


                            $levelMap[
                                $key
                            ] = [

                                'minimum' =>
                                    (float)
                                    $level
                                        ->minimum_quantity,

                                'maximum' =>
                                    (float)
                                    $level
                                        ->maximum_quantity,

                            ];

                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Current SOH
                        |--------------------------------------------------------------------------
                        */

                        $sohMap =
                            $this
                                ->getSystemStockMap();


                        /*
                        |--------------------------------------------------------------------------
                        | Generate Suggested Order Items
                        |--------------------------------------------------------------------------
                        */

                        foreach (
                            $products
                            as $product
                        ) {

                            $brand =
                                $this
                                    ->getBrand(
                                        $product
                                            ->model_number
                                    );


                            foreach (
                                $branches
                                as $branch
                            ) {

                                $key =
                                    $branch->id
                                    .
                                    '|'
                                    .
                                    $product->id;


                                $stockOnHand =
                                    $sohMap[
                                        $key
                                    ]
                                    ??
                                    0;


                                $level =
                                    $levelMap[
                                        $key
                                    ]
                                    ??
                                    [

                                        'minimum' =>
                                            0,

                                        'maximum' =>
                                            0,

                                    ];


                                $minimum =
                                    (float)
                                    $level[
                                        'minimum'
                                    ];


                                $maximum =
                                    (float)
                                    $level[
                                        'maximum'
                                    ];


                                /*
                                |--------------------------------------------------------------------------
                                | Order Logic
                                |--------------------------------------------------------------------------
                                |
                                | Only reorder when:
                                |
                                | SOH < Minimum
                                |
                                | Suggested =
                                |
                                | Maximum - SOH
                                |
                                */

                                $suggested =
                                    0;


                                if (
                                    $maximum > 0
                                    &&
                                    $stockOnHand
                                    <
                                    $minimum
                                ) {

                                    $suggested =
                                        max(
                                            0,
                                            $maximum
                                            -
                                            $stockOnHand
                                        );

                                }


                                /*
                                |--------------------------------------------------------------------------
                                | Only Store Required Rows
                                |--------------------------------------------------------------------------
                                */

                                if (
                                    $suggested <= 0
                                ) {

                                    continue;

                                }


                                NewRangeOrderItem::create([

                                    'order_id' =>
                                        $order->id,

                                    'product_id' =>
                                        $product->id,

                                    'branch_id' =>
                                        $branch->id,

                                    'brand' =>
                                        $brand,

                                    'system_soh' =>
                                        round(
                                            $stockOnHand,
                                            2
                                        ),

                                    'minimum_quantity' =>
                                        round(
                                            $minimum,
                                            2
                                        ),

                                    'maximum_quantity' =>
                                        round(
                                            $maximum,
                                            2
                                        ),

                                    'suggested_quantity' =>
                                        round(
                                            $suggested,
                                            2
                                        ),

                                    'order_quantity' =>
                                        round(
                                            $suggested,
                                            2
                                        ),

                                ]);

                            }

                        }


                        return $order;

                    }

                );


            return response()->json([

                'message' =>
                    'Suggested stock order generated successfully.',

                'order' =>
                    $this->formatOrder(
                        $order
                    ),

            ], 201);

        } catch (
            Throwable $exception
        ) {

            return response()->json([

                'message' =>
                    'Unable to generate stock order.',

                'error' =>
                    $exception
                        ->getMessage(),

            ], 500);

        }

    }


    /**
     * =========================================================
     * SHOW ORDER
     * =========================================================
     *
     * GET /api/new-range/orders/{id}
     *
     */
    public function show(
        int $id
    ): JsonResponse {

        try {

            /** @var NewRangeOrder $order */
            $order =
                NewRangeOrder::query()
                    ->findOrFail(
                        $id
                    );


            return response()->json([

                'order' =>
                    $this->formatOrder(
                        $order
                    ),

            ]);

        } catch (
            Throwable $exception
        ) {

            return response()->json([

                'message' =>
                    'Unable to load stock order.',

                'error' =>
                    $exception
                        ->getMessage(),

            ], 500);

        }

    }


    /**
     * =========================================================
     * UPDATE ORDER QUANTITIES
     * =========================================================
     *
     * PUT /api/new-range/orders/{id}
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

                    'items.*.id' => [
                        'required',
                        'integer',
                    ],

                    'items.*.order_quantity' => [
                        'required',
                        'numeric',
                        'min:0',
                    ],

                ]);


            /** @var NewRangeOrder $order */
            $order =
                NewRangeOrder::query()
                    ->findOrFail(
                        $id
                    );


            DB::transaction(

                function () use (
                    $order,
                    $validated
                ): void {

                    if (
                        array_key_exists(
                            'notes',
                            $validated
                        )
                    ) {

                        $order->update([

                            'notes' =>
                                $validated[
                                    'notes'
                                ],

                        ]);

                    }


                    foreach (
                        $validated[
                            'items'
                        ]
                        as $row
                    ) {

                        /** @var NewRangeOrderItem|null $item */
                        $item =
                            NewRangeOrderItem::query()

                                ->where(
                                    'order_id',
                                    $order->id
                                )

                                ->where(
                                    'id',
                                    $row[
                                        'id'
                                    ]
                                )

                                ->first();


                        if (
                            !$item
                        ) {

                            continue;

                        }


                        $item->update([

                            'order_quantity' =>
                                round(
                                    (float)
                                    $row[
                                        'order_quantity'
                                    ],
                                    2
                                ),

                        ]);

                    }

                }

            );


            /** @var NewRangeOrder $order */
            $order =
                NewRangeOrder::query()
                    ->findOrFail(
                        $id
                    );


            return response()->json([

                'message' =>
                    'Order quantities saved successfully.',

                'order' =>
                    $this->formatOrder(
                        $order
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
                    'Unable to save stock order.',

                'error' =>
                    $exception
                        ->getMessage(),

            ], 500);

        }

    }


    /**
     * =========================================================
     * EXPORT ORDER TO EXCEL
     * =========================================================
     *
     * GET /api/new-range/orders/{id}/export
     *
     */
    public function export(
        int $id
    ): StreamedResponse {

        /** @var NewRangeOrder $order */
        $order =
            NewRangeOrder::query()
                ->findOrFail(
                    $id
                );


        $data =
            $this->formatOrder(
                $order
            );


        /*
        |--------------------------------------------------------------------------
        | Save Export Date
        |--------------------------------------------------------------------------
        */

        $order->update([

            'status' =>
                NewRangeOrder::STATUS_EXPORTED,

            'exported_at' =>
                now(),

        ]);


        /*
        |--------------------------------------------------------------------------
        | File Name
        |--------------------------------------------------------------------------
        */

        $filename =
            $order
                ->order_number
            .
            '.xlsx';


        return response()->streamDownload(

            function () use (
                $data
            ) {

                /*
                |--------------------------------------------------------------------------
                | Workbook
                |--------------------------------------------------------------------------
                */

                $spreadsheet =
                    new Spreadsheet();


                $sheet =
                    $spreadsheet
                        ->getActiveSheet();


                $sheet->setTitle(
                    'Order List'
                );


                /*
                |--------------------------------------------------------------------------
                | Branches
                |--------------------------------------------------------------------------
                */

                $branches =
                    $data[
                        'branches'
                    ];


                /*
                |--------------------------------------------------------------------------
                | Main Title
                |--------------------------------------------------------------------------
                */

                $lastColumnNumber =
                    count(
                        $branches
                    )
                    +
                    1;


                $lastColumn =
                    Coordinate::stringFromColumnIndex(
                        $lastColumnNumber
                    );


                $sheet->mergeCells(
                    "A1:{$lastColumn}1"
                );


                $sheet->setCellValue(
                    'A1',
                    'NEW STOCK REQUIRED FOR ALL BRANCHES'
                );


                $sheet
                    ->getStyle(
                        "A1:{$lastColumn}1"
                    )
                    ->getFont()
                    ->setBold(
                        true
                    )
                    ->setSize(
                        16
                    );


                $sheet
                    ->getStyle(
                        "A1:{$lastColumn}1"
                    )
                    ->getAlignment()
                    ->setHorizontal(
                        Alignment::HORIZONTAL_CENTER
                    );


                /*
                |--------------------------------------------------------------------------
                | Order Details
                |--------------------------------------------------------------------------
                */

                $sheet->setCellValue(
                    'A2',
                    'Order: '
                    .
                    $data[
                        'order_number'
                    ]
                );


                $sheet->setCellValue(
                    'A3',
                    'Date: '
                    .
                    $data[
                        'order_date'
                    ]
                );


                /*
                |--------------------------------------------------------------------------
                | Brand Sections
                |--------------------------------------------------------------------------
                */

                $row =
                    5;


                foreach (
                    [
                        'DEFY',
                        'UNIVA',
                        'HISENSE',
                        'OTHER',
                    ]
                    as $brand
                ) {

                    $brandRows =
                        $data[
                            'brands'
                        ][
                            $brand
                        ]
                        ??
                        [];


                    if (
                        count(
                            $brandRows
                        )
                        ===
                        0
                    ) {

                        continue;

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Brand Header
                    |--------------------------------------------------------------------------
                    */

                    $sheet->mergeCells(
                        "A{$row}:{$lastColumn}{$row}"
                    );


                    $sheet->setCellValue(
                        "A{$row}",
                        $brand
                    );


                    $sheet
                        ->getStyle(
                            "A{$row}:{$lastColumn}{$row}"
                        )
                        ->getFont()
                        ->setBold(
                            true
                        )
                        ->setSize(
                            13
                        )
                        ->getColor()
                        ->setARGB(
                            'FFFFFFFF'
                        );


                    $sheet
                        ->getStyle(
                            "A{$row}:{$lastColumn}{$row}"
                        )
                        ->getFill()
                        ->setFillType(
                            Fill::FILL_SOLID
                        )
                        ->getStartColor()
                        ->setARGB(
                            'FF334155'
                        );


                    $row++;


                    /*
                    |--------------------------------------------------------------------------
                    | Table Header
                    |--------------------------------------------------------------------------
                    */

                    $sheet->setCellValue(
                        "A{$row}",
                        'MODEL'
                    );


                    $columnNumber =
                        2;


                    foreach (
                        $branches
                        as $branch
                    ) {

                        $column =
                            Coordinate::stringFromColumnIndex(
                                $columnNumber
                            );


                        $sheet->setCellValue(
                            "{$column}{$row}",
                            strtoupper(
                                $branch[
                                    'name'
                                ]
                            )
                        );


                        $columnNumber++;

                    }


                    $sheet
                        ->getStyle(
                            "A{$row}:{$lastColumn}{$row}"
                        )
                        ->getFont()
                        ->setBold(
                            true
                        )
                        ->getColor()
                        ->setARGB(
                            'FFFFFFFF'
                        );


                    $sheet
                        ->getStyle(
                            "A{$row}:{$lastColumn}{$row}"
                        )
                        ->getFill()
                        ->setFillType(
                            Fill::FILL_SOLID
                        )
                        ->getStartColor()
                        ->setARGB(
                            'FF475569'
                        );


                    $sheet
                        ->getStyle(
                            "A{$row}:{$lastColumn}{$row}"
                        )
                        ->getAlignment()
                        ->setHorizontal(
                            Alignment::HORIZONTAL_CENTER
                        );


                    $sheet
                        ->getStyle(
                            "A{$row}"
                        )
                        ->getAlignment()
                        ->setHorizontal(
                            Alignment::HORIZONTAL_LEFT
                        );


                    $row++;


                    /*
                    |--------------------------------------------------------------------------
                    | Product Rows
                    |--------------------------------------------------------------------------
                    */

                    foreach (
                        $brandRows
                        as $product
                    ) {

                        $sheet->setCellValue(
                            "A{$row}",
                            $product[
                                'model_number'
                            ]
                        );


                        $columnNumber =
                            2;


                        foreach (
                            $branches
                            as $branch
                        ) {

                            $column =
                                Coordinate::stringFromColumnIndex(
                                    $columnNumber
                                );


                            $quantity =
                                $product[
                                    'branches'
                                ][
                                    $branch[
                                        'id'
                                    ]
                                ][
                                    'order_quantity'
                                ]
                                ??
                                0;


                            $sheet->setCellValue(
                                "{$column}{$row}",
                                $quantity
                            );


                            $columnNumber++;

                        }


                        $sheet
                            ->getStyle(
                                "B{$row}:{$lastColumn}{$row}"
                            )
                            ->getAlignment()
                            ->setHorizontal(
                                Alignment::HORIZONTAL_CENTER
                            );


                        $row++;

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Space Between Brands
                    |--------------------------------------------------------------------------
                    */

                    $row +=
                        2;

                }


                /*
                |--------------------------------------------------------------------------
                | Widths
                |--------------------------------------------------------------------------
                */

                $sheet
                    ->getColumnDimension(
                        'A'
                    )
                    ->setWidth(
                        24
                    );


                for (
                    $columnNumber = 2;
                    $columnNumber <= $lastColumnNumber;
                    $columnNumber++
                ) {

                    $column =
                        Coordinate::stringFromColumnIndex(
                            $columnNumber
                        );


                    $sheet
                        ->getColumnDimension(
                            $column
                        )
                        ->setWidth(
                            16
                        );

                }


                /*
                |--------------------------------------------------------------------------
                | Borders
                |--------------------------------------------------------------------------
                */

                $usedRange =
                    "A1:"
                    .
                    $lastColumn
                    .
                    (
                        $row
                        -
                        1
                    );


                $sheet
                    ->getStyle(
                        $usedRange
                    )
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(
                        Border::BORDER_THIN
                    )
                    ->getColor()
                    ->setARGB(
                        'FFD9E0E8'
                    );


                /*
                |--------------------------------------------------------------------------
                | Output
                |--------------------------------------------------------------------------
                */

                $writer =
                    new Xlsx(
                        $spreadsheet
                    );


                $writer->save(
                    'php://output'
                );


                $spreadsheet
                    ->disconnectWorksheets();

            },

            $filename,

            [

                'Content-Type' =>
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',

            ]

        );

    }


    /**
     * =========================================================
     * FORMAT ORDER
     * =========================================================
     */
    private function formatOrder(
        NewRangeOrder $order
    ): array {

        $branches =
            NewRangeBranch::query()

                ->where(
                    'is_active',
                    true
                )

                ->whereRaw(
                    'LOWER(name) NOT LIKE ?',
                    ['%protec%']
                )

                ->orderBy(
                    'name'
                )

                ->get();


        $items =
            NewRangeOrderItem::query()

                ->where(
                    'order_id',
                    $order->id
                )

                ->get();


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
                    $product
                        ->model_number,

                'description' =>
                    $product
                        ->description,

            ];

        }


        $brands = [

            'DEFY' =>
                [],

            'UNIVA' =>
                [],

            'HISENSE' =>
                [],

            'OTHER' =>
                [],

        ];


        foreach (
            $items
            as $item
        ) {

            $brand =
                $item->brand;


            if (
                !isset(
                    $brands[
                        $brand
                    ]
                )
            ) {

                $brand =
                    'OTHER';

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


            $productId =
                $item->product_id;


            if (
                !isset(
                    $brands[
                        $brand
                    ][
                        $productId
                    ]
                )
            ) {

                $brands[
                    $brand
                ][
                    $productId
                ] = [

                    'product_id' =>
                        $productId,

                    'model_number' =>
                        $product[
                            'model_number'
                        ],

                    'description' =>
                        $product[
                            'description'
                        ],

                    'branches' =>
                        [],

                ];

            }


            $brands[
                $brand
            ][
                $productId
            ][
                'branches'
            ][
                $item->branch_id
            ] = [

                'item_id' =>
                    $item->id,

                'system_soh' =>
                    (float)
                    $item
                        ->system_soh,

                'minimum_quantity' =>
                    (float)
                    $item
                        ->minimum_quantity,

                'maximum_quantity' =>
                    (float)
                    $item
                        ->maximum_quantity,

                'suggested_quantity' =>
                    (float)
                    $item
                        ->suggested_quantity,

                'order_quantity' =>
                    (float)
                    $item
                        ->order_quantity,

            ];

        }


        foreach (
            $brands
            as $brand =>
                $rows
        ) {

            $rows =
                array_values(
                    $rows
                );


            usort(

                $rows,

                fn (
                    array $a,
                    array $b
                ): int =>

                    strcmp(
                        $a[
                            'model_number'
                        ],
                        $b[
                            'model_number'
                        ]
                    )

            );


            $brands[
                $brand
            ] =
                $rows;

        }


        return [

            'id' =>
                $order->id,

            'order_number' =>
                $order
                    ->order_number,

            'order_date' =>
                optional(
                    $order
                        ->order_date
                )
                    ->format(
                        'Y-m-d'
                    ),

            'status' =>
                $order
                    ->status,

            'notes' =>
                $order
                    ->notes,

            'branches' =>
                $branches
                    ->map(

                        fn ($branch) => [

                            'id' =>
                                $branch->id,

                            'name' =>
                                $branch->name,

                        ]

                    )
                    ->values()
                    ->toArray(),

            'brands' =>
                $brands,

        ];

    }


    /**
     * =========================================================
     * BRAND
     * =========================================================
     */
    private function getBrand(
        string $modelNumber
    ): string {

        $model =
            strtoupper(
                trim(
                    $modelNumber
                )
            );


        /*
        |--------------------------------------------------------------------------
        | DEFY
        |--------------------------------------------------------------------------
        |
        | Standard Defy models start with D.
        |
        | SI2322 is also Defy.
        |
        */

        if (
            str_starts_with(
                $model,
                'D'
            )
            ||
            str_starts_with(
                $model,
                'SI2322'
            )
        ) {

            return 'DEFY';

        }


        /*
        |--------------------------------------------------------------------------
        | UNIVA
        |--------------------------------------------------------------------------
        */

        if (
            str_starts_with(
                $model,
                'U'
            )
        ) {

            return 'UNIVA';

        }


        /*
        |--------------------------------------------------------------------------
        | HISENSE
        |--------------------------------------------------------------------------
        |
        | Hisense includes:
        |
        | W...
        | WT...
        | H225...
        |
        */

        if (
            str_starts_with(
                $model,
                'W'
            )
            ||
            str_starts_with(
                $model,
                'H225'
            )
        ) {

            return 'HISENSE';

        }


        /*
        |--------------------------------------------------------------------------
        | UNKNOWN / OTHER
        |--------------------------------------------------------------------------
        */

        return 'OTHER';

    }

    /**
     * =========================================================
     * SYSTEM STOCK MAP
     * =========================================================
     */
    private function getSystemStockMap(): array
    {
        $quantityColumn =
            $this
                ->getQuantityColumn();


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

                ->groupBy(

                    'branch_id',

                    'product_id',

                    'movement_type'

                )

                ->get();


        $movementMap =
            [];


        foreach (
            $movements
            as $movement
        ) {

            $key =
                $movement->branch_id
                .
                '|'
                .
                $movement->product_id;


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
                        $key
                    ][
                        'opening'
                    ] +=
                        $quantity;

                    break;


                case 'GRV':

                    $movementMap[
                        $key
                    ][
                        'grv'
                    ] +=
                        $quantity;

                    break;


                case 'IBT_IN':

                    $movementMap[
                        $key
                    ][
                        'ibt_in'
                    ] +=
                        $quantity;

                    break;


                case 'IBT_OUT':

                    $movementMap[
                        $key
                    ][
                        'ibt_out'
                    ] +=
                        $quantity;

                    break;


                case 'ADJUSTMENT_IN':

                    $movementMap[
                        $key
                    ][
                        'adjustment_in'
                    ] +=
                        $quantity;

                    break;


                case 'ADJUSTMENT_OUT':

                    $movementMap[
                        $key
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

                ->groupBy(
                    'branch_id',
                    'product_id'
                )

                ->get();


        $salesMap =
            [];


        foreach (
            $sales
            as $sale
        ) {

            $key =
                $sale->branch_id
                .
                '|'
                .
                $sale->product_id;


            $salesMap[
                $key
            ] =
                (float)
                $sale
                    ->quantity_sold;

        }


        /*
        |--------------------------------------------------------------------------
        | Final SOH
        |--------------------------------------------------------------------------
        */

        $keys =
            array_unique(

                array_merge(

                    array_keys(
                        $movementMap
                    ),

                    array_keys(
                        $salesMap
                    )

                )

            );


        $sohMap =
            [];


        foreach (
            $keys
            as $key
        ) {

            $movement =
                $movementMap[
                    $key
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
                    $key
                ]
                ??
                0;


            $sohMap[
                $key
            ] =

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

        }


        return $sohMap;

    }


    /**
     * =========================================================
     * ORDER NUMBER
     * =========================================================
     */
    private function generateOrderNumber(): string
    {
        do {

            $number =

                'ORD-'

                .

                now()->format(
                    'Ymd'
                )

                .

                '-'

                .

                random_int(
                    1000,
                    9999
                );


            $exists =
                NewRangeOrder::query()

                    ->where(
                        'order_number',
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
            'new_range_stock_movements does not contain a quantity or qty column.'
        );

    }
}
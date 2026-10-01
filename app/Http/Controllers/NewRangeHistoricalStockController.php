<?php

namespace App\Http\Controllers;

use App\Models\NewRangeBranch;
use App\Models\NewRangeProduct;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class NewRangeHistoricalStockController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Historical cut-off
    |--------------------------------------------------------------------------
    */

    private const HISTORICAL_CUTOFF =
        '2026-09-17';

    private const OPENING_STOCK_DATE =
        '2026-09-18';

    private const OPENING_REFERENCE =
        'HIST-SOH-2026-09-18';


    /**
     * =========================================================
     * PREVIEW HISTORICAL STOCK ON HAND
     * =========================================================
     */
    public function preview(
        Request $request
    ): JsonResponse {

        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls',
            ],
        ]);


        try {

            $analysis =
                $this->analyseWorkbook(
                    $request
                );


            return response()->json([

                'message' =>
                    'Historical NEW Range stock preview completed.',

                'historical_cutoff' =>
                    self::HISTORICAL_CUTOFF,

                'opening_stock_date' =>
                    self::OPENING_STOCK_DATE,

                'summary' =>
                    $analysis[
                        'summary'
                    ],

                'branches' =>
                    $analysis[
                        'branches'
                    ],

                'items' =>
                    $analysis[
                        'items'
                    ],

                'unmatched_products' =>
                    $analysis[
                        'unmatched_products'
                    ],

                'warnings_count' =>
                    count(
                        $analysis[
                            'warnings'
                        ]
                    ),

                'warnings' =>
                    array_slice(
                        $analysis[
                            'warnings'
                        ],
                        0,
                        100
                    ),

            ]);

        } catch (Throwable $exception) {

            return response()->json([

                'message' =>
                    'Unable to preview historical NEW Range stock.',

                'error' =>
                    $exception->getMessage(),

            ], 500);

        }

    }


    /**
     * =========================================================
     * IMPORT HISTORICAL OPENING STOCK
     * =========================================================
     */
    public function import(
        Request $request
    ): JsonResponse {

        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls',
            ],
        ]);


        try {

            /*
            |--------------------------------------------------------------------------
            | Determine actual database column names
            |--------------------------------------------------------------------------
            */

            $quantityColumn =
                $this->getQuantityColumn();


            $referenceColumn =
                $this->getReferenceColumn();


            /*
            |--------------------------------------------------------------------------
            | Duplicate protection
            |--------------------------------------------------------------------------
            */

            $existingQuery =
                DB::table(
                    'new_range_stock_movements'
                )
                    ->where(
                        'movement_type',
                        'OPENING'
                    );


            if ($referenceColumn) {

                $existingQuery
                    ->where(
                        $referenceColumn,
                        self::OPENING_REFERENCE
                    );

            } else {

                $existingQuery
                    ->where(
                        'notes',
                        'like',
                        '%'
                        . self::OPENING_REFERENCE
                        . '%'
                    );

            }


            if (
                $existingQuery->exists()
            ) {

                return response()->json([

                    'message' =>
                        'The historical NEW Range opening stock has already been imported.',

                    'reference' =>
                        self::OPENING_REFERENCE,

                    'opening_stock_date' =>
                        self::OPENING_STOCK_DATE,

                ], 409);

            }


            /*
            |--------------------------------------------------------------------------
            | Analyse workbook
            |--------------------------------------------------------------------------
            */

            $analysis =
                $this->analyseWorkbook(
                    $request
                );


            /*
            |--------------------------------------------------------------------------
            | Unmatched product protection
            |--------------------------------------------------------------------------
            */

            if (
                count(
                    $analysis[
                        'unmatched_products'
                    ]
                ) > 0
            ) {

                return response()->json([

                    'message' =>
                        'Import stopped because some historical stock products could not be matched.',

                    'unmatched_products' =>
                        $analysis[
                            'unmatched_products'
                        ],

                ], 422);

            }


            /*
            |--------------------------------------------------------------------------
            | Warning protection
            |--------------------------------------------------------------------------
            */

            if (
                count(
                    $analysis[
                        'warnings'
                    ]
                ) > 0
            ) {

                return response()->json([

                    'message' =>
                        'Import stopped because historical stock warnings remain.',

                    'warnings' =>
                        $analysis[
                            'warnings'
                        ],

                ], 422);

            }


            /*
            |--------------------------------------------------------------------------
            | No stock
            |--------------------------------------------------------------------------
            */

            if (
                $analysis[
                    'summary'
                ][
                    'units_on_hand'
                ] <= 0
            ) {

                return response()->json([

                    'message' =>
                        'No historical stock on hand was found.',

                ], 422);

            }


            /*
            |--------------------------------------------------------------------------
            | Import opening stock
            |--------------------------------------------------------------------------
            */

            $created =
                DB::transaction(
                    function () use (
                        $analysis,
                        $quantityColumn,
                        $referenceColumn
                    ) {

                        $count =
                            0;


                        foreach (
                            $analysis[
                                'stock_movements'
                            ] as $item
                        ) {

                            $row = [

                                'product_id' =>
                                    $item[
                                        'product_id'
                                    ],

                                'branch_id' =>
                                    $item[
                                        'branch_id'
                                    ],

                                'movement_type' =>
                                    'OPENING',

                                'movement_date' =>
                                    self::OPENING_STOCK_DATE,

                                $quantityColumn =>
                                    $item[
                                        'quantity'
                                    ],

                                'unit_cost_excl' =>
                                    $item[
                                        'unit_cost_excl'
                                    ],

                                'unit_cost_incl' =>
                                    $item[
                                        'unit_cost_incl'
                                    ],

                                'transfer_group_id' =>
                                    null,

                                'created_by' =>
                                    auth()->id(),

                                'notes' =>
                                    'Historical NEW Range opening stock baseline. '
                                    . 'Reference: '
                                    . self::OPENING_REFERENCE
                                    . '. Historical stock through '
                                    . self::HISTORICAL_CUTOFF
                                    . '.',

                                'created_at' =>
                                    now(),

                                'updated_at' =>
                                    now(),

                            ];


                            if ($referenceColumn) {

                                $row[
                                    $referenceColumn
                                ] =
                                    self::OPENING_REFERENCE;

                            }


                            DB::table(
                                'new_range_stock_movements'
                            )
                                ->insert(
                                    $row
                                );


                            $count++;

                        }


                        return
                            $count;

                    }
                );


            return response()->json([

                'message' =>
                    'Historical NEW Range opening stock imported successfully.',

                'reference' =>
                    self::OPENING_REFERENCE,

                'opening_stock_date' =>
                    self::OPENING_STOCK_DATE,

                'movements_created' =>
                    $created,

                'summary' =>
                    $analysis[
                        'summary'
                    ],

                'branches' =>
                    $analysis[
                        'branches'
                    ],

            ], 201);

        } catch (Throwable $exception) {

            return response()->json([

                'message' =>
                    'Unable to import historical NEW Range opening stock.',

                'error' =>
                    $exception->getMessage(),

            ], 500);

        }

    }


    /**
     * =========================================================
     * ANALYSE HISTORICAL WORKBOOK
     * =========================================================
     */
    private function analyseWorkbook(
        Request $request
    ): array {

        $uploadedFile =
            $request->file(
                'file'
            );


        $realPath =
            $uploadedFile
                ->getRealPath();


        if (!$realPath) {

            throw new \RuntimeException(
                'Unable to access uploaded historical workbook.'
            );

        }


        /*
        |--------------------------------------------------------------------------
        | Read workbook
        |--------------------------------------------------------------------------
        */

        $sheets =
            Excel::toArray(
                [],
                $uploadedFile
            );


        if (
            empty(
                $sheets
            )
        ) {

            throw new \RuntimeException(
                'Historical workbook contains no worksheets.'
            );

        }


        /*
        |--------------------------------------------------------------------------
        | Get worksheet names
        |--------------------------------------------------------------------------
        */

        $reader =
            IOFactory::createReaderForFile(
                $realPath
            );


        $reader
            ->setReadDataOnly(
                true
            );


        $spreadsheet =
            $reader->load(
                $realPath
            );


        $sheetNames =
            $spreadsheet
                ->getSheetNames();


        /*
        |--------------------------------------------------------------------------
        | Historical sheet -> database branch
        |--------------------------------------------------------------------------
        */

        $branchMap = [

            'CHINA' =>
                'China Gate',

            'BOSWORTH' =>
                'Bosworth',

            'WEST' =>
                'West',

            'SOWETO' =>
                'Soweto',

        ];


        /*
        |--------------------------------------------------------------------------
        | Load branches
        |--------------------------------------------------------------------------
        */

        $branches =
            NewRangeBranch::query()
                ->get()
                ->keyBy(
                    fn ($branch) =>
                        strtoupper(
                            trim(
                                $branch->name
                            )
                        )
                );


        /*
        |--------------------------------------------------------------------------
        | Load products
        |--------------------------------------------------------------------------
        */

        $products =
            NewRangeProduct::query()
                ->get()
                ->keyBy(
                    fn ($product) =>
                        strtoupper(
                            trim(
                                $product
                                    ->model_number
                            )
                        )
                );


        /*
        |--------------------------------------------------------------------------
        | Summary
        |--------------------------------------------------------------------------
        */

        $summary = [

            'branches_found' =>
                0,

            'stock_rows_found' =>
                0,

            'units_on_hand' =>
                0,

            'stock_value_incl' =>
                0,

            'stock_value_excl' =>
                0,

            'outgoing_ibt_rows_excluded' =>
                0,

            'future_receipt_rows_excluded' =>
                0,

            'sold_rows_ignored' =>
                0,

            'undated_stock_rows_included' =>
                0,

        ];


        $branchSummary =
            [];


        $warnings =
            [];


        $unmatchedProducts =
            [];


        /*
        |--------------------------------------------------------------------------
        | Aggregated stock
        |--------------------------------------------------------------------------
        */

        $stock =
            [];


        $cutoffDate =
            Carbon::parse(
                self::HISTORICAL_CUTOFF
            )
                ->startOfDay();


        /*
        |--------------------------------------------------------------------------
        | Process worksheets
        |--------------------------------------------------------------------------
        */

        foreach (
            $sheets as $sheetIndex => $rows
        ) {

            $sheetName =
                strtoupper(
                    trim(
                        $sheetNames[
                            $sheetIndex
                        ] ?? ''
                    )
                );


            /*
            |--------------------------------------------------------------------------
            | Ignore non historical sheets
            |--------------------------------------------------------------------------
            */

            if (
                !$sheetName ||
                !isset(
                    $branchMap[
                        $sheetName
                    ]
                )
            ) {

                continue;

            }


            $branchName =
                $branchMap[
                    $sheetName
                ];


            $branch =
                $branches->get(
                    strtoupper(
                        $branchName
                    )
                );


            if (!$branch) {

                $warnings[] = [

                    'sheet' =>
                        $sheetName,

                    'message' =>
                        "Database branch '{$branchName}' does not exist.",

                ];


                continue;

            }


            $summary[
                'branches_found'
            ]++;


            $branchSummary[
                $branchName
            ] = [

                'branch_id' =>
                    $branch->id,

                'stock_rows' =>
                    0,

                'units_on_hand' =>
                    0,

                'stock_value_incl' =>
                    0,

                'stock_value_excl' =>
                    0,

                'undated_stock_rows_included' =>
                    0,

            ];


            /*
            |--------------------------------------------------------------------------
            | Historical workbook writes model once per block
            |--------------------------------------------------------------------------
            */

            $currentModel =
                null;


            $currentProduct =
                null;


            foreach (
                $rows as $rowIndex => $row
            ) {

                $excelRow =
                    $rowIndex + 1;


                /*
                |--------------------------------------------------------------------------
                | Column mapping
                |--------------------------------------------------------------------------
                |
                | A = Model
                | B = Invoice
                | C = GRV / IBT
                | D = Date Received
                | E = Qty Received
                | F = Cost Incl
                | G = Sales Date
                | H = Sales Qty
                | I = Selling Price Incl
                |
                */

                $columnA =
                    $row[0] ?? null;

                $invoice =
                    $row[1] ?? null;

                $reference =
                    $row[2] ?? null;

                $receivedDate =
                    $row[3] ?? null;

                $receivedQty =
                    $row[4] ?? null;

                $costIncl =
                    $row[5] ?? null;

                $saleDate =
                    $row[6] ?? null;

                $saleQty =
                    $row[7] ?? null;

                $salePrice =
                    $row[8] ?? null;


                /*
                |--------------------------------------------------------------------------
                | Detect new model block
                |--------------------------------------------------------------------------
                */

                if (
                    is_string(
                        $columnA
                    ) &&
                    trim(
                        $columnA
                    ) !== ''
                ) {

                    $possibleModel =
                        $this
                            ->normalizeHistoricalModel(
                                $columnA
                            );


                    if (
                        $possibleModel !==
                        null
                    ) {

                        $currentModel =
                            $possibleModel;


                        $currentProduct =
                            $products->get(
                                $currentModel
                            );

                    }

                }


                /*
                |--------------------------------------------------------------------------
                | Ignore until model block exists
                |--------------------------------------------------------------------------
                */

                if (!$currentModel) {

                    continue;

                }


                /*
                |--------------------------------------------------------------------------
                | Must have received quantity and cost
                |--------------------------------------------------------------------------
                */

                if (
                    !$this->isValidNumber(
                        $receivedQty
                    ) ||
                    (float)
                    $receivedQty <= 0 ||
                    !$this->isValidNumber(
                        $costIncl
                    ) ||
                    (float)
                    $costIncl <= 0
                ) {

                    continue;

                }


                /*
                |--------------------------------------------------------------------------
                | Detect whether row is already sold
                |--------------------------------------------------------------------------
                */

                $hasSaleDate =
                    !$this->isBlank(
                        $saleDate
                    );


                $hasSaleQty =
                    $this->isValidNumber(
                        $saleQty
                    ) &&
                    abs(
                        (float)
                        $saleQty
                    ) > 0;


                $hasSalePrice =
                    $this->isValidNumber(
                        $salePrice
                    ) &&
                    abs(
                        (float)
                        $salePrice
                    ) > 0;


                /*
                |--------------------------------------------------------------------------
                | Sold rows do not form part of opening SOH
                |--------------------------------------------------------------------------
                */

                if (
                    $hasSaleDate ||
                    $hasSaleQty ||
                    $hasSalePrice
                ) {

                    $summary[
                        'sold_rows_ignored'
                    ]++;


                    continue;

                }


                /*
                |--------------------------------------------------------------------------
                | Exclude outgoing IBTs
                |--------------------------------------------------------------------------
                */

                $invoiceText =
                    strtoupper(
                        trim(
                            (string)
                            $invoice
                        )
                    );


                $referenceText =
                    strtoupper(
                        trim(
                            (string)
                            $reference
                        )
                    );


                if (
                    str_starts_with(
                        $invoiceText,
                        'IBTO'
                    ) ||
                    str_starts_with(
                        $referenceText,
                        'IBTO'
                    )
                ) {

                    $summary[
                        'outgoing_ibt_rows_excluded'
                    ]++;


                    continue;

                }


                /*
                |--------------------------------------------------------------------------
                | Resolve historical received date
                |--------------------------------------------------------------------------
                */

                $resolvedReceivedDate =
                    $this->parseDate(
                        $receivedDate
                    );


                /*
                |--------------------------------------------------------------------------
                | Sometimes invoice column itself contains a valid date
                |--------------------------------------------------------------------------
                */

                $invoiceDate =
                    $this->parseDate(
                        $invoice
                    );


                /*
                |--------------------------------------------------------------------------
                | Use invoice date where received date is unusable or future
                |--------------------------------------------------------------------------
                */

                if (
                    (
                        !$resolvedReceivedDate ||
                        $resolvedReceivedDate
                            ->greaterThan(
                                $cutoffDate
                            )
                    ) &&
                    $invoiceDate &&
                    $invoiceDate
                        ->lessThanOrEqualTo(
                            $cutoffDate
                        )
                ) {

                    $resolvedReceivedDate =
                        $invoiceDate;

                }


                /*
                |--------------------------------------------------------------------------
                | Undated stock
                |--------------------------------------------------------------------------
                |
                | Some historical rows have:
                |
                | Qty Received
                | Cost Incl
                | no sales information
                |
                | but no received date.
                |
                | They are still valid stock-on-hand.
                |
                */

                if (!$resolvedReceivedDate) {

                    $summary[
                        'undated_stock_rows_included'
                    ]++;


                    $branchSummary[
                        $branchName
                    ][
                        'undated_stock_rows_included'
                    ]++;

                }


                /*
                |--------------------------------------------------------------------------
                | Exclude stock definitely received after historical cut-off
                |--------------------------------------------------------------------------
                */

                if (
                    $resolvedReceivedDate &&
                    $resolvedReceivedDate
                        ->greaterThan(
                            $cutoffDate
                        )
                ) {

                    $summary[
                        'future_receipt_rows_excluded'
                    ]++;


                    continue;

                }


                /*
                |--------------------------------------------------------------------------
                | Product must exist
                |--------------------------------------------------------------------------
                */

                if (!$currentProduct) {

                    $unmatchedProducts[
                        $currentModel
                    ] = [

                        'model' =>
                            $currentModel,

                        'branch' =>
                            $branchName,

                        'sheet' =>
                            $sheetName,

                        'row' =>
                            $excelRow,

                    ];


                    continue;

                }


                /*
                |--------------------------------------------------------------------------
                | Quantity
                |--------------------------------------------------------------------------
                */

                $qty =
                    (float)
                    $receivedQty;


                /*
                |--------------------------------------------------------------------------
                | Unit cost incl VAT
                |--------------------------------------------------------------------------
                */

                $unitCostIncl =
                    round(
                        (float)
                        $costIncl,
                        2
                    );


                /*
                |--------------------------------------------------------------------------
                | Total cost incl VAT
                |--------------------------------------------------------------------------
                */

                $totalCostIncl =
                    round(
                        $qty *
                        $unitCostIncl,
                        2
                    );


                /*
                |--------------------------------------------------------------------------
                | Aggregate key
                |--------------------------------------------------------------------------
                */

                $key =
                    $branch->id
                    . '|'
                    . $currentProduct->id;


                /*
                |--------------------------------------------------------------------------
                | Create aggregate row
                |--------------------------------------------------------------------------
                */

                if (
                    !isset(
                        $stock[
                            $key
                        ]
                    )
                ) {

                    $stock[
                        $key
                    ] = [

                        'branch_id' =>
                            $branch->id,

                        'branch_name' =>
                            $branchName,

                        'product_id' =>
                            $currentProduct->id,

                        'model_number' =>
                            $currentProduct
                                ->model_number,

                        'description' =>
                            $currentProduct
                                ->description,

                        'quantity' =>
                            0,

                        'total_cost_incl' =>
                            0,

                        'source_rows' =>
                            [],

                        'undated_units' =>
                            0,

                    ];

                }


                /*
                |--------------------------------------------------------------------------
                | Add quantity
                |--------------------------------------------------------------------------
                */

                $stock[
                    $key
                ][
                    'quantity'
                ] +=
                    $qty;


                /*
                |--------------------------------------------------------------------------
                | Add cost
                |--------------------------------------------------------------------------
                */

                $stock[
                    $key
                ][
                    'total_cost_incl'
                ] +=
                    $totalCostIncl;


                /*
                |--------------------------------------------------------------------------
                | Store source row
                |--------------------------------------------------------------------------
                */

                $stock[
                    $key
                ][
                    'source_rows'
                ][] =
                    $excelRow;


                /*
                |--------------------------------------------------------------------------
                | Track undated units
                |--------------------------------------------------------------------------
                */

                if (!$resolvedReceivedDate) {

                    $stock[
                        $key
                    ][
                        'undated_units'
                    ] +=
                        $qty;

                }


                /*
                |--------------------------------------------------------------------------
                | Overall summary
                |--------------------------------------------------------------------------
                */

                $summary[
                    'stock_rows_found'
                ]++;


                $summary[
                    'units_on_hand'
                ] +=
                    $qty;


                $summary[
                    'stock_value_incl'
                ] +=
                    $totalCostIncl;


                /*
                |--------------------------------------------------------------------------
                | Branch summary
                |--------------------------------------------------------------------------
                */

                $branchSummary[
                    $branchName
                ][
                    'stock_rows'
                ]++;


                $branchSummary[
                    $branchName
                ][
                    'units_on_hand'
                ] +=
                    $qty;


                $branchSummary[
                    $branchName
                ][
                    'stock_value_incl'
                ] +=
                    $totalCostIncl;

            }

        }


        /*
        |--------------------------------------------------------------------------
        | Build aggregated opening movements
        |--------------------------------------------------------------------------
        */

        $stockMovements =
            [];


        $items =
            [];


        foreach (
            $stock as $item
        ) {

            $quantity =
                (float)
                $item[
                    'quantity'
                ];


            $totalIncl =
                round(
                    (float)
                    $item[
                        'total_cost_incl'
                    ],
                    2
                );


            /*
            |--------------------------------------------------------------------------
            | Weighted average unit cost incl VAT
            |--------------------------------------------------------------------------
            */

            $unitCostIncl =
                $quantity > 0
                    ? round(
                        $totalIncl /
                        $quantity,
                        2
                    )
                    : 0;


            /*
            |--------------------------------------------------------------------------
            | VAT exclusive unit cost
            |--------------------------------------------------------------------------
            */

            $unitCostExcl =
                round(
                    $unitCostIncl /
                    1.15,
                    2
                );


            /*
            |--------------------------------------------------------------------------
            | Total cost excl VAT
            |--------------------------------------------------------------------------
            */

            $totalExcl =
                round(
                    $unitCostExcl *
                    $quantity,
                    2
                );


            /*
            |--------------------------------------------------------------------------
            | Movement data
            |--------------------------------------------------------------------------
            */

            $stockMovements[] = [

                'branch_id' =>
                    $item[
                        'branch_id'
                    ],

                'product_id' =>
                    $item[
                        'product_id'
                    ],

                'quantity' =>
                    $quantity,

                'unit_cost_incl' =>
                    $unitCostIncl,

                'unit_cost_excl' =>
                    $unitCostExcl,

            ];


            /*
            |--------------------------------------------------------------------------
            | Preview data
            |--------------------------------------------------------------------------
            */

            $items[] = [

                'branch_id' =>
                    $item[
                        'branch_id'
                    ],

                'branch_name' =>
                    $item[
                        'branch_name'
                    ],

                'product_id' =>
                    $item[
                        'product_id'
                    ],

                'model_number' =>
                    $item[
                        'model_number'
                    ],

                'description' =>
                    $item[
                        'description'
                    ],

                'quantity' =>
                    $quantity,

                'unit_cost_incl' =>
                    $unitCostIncl,

                'unit_cost_excl' =>
                    $unitCostExcl,

                'total_cost_incl' =>
                    $totalIncl,

                'total_cost_excl' =>
                    $totalExcl,

                'undated_units' =>
                    $item[
                        'undated_units'
                    ],

                'source_rows' =>
                    $item[
                        'source_rows'
                    ],

            ];

        }


        /*
        |--------------------------------------------------------------------------
        | Final overall totals
        |--------------------------------------------------------------------------
        */

        $summary[
            'units_on_hand'
        ] =
            round(
                $summary[
                    'units_on_hand'
                ],
                2
            );


        $summary[
            'stock_value_incl'
        ] =
            round(
                $summary[
                    'stock_value_incl'
                ],
                2
            );


        $summary[
            'stock_value_excl'
        ] =
            round(
                $summary[
                    'stock_value_incl'
                ] /
                1.15,
                2
            );


        /*
        |--------------------------------------------------------------------------
        | Final branch totals
        |--------------------------------------------------------------------------
        */

        foreach (
            $branchSummary
            as $branchName => $values
        ) {

            $branchSummary[
                $branchName
            ][
                'units_on_hand'
            ] =
                round(
                    $values[
                        'units_on_hand'
                    ],
                    2
                );


            $branchSummary[
                $branchName
            ][
                'stock_value_incl'
            ] =
                round(
                    $values[
                        'stock_value_incl'
                    ],
                    2
                );


            $branchSummary[
                $branchName
            ][
                'stock_value_excl'
            ] =
                round(
                    $values[
                        'stock_value_incl'
                    ] /
                    1.15,
                    2
                );

        }


        /*
        |--------------------------------------------------------------------------
        | Sort model preview
        |--------------------------------------------------------------------------
        */

        usort(
            $items,
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
        | Close spreadsheet
        |--------------------------------------------------------------------------
        */

        $spreadsheet
            ->disconnectWorksheets();


        unset(
            $spreadsheet
        );


        return [

            'summary' =>
                $summary,

            'branches' =>
                $branchSummary,

            'items' =>
                $items,

            'stock_movements' =>
                $stockMovements,

            'unmatched_products' =>
                array_values(
                    $unmatchedProducts
                ),

            'warnings' =>
                $warnings,

        ];

    }


    /**
     * =========================================================
     * NORMALIZE HISTORICAL MODEL
     * =========================================================
     */
    private function normalizeHistoricalModel(
        string $value
    ): ?string {

        $value =
            strtoupper(
                trim(
                    $value
                )
            );


        /*
        |--------------------------------------------------------------------------
        | Ignore headings
        |--------------------------------------------------------------------------
        */

        $ignored = [

            'MODEL',
            'SOH',
            'ON - HAND',
            'ON-HAND',
            'RE - ORDER',
            'RE-ORDER',
            'OPEN STOCK',

        ];


        if (
            $value === '' ||
            in_array(
                $value,
                $ignored,
                true
            )
        ) {

            return null;

        }


        /*
        |--------------------------------------------------------------------------
        | Must contain a letter
        |--------------------------------------------------------------------------
        */

        if (
            !preg_match(
                '/[A-Z]/',
                $value
            )
        ) {

            return null;

        }


        /*
        |--------------------------------------------------------------------------
        | Only model-like values
        |--------------------------------------------------------------------------
        */

        if (
            !preg_match(
                '/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/',
                $value
            )
        ) {

            return null;

        }


        /*
        |--------------------------------------------------------------------------
        | Known historical alias
        |--------------------------------------------------------------------------
        */

        if (
            $value ===
            'U246B-1'
        ) {

            $value =
                'U246B';

        }


        /*
        |--------------------------------------------------------------------------
        | Already NEW
        |--------------------------------------------------------------------------
        */

        if (
            str_contains(
                $value,
                '-NEW'
            )
        ) {

            return preg_replace(
                '/-NEW-\d+$/',
                '-NEW',
                $value
            );

        }


        return
            $value
            . '-NEW';

    }


    /**
     * =========================================================
     * PARSE DATE
     * =========================================================
     */
    private function parseDate(
        mixed $value
    ): ?Carbon {

        if (
            $value === null ||
            $value === ''
        ) {

            return null;

        }


        /*
        |--------------------------------------------------------------------------
        | Date object
        |--------------------------------------------------------------------------
        */

        if (
            $value instanceof
            \DateTimeInterface
        ) {

            return Carbon::instance(
                \DateTime::createFromInterface(
                    $value
                )
            )
                ->startOfDay();

        }


        /*
        |--------------------------------------------------------------------------
        | Excel serial
        |--------------------------------------------------------------------------
        */

        if (
            is_numeric(
                $value
            ) &&
            (float)
            $value > 1 &&
            (float)
            $value < 100000
        ) {

            try {

                return Carbon::instance(
                    ExcelDate::excelToDateTimeObject(
                        (float)
                        $value
                    )
                )
                    ->startOfDay();

            } catch (Throwable $exception) {

                return null;

            }

        }


        /*
        |--------------------------------------------------------------------------
        | String date
        |--------------------------------------------------------------------------
        */

        $text =
            trim(
                (string)
                $value
            );


        foreach (
            [
                'Y-m-d',
                'Y/m/d',
                'd/m/Y',
                'd.m.Y',
                'd-m-Y',
                'd/m/y',
                'd.m.y',
                'd-m-y',
            ]
            as $format
        ) {

            try {

                $date =
                    Carbon::createFromFormat(
                        $format,
                        $text
                    );


                if (
                    $date &&
                    $date->format(
                        $format
                    ) ===
                    $text
                ) {

                    return
                        $date->startOfDay();

                }

            } catch (Throwable $exception) {

                // Try next format.

            }

        }


        return null;

    }


    /**
     * =========================================================
     * BLANK VALUE
     * =========================================================
     */
    private function isBlank(
        mixed $value
    ): bool {

        if (
            $value === null
        ) {

            return true;

        }


        if (
            is_string(
                $value
            )
        ) {

            return
                trim(
                    $value
                ) === '';

        }


        return false;

    }


    /**
     * =========================================================
     * VALID NUMBER
     * =========================================================
     */
    private function isValidNumber(
        mixed $value
    ): bool {

        return
            is_numeric(
                $value
            ) &&
            is_finite(
                (float)
                $value
            );

    }


    /**
     * =========================================================
     * STOCK QUANTITY COLUMN
     * =========================================================
     */
    private function getQuantityColumn(): string {

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


    /**
     * =========================================================
     * STOCK REFERENCE COLUMN
     * =========================================================
     */
    private function getReferenceColumn(): ?string {

        if (
            Schema::hasColumn(
                'new_range_stock_movements',
                'reference_number'
            )
        ) {

            return
                'reference_number';

        }


        if (
            Schema::hasColumn(
                'new_range_stock_movements',
                'reference'
            )
        ) {

            return
                'reference';

        }


        return null;

    }
}
<?php

namespace App\Http\Controllers;

use App\Models\NewRangeBranch;
use App\Models\NewRangeProduct;
use App\Models\NewRangeSale;
use App\Models\NewRangeSalesImport;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class NewRangeSalesImportController extends Controller
{
    /**
     * =========================================================
     * PREVIEW DAILY SALES WORKBOOK
     * =========================================================
     *
     * Does NOT write anything to the database.
     *
     * The sales date is detected automatically from the
     * transaction rows in the workbook.
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
                    'NEW range daily sales preview completed.',

                'detected_sales_date' =>
                    $analysis[
                        'detected_sales_date'
                    ],

                'detected_sales_dates' =>
                    $analysis[
                        'detected_sales_dates'
                    ],

                'summary' =>
                    $analysis[
                        'summary'
                    ],

                'branches' =>
                    $analysis[
                        'branches'
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
                    'Unable to preview NEW range daily sales workbook.',

                'error' =>
                    $exception
                        ->getMessage(),

            ], 500);

        }

    }


    /**
     * =========================================================
     * IMPORT DAILY SALES
     * =========================================================
     *
     * Sales date is detected from the workbook.
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

            // ==================================================
            // FILE
            // ==================================================

            $uploadedFile =
                $request->file(
                    'file'
                );


            $realPath =
                $uploadedFile
                    ->getRealPath();


            if (!$realPath) {

                return response()->json([

                    'message' =>
                        'Unable to access uploaded workbook.',

                ], 422);

            }


            // ==================================================
            // FILE HASH
            // ==================================================

            $fileHash =
                hash_file(
                    'sha256',
                    $realPath
                );


            if (!$fileHash) {

                return response()->json([

                    'message' =>
                        'Unable to calculate workbook hash.',

                ], 422);

            }


            // ==================================================
            // DUPLICATE FILE CHECK
            // ==================================================

            $existingImport =
                NewRangeSalesImport::query()
                    ->where(
                        'file_hash',
                        $fileHash
                    )
                    ->first();


            if ($existingImport) {

                return response()->json([

                    'message' =>
                        'This sales workbook has already been imported.',

                    'existing_import' => [

                        'id' =>
                            $existingImport
                                ->id,

                        'source_filename' =>
                            $existingImport
                                ->source_filename,

                        'sales_date' =>
                            $existingImport
                                ->sales_date,

                        'status' =>
                            $existingImport
                                ->status,

                        'rows_imported' =>
                            $existingImport
                                ->rows_imported,

                        'imported_at' =>
                            $existingImport
                                ->imported_at,

                    ],

                ], 409);

            }


            // ==================================================
            // ANALYSE WORKBOOK
            // ==================================================

            $analysis =
                $this->analyseWorkbook(
                    $request
                );


            // ==================================================
            // NO NEW RANGE SALES
            // ==================================================

            if (
                $analysis[
                    'summary'
                ][
                    'sales_rows'
                ] === 0
            ) {

                return response()->json([

                    'message' =>
                        'No NEW range sales were found in the workbook.',

                ], 422);

            }


            // ==================================================
            // SALES DATE MUST BE DETECTED
            // ==================================================

            if (
                !$analysis[
                    'detected_sales_date'
                ]
            ) {

                return response()->json([

                    'message' =>
                        'Import stopped because the sales date could not be determined.',

                    'detected_sales_dates' =>
                        $analysis[
                            'detected_sales_dates'
                        ],

                    'warnings' =>
                        $analysis[
                            'warnings'
                        ],

                ], 422);

            }


            // ==================================================
            // ONLY ONE SALES DATE ALLOWED
            // ==================================================

            if (
                count(
                    $analysis[
                        'detected_sales_dates'
                    ]
                ) !== 1
            ) {

                return response()->json([

                    'message' =>
                        'Import stopped because more than one sales date was detected in the workbook.',

                    'detected_sales_dates' =>
                        $analysis[
                            'detected_sales_dates'
                        ],

                    'warnings' =>
                        $analysis[
                            'warnings'
                        ],

                ], 422);

            }


            // ==================================================
            // UNMATCHED PRODUCTS
            // ==================================================

            if (
                count(
                    $analysis[
                        'unmatched_products'
                    ]
                ) > 0
            ) {

                return response()->json([

                    'message' =>
                        'Import stopped because some NEW range products could not be matched.',

                    'unmatched_products' =>
                        $analysis[
                            'unmatched_products'
                        ],

                ], 422);

            }


            // ==================================================
            // WARNINGS
            // ==================================================

            if (
                count(
                    $analysis[
                        'warnings'
                    ]
                ) > 0
            ) {

                return response()->json([

                    'message' =>
                        'Import stopped because preview warnings remain.',

                    'warnings' =>
                        $analysis[
                            'warnings'
                        ],

                ], 422);

            }


            // ==================================================
            // SALES DATE
            // ==================================================

            $detectedSalesDate =
                $analysis[
                    'detected_sales_date'
                ];

            // ==================================================
                    // HISTORICAL IMPORT CUT-OFF
                    // ==================================================
                    //
                    // Historical migration already contains sales
                    // through 17 September 2026.
                    //
                    // Daily imports may only start from 18 September 2026.
                    //

                    $historicalCutoff =
                        Carbon::create(
                            2026,
                            9,
                            17
                        )->startOfDay();


                    $importDate =
                        Carbon::parse(
                            $detectedSalesDate
                        )->startOfDay();


                    if (
                        $importDate->lessThanOrEqualTo(
                            $historicalCutoff
                        )
                    ) {

                        return response()->json([

                            'message' =>
                                'This sales date is already covered by the historical migration.',

                            'detected_sales_date' =>
                                $detectedSalesDate,

                            'historical_cutoff' =>
                                '2026-09-17',

                            'first_allowed_daily_sales_date' =>
                                '2026-09-18',

                        ], 422);

                    }
            // ==================================================
            // DATABASE TRANSACTION
            // ==================================================

            $result =
                DB::transaction(
                    function () use (
                        $uploadedFile,
                        $fileHash,
                        $analysis,
                        $detectedSalesDate
                    ) {

                        // ==========================================
                        // IMPORT HEADER
                        // ==========================================

                        $salesImport =
                            NewRangeSalesImport::create([

                                'source_filename' =>
                                    $uploadedFile
                                        ->getClientOriginalName(),

                                'file_hash' =>
                                    $fileHash,

                                'sales_date' =>
                                    $detectedSalesDate,

                                'status' =>
                                    'processing',

                                'rows_read' =>
                                    $analysis[
                                        'rows_read'
                                    ],

                                'rows_imported' =>
                                    0,

                                'rows_skipped' =>
                                    $analysis[
                                        'summary'
                                    ][
                                        'rows_skipped'
                                    ],

                                'rows_failed' =>
                                    0,

                                'imported_by' =>
                                    auth()->id(),

                                'imported_at' =>
                                    null,

                                'notes' =>
                                    'Daily NEW range branch sales import. '
                                    . 'Sales date detected automatically from workbook: '
                                    . $detectedSalesDate,

                            ]);


                        // ==========================================
                        // IMPORT SALES
                        // ==========================================

                        $importedCount =
                            0;


                        foreach (
                            $analysis[
                                'sales'
                            ] as $sale
                        ) {

                            NewRangeSale::create([

                                'sales_import_id' =>
                                    $salesImport->id,

                                'product_id' =>
                                    $sale[
                                        'product_id'
                                    ],

                                'branch_id' =>
                                    $sale[
                                        'branch_id'
                                    ],

                                'sale_date' =>
                                    $sale[
                                        'sale_date'
                                    ],

                                'original_plu' =>
                                    $sale[
                                        'original_plu'
                                    ],

                                'description' =>
                                    $sale[
                                        'description'
                                    ],

                                'quantity' =>
                                    $sale[
                                        'quantity'
                                    ],

                                'sales_excl' =>
                                    $sale[
                                        'sales_excl'
                                    ],

                                'sales_incl' =>
                                    $sale[
                                        'sales_incl'
                                    ],

                                'unit_cost_excl' =>
                                    $sale[
                                        'unit_cost_excl'
                                    ],

                                'total_cost_excl' =>
                                    $sale[
                                        'total_cost_excl'
                                    ],

                                'gross_profit' =>
                                    $sale[
                                        'gross_profit'
                                    ],

                                'gp_percentage' =>
                                    $sale[
                                        'gp_percentage'
                                    ],

                                'day_period' =>
                                    $sale[
                                        'day_period'
                                    ],

                                'source_sheet' =>
                                    $sale[
                                        'source_sheet'
                                    ],

                                'source_row' =>
                                    $sale[
                                        'source_row'
                                    ],

                            ]);


                            $importedCount++;

                        }


                        // ==========================================
                        // VERIFY COUNT
                        // ==========================================

                        if (
                            $importedCount !==
                            $analysis[
                                'summary'
                            ][
                                'sales_rows'
                            ]
                        ) {

                            throw new \RuntimeException(
                                'Daily sales import count mismatch. Transaction cancelled.'
                            );

                        }


                        // ==========================================
                        // COMPLETE IMPORT
                        // ==========================================

                        $salesImport->update([

                            'status' =>
                                'completed',

                            'rows_imported' =>
                                $importedCount,

                            'rows_failed' =>
                                0,

                            'imported_at' =>
                                now(),

                            'notes' =>
                                'Daily NEW range sales imported successfully. '
                                . 'Detected sales date: '
                                . $detectedSalesDate
                                . '. Rows imported: '
                                . $importedCount
                                . '.',

                        ]);


                        return [

                            'sales_import_id' =>
                                $salesImport
                                    ->id,

                            'rows_imported' =>
                                $importedCount,

                        ];

                    }
                );


            // ==================================================
            // SUCCESS
            // ==================================================

            return response()->json([

                'message' =>
                    'NEW range daily sales imported successfully.',

                'sales_import_id' =>
                    $result[
                        'sales_import_id'
                    ],

                'sales_date' =>
                    $detectedSalesDate,

                'summary' => [

                    'sales_rows_imported' =>
                        $result[
                            'rows_imported'
                        ],

                    'units_sold' =>
                        $analysis[
                            'summary'
                        ][
                            'units_sold'
                        ],

                    'sales_incl' =>
                        $analysis[
                            'summary'
                        ][
                            'sales_incl'
                        ],

                    'sales_excl' =>
                        $analysis[
                            'summary'
                        ][
                            'sales_excl'
                        ],

                    'total_cost_excl' =>
                        $analysis[
                            'summary'
                        ][
                            'total_cost_excl'
                        ],

                    'gross_profit' =>
                        $analysis[
                            'summary'
                        ][
                            'gross_profit'
                        ],

                    'gp_percentage' =>
                        $analysis[
                            'summary'
                        ][
                            'gp_percentage'
                        ],

                ],

                'branches' =>
                    $analysis[
                        'branches'
                    ],

            ], 201);

        } catch (Throwable $exception) {

            return response()->json([

                'message' =>
                    'Unable to import NEW range daily sales.',

                'error' =>
                    $exception
                        ->getMessage(),

            ], 500);

        }

    }


    /**
     * =========================================================
     * ANALYSE WORKBOOK
     * =========================================================
     */
    private function analyseWorkbook(
        Request $request
    ): array {

        // ======================================================
        // FILE
        // ======================================================

        $uploadedFile =
            $request->file(
                'file'
            );


        $realPath =
            $uploadedFile
                ->getRealPath();


        if (!$realPath) {

            throw new \RuntimeException(
                'Unable to access workbook.'
            );

        }


        // ======================================================
        // READ WORKBOOK DATA
        // ======================================================

        $sheets =
            Excel::toArray(
                [],
                $uploadedFile
            );


        if (empty($sheets)) {

            throw new \RuntimeException(
                'Workbook contains no worksheets.'
            );

        }


        // ======================================================
        // LOAD WORKBOOK FOR ACTUAL SHEET NAMES
        // ======================================================

        $reader =
            IOFactory::createReaderForFile(
                $realPath
            );


        $reader->setReadDataOnly(
            true
        );


        $spreadsheet =
            $reader->load(
                $realPath
            );


        $sheetNames =
            $spreadsheet
                ->getSheetNames();


        // ======================================================
        // ACTIVE BRANCHES
        // ======================================================

        $branches =
            NewRangeBranch::query()
                ->where(
                    'is_active',
                    true
                )
                ->get();


        // ======================================================
        // KEY BRANCHES BY SALES SHEET NAME
        // ======================================================

        $branchesBySheet =
            $branches
                ->filter(
                    fn ($branch) =>
                        !empty(
                            $branch
                                ->sales_sheet_name
                        )
                )
                ->keyBy(
                    fn ($branch) =>
                        strtoupper(
                            trim(
                                $branch
                                    ->sales_sheet_name
                            )
                        )
                );


        // ======================================================
        // ACTIVE PRODUCTS
        // ======================================================

        $products =
            NewRangeProduct::query()
                ->where(
                    'is_active',
                    true
                )
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


        // ======================================================
        // SUMMARY
        // ======================================================

        $summary = [

            'branches_found' =>
                0,

            'sales_rows' =>
                0,

            'units_sold' =>
                0,

            'sales_incl' =>
                0,

            'sales_excl' =>
                0,

            'total_cost_excl' =>
                0,

            'gross_profit' =>
                0,

            'gp_percentage' =>
                0,

            'rows_skipped' =>
                0,

        ];


        // ======================================================
        // DATA COLLECTION
        // ======================================================

        $branchSummary =
            [];


        $warnings =
            [];


        $unmatchedProducts =
            [];


        $sales =
            [];


        $rowsRead =
            0;


        /*
        |--------------------------------------------------------------------------
        | SALES DATES FOUND IN NEW RANGE TRANSACTIONS
        |--------------------------------------------------------------------------
        |
        | Key:
        |
        | 2026-09-15
        |
        | Value:
        |
        | number of NEW range rows found for that date
        |
        */

        $detectedDates =
            [];


        // ======================================================
        // PROCESS WORKSHEETS
        // ======================================================

        foreach (
            $sheets as $sheetIndex => $rows
        ) {

            $sheetName =
                $sheetNames[
                    $sheetIndex
                ] ?? null;


            if (!$sheetName) {

                continue;

            }


            $normalizedSheetName =
                strtoupper(
                    trim(
                        $sheetName
                    )
                );


            // ==================================================
            // ONLY BRANCH SALES SHEETS
            // ==================================================
            //
            // Other summary sheets are ignored.
            //

            $branch =
                $branchesBySheet->get(
                    $normalizedSheetName
                );


            if (!$branch) {

                continue;

            }


            $summary[
                'branches_found'
            ]++;


            // ==================================================
            // BRANCH SUMMARY
            // ==================================================

            $branchSummary[
                $branch->name
            ] = [

                'branch_id' =>
                    $branch->id,

                'sheet_name' =>
                    $sheetName,

                'sales_rows' =>
                    0,

                'units_sold' =>
                    0,

                'sales_incl' =>
                    0,

                'sales_excl' =>
                    0,

                'total_cost_excl' =>
                    0,

                'gross_profit' =>
                    0,

                'gp_percentage' =>
                    0,

            ];


            // ==================================================
            // PROCESS ROWS
            // ==================================================

            foreach (
                $rows as $rowIndex => $row
            ) {

                $excelRow =
                    $rowIndex + 1;


                /*
                |--------------------------------------------------------------------------
                | ACTUAL MORNING WORKBOOK COLUMN MAPPING
                |--------------------------------------------------------------------------
                |
                | A = DATE
                | B = STOCK GROUP ID
                | C = STOCK GROUP DESCRIPTION
                | D = PLU
                | E = DESCRIPTION
                | F = QUANTITY
                | G = TOTAL AMOUNT EXCL
                | H = TOTAL AMOUNT
                | I = DAY_PERIOD
                |
                */

                $saleDateValue =
                    $row[0] ?? null;

                $plu =
                    $row[3] ?? null;

                $description =
                    $row[4] ?? null;

                $quantity =
                    $row[5] ?? null;

                $salesExcl =
                    $row[6] ?? null;

                $salesIncl =
                    $row[7] ?? null;

                $dayPeriod =
                    $row[8] ?? null;


                // ==================================================
                // NO PLU
                // ==================================================

                if (
                    $plu === null ||
                    trim(
                        (string)
                        $plu
                    ) === ''
                ) {

                    continue;

                }


                $rowsRead++;


                $originalPlu =
                    strtoupper(
                        trim(
                            (string)
                            $plu
                        )
                    );


                // ==================================================
                // ONLY -NEW PRODUCTS
                // ==================================================

                if (
                    !str_contains(
                        $originalPlu,
                        '-NEW'
                    )
                ) {

                    $summary[
                        'rows_skipped'
                    ]++;


                    continue;

                }


                // ==================================================
                // NORMALIZE PLU
                // ==================================================

                $normalizedModel =
                    $this
                        ->normalizeSalesPlu(
                            $originalPlu
                        );


                // ==================================================
                // PRODUCT MATCH
                // ==================================================

                $product =
                    $products->get(
                        $normalizedModel
                    );


                if (!$product) {

                    $unmatchedProducts[
                        $normalizedModel
                    ] = [

                        'model' =>
                            $normalizedModel,

                        'original_plu' =>
                            $originalPlu,

                        'branch' =>
                            $branch->name,

                        'sheet' =>
                            $sheetName,

                        'row' =>
                            $excelRow,

                    ];


                    continue;

                }


                // ==================================================
                // PARSE ROW SALES DATE
                // ==================================================

                $rowSaleDate =
                    $this->parseSaleDate(
                        $saleDateValue
                    );


                if (!$rowSaleDate) {

                    $warnings[] = [

                        'sheet' =>
                            $sheetName,

                        'row' =>
                            $excelRow,

                        'plu' =>
                            $originalPlu,

                        'message' =>
                            'Sale date could not be read.',

                    ];


                    continue;

                }


                // ==================================================
                // RECORD SALES DATE
                // ==================================================

                if (
                    !isset(
                        $detectedDates[
                            $rowSaleDate
                        ]
                    )
                ) {

                    $detectedDates[
                        $rowSaleDate
                    ] =
                        0;

                }


                $detectedDates[
                    $rowSaleDate
                ]++;


                // ==================================================
                // QUANTITY
                // ==================================================

                if (
                    !$this->isValidNumber(
                        $quantity
                    )
                ) {

                    $warnings[] = [

                        'sheet' =>
                            $sheetName,

                        'row' =>
                            $excelRow,

                        'plu' =>
                            $originalPlu,

                        'message' =>
                            'Quantity is not numeric.',

                    ];


                    continue;

                }


                $qty =
                    (float)
                    $quantity;


                if ($qty <= 0) {

                    $warnings[] = [

                        'sheet' =>
                            $sheetName,

                        'row' =>
                            $excelRow,

                        'plu' =>
                            $originalPlu,

                        'message' =>
                            'Quantity must be greater than zero.',

                    ];


                    continue;

                }


                // ==================================================
                // SALES EXCL VAT
                // ==================================================

                if (
                    !$this->isValidNumber(
                        $salesExcl
                    )
                ) {

                    $warnings[] = [

                        'sheet' =>
                            $sheetName,

                        'row' =>
                            $excelRow,

                        'plu' =>
                            $originalPlu,

                        'message' =>
                            'TOTAL AMOUNT EXCL is not numeric.',

                    ];


                    continue;

                }


                $totalSalesExcl =
                    round(
                        (float)
                        $salesExcl,
                        2
                    );


                // ==================================================
                // SALES INCL VAT
                // ==================================================

                if (
                    !$this->isValidNumber(
                        $salesIncl
                    )
                ) {

                    $warnings[] = [

                        'sheet' =>
                            $sheetName,

                        'row' =>
                            $excelRow,

                        'plu' =>
                            $originalPlu,

                        'message' =>
                            'TOTAL AMOUNT is not numeric.',

                    ];


                    continue;

                }


                $totalSalesIncl =
                    round(
                        (float)
                        $salesIncl,
                        2
                    );


                if (
                    $totalSalesIncl <= 0 ||
                    $totalSalesExcl <= 0
                ) {

                    $warnings[] = [

                        'sheet' =>
                            $sheetName,

                        'row' =>
                            $excelRow,

                        'plu' =>
                            $originalPlu,

                        'message' =>
                            'Sales amounts must be greater than zero.',

                    ];


                    continue;

                }


                // ==================================================
                // COST SNAPSHOT
                // ==================================================
                //
                // The current product cost is copied into the sale.
                //
                // Future cost changes will therefore NOT change
                // historical GP.
                //

                $unitCostExcl =
                    round(
                        (float)
                        $product
                            ->cost_excl,
                        2
                    );


                if ($unitCostExcl <= 0) {

                    $warnings[] = [

                        'sheet' =>
                            $sheetName,

                        'row' =>
                            $excelRow,

                        'plu' =>
                            $originalPlu,

                        'model' =>
                            $normalizedModel,

                        'message' =>
                            'Product does not have a usable cost excl. VAT.',

                    ];


                    continue;

                }


                $totalCostExcl =
                    round(
                        $unitCostExcl *
                        $qty,
                        2
                    );


                // ==================================================
                // GP
                // ==================================================

                $grossProfit =
                    round(
                        $totalSalesExcl -
                        $totalCostExcl,
                        2
                    );


                // ==================================================
                // GP %
                // ==================================================

                $gpPercentage =
                    $totalSalesExcl > 0
                        ? round(
                            (
                                $grossProfit /
                                $totalSalesExcl
                            ) * 100,
                            4
                        )
                        : 0;


                // ==================================================
                // BUILD SALE RECORD
                // ==================================================

                $sales[] = [

                    'product_id' =>
                        $product->id,

                    'branch_id' =>
                        $branch->id,

                    'sale_date' =>
                        $rowSaleDate,

                    'original_plu' =>
                        $originalPlu,

                    'description' =>
                        $description
                            ? trim(
                                (string)
                                $description
                            )
                            : $product
                                ->description,

                    'quantity' =>
                        $qty,

                    'sales_incl' =>
                        $totalSalesIncl,

                    'sales_excl' =>
                        $totalSalesExcl,

                    'unit_cost_excl' =>
                        $unitCostExcl,

                    'total_cost_excl' =>
                        $totalCostExcl,

                    'gross_profit' =>
                        $grossProfit,

                    'gp_percentage' =>
                        $gpPercentage,

                    'day_period' =>
                        $dayPeriod !== null
                            ? trim(
                                (string)
                                $dayPeriod
                            )
                            : null,

                    'source_sheet' =>
                        $sheetName,

                    'source_row' =>
                        $excelRow,

                ];


                // ==================================================
                // OVERALL SUMMARY
                // ==================================================

                $summary[
                    'sales_rows'
                ]++;


                $summary[
                    'units_sold'
                ] +=
                    $qty;


                $summary[
                    'sales_incl'
                ] +=
                    $totalSalesIncl;


                $summary[
                    'sales_excl'
                ] +=
                    $totalSalesExcl;


                $summary[
                    'total_cost_excl'
                ] +=
                    $totalCostExcl;


                $summary[
                    'gross_profit'
                ] +=
                    $grossProfit;


                // ==================================================
                // BRANCH SUMMARY
                // ==================================================

                $branchSummary[
                    $branch->name
                ][
                    'sales_rows'
                ]++;


                $branchSummary[
                    $branch->name
                ][
                    'units_sold'
                ] +=
                    $qty;


                $branchSummary[
                    $branch->name
                ][
                    'sales_incl'
                ] +=
                    $totalSalesIncl;


                $branchSummary[
                    $branch->name
                ][
                    'sales_excl'
                ] +=
                    $totalSalesExcl;


                $branchSummary[
                    $branch->name
                ][
                    'total_cost_excl'
                ] +=
                    $totalCostExcl;


                $branchSummary[
                    $branch->name
                ][
                    'gross_profit'
                ] +=
                    $grossProfit;

            }

        }


        // ======================================================
        // SORT DETECTED DATES
        // ======================================================

        ksort(
            $detectedDates
        );


        $detectedSalesDates =
            array_keys(
                $detectedDates
            );


        // ======================================================
        // DETECT SINGLE SALES DATE
        // ======================================================

        $detectedSalesDate =
            count(
                $detectedSalesDates
            ) === 1
                ? $detectedSalesDates[0]
                : null;


        // ======================================================
        // MULTIPLE DATES FOUND
        // ======================================================

        if (
            count(
                $detectedSalesDates
            ) > 1
        ) {

            $warnings[] = [

                'message' =>
                    'More than one NEW range sales date was detected in the workbook.',

                'dates' =>
                    $detectedDates,

            ];

        }


        // ======================================================
        // OVERALL GP %
        // ======================================================

        if (
            $summary[
                'sales_excl'
            ] > 0
        ) {

            $summary[
                'gp_percentage'
            ] =
                round(
                    (
                        $summary[
                            'gross_profit'
                        ] /
                        $summary[
                            'sales_excl'
                        ]
                    ) * 100,
                    2
                );

        }


        // ======================================================
        // ROUND SUMMARY
        // ======================================================

        foreach (
            [
                'sales_incl',
                'sales_excl',
                'total_cost_excl',
                'gross_profit',
            ]
            as $field
        ) {

            $summary[
                $field
            ] =
                round(
                    $summary[
                        $field
                    ],
                    2
                );

        }


        // ======================================================
        // BRANCH TOTALS
        // ======================================================

        foreach (
            $branchSummary
            as $branchName => $values
        ) {

            foreach (
                [
                    'sales_incl',
                    'sales_excl',
                    'total_cost_excl',
                    'gross_profit',
                ]
                as $field
            ) {

                $branchSummary[
                    $branchName
                ][
                    $field
                ] =
                    round(
                        $branchSummary[
                            $branchName
                        ][
                            $field
                        ],
                        2
                    );

            }


            $branchSalesExcl =
                $branchSummary[
                    $branchName
                ][
                    'sales_excl'
                ];


            $branchGrossProfit =
                $branchSummary[
                    $branchName
                ][
                    'gross_profit'
                ];


            $branchSummary[
                $branchName
            ][
                'gp_percentage'
            ] =
                $branchSalesExcl > 0
                    ? round(
                        (
                            $branchGrossProfit /
                            $branchSalesExcl
                        ) * 100,
                        2
                    )
                    : 0;

        }


        // ======================================================
        // RELEASE WORKBOOK
        // ======================================================

        $spreadsheet
            ->disconnectWorksheets();


        unset(
            $spreadsheet
        );


        // ======================================================
        // RETURN ANALYSIS
        // ======================================================

        return [

            'detected_sales_date' =>
                $detectedSalesDate,

            'detected_sales_dates' =>
                $detectedDates,

            'summary' =>
                $summary,

            'branches' =>
                $branchSummary,

            'unmatched_products' =>
                array_values(
                    $unmatchedProducts
                ),

            'warnings' =>
                $warnings,

            'sales' =>
                $sales,

            'rows_read' =>
                $rowsRead,

        ];

    }


    /**
     * =========================================================
     * NORMALIZE SALES PLU
     * =========================================================
     *
     * Examples:
     *
     * DAD239-NEW
     *     -> DAD239-NEW
     *
     * DAD239-NEW-01
     *     -> DAD239-NEW
     *
     * DAC363-NEW-02
     *     -> DAC363-NEW
     */
    private function normalizeSalesPlu(
        string $value
    ): string {

        $value =
            strtoupper(
                trim(
                    $value
                )
            );


        // ======================================================
        // REMOVE WHITESPACE
        // ======================================================

        $value =
            preg_replace(
                '/\s+/',
                '',
                $value
            );


        // ======================================================
        // REMOVE GRADE SUFFIX
        // ======================================================

        $value =
            preg_replace(
                '/-NEW-\d+$/',
                '-NEW',
                $value
            );


        return
            $value;

    }


    /**
     * =========================================================
     * PARSE SALES DATE
     * =========================================================
     */
    private function parseSaleDate(
        mixed $value
    ): ?string {

        // ======================================================
        // EMPTY
        // ======================================================

        if (
            $value === null ||
            $value === ''
        ) {

            return null;

        }


        // ======================================================
        // PHP DATE OBJECT
        // ======================================================

        if (
            $value instanceof
            \DateTimeInterface
        ) {

            return
                Carbon::instance(
                    \DateTime::createFromInterface(
                        $value
                    )
                )
                    ->format(
                        'Y-m-d'
                    );

        }


        // ======================================================
        // EXCEL SERIAL DATE
        // ======================================================

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

                return
                    Carbon::instance(
                        ExcelDate::excelToDateTimeObject(
                            (float)
                            $value
                        )
                    )
                        ->format(
                            'Y-m-d'
                        );

            } catch (Throwable $exception) {

                return null;

            }

        }


        // ======================================================
        // STRING DATE
        // ======================================================

        $text =
            trim(
                (string)
                $value
            );


        if ($text === '') {

            return null;

        }


        $formats = [

            'Y-m-d',
            'Y/m/d',
            'd/m/Y',
            'd-m-Y',
            'd.m.Y',

        ];


        foreach (
            $formats as $format
        ) {

            try {

                $date =
                    Carbon::createFromFormat(
                        $format,
                        $text
                    );


                if (
                    $date->format(
                        $format
                    ) === $text
                ) {

                    return
                        $date->format(
                            'Y-m-d'
                        );

                }

            } catch (Throwable $exception) {

                // Try next format.

            }

        }


        return null;

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
}
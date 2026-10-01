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
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class NewRangeHistoricalImportController extends Controller
{
    /**
     * =========================================================
     * PREVIEW HISTORICAL WORKBOOK
     * =========================================================
     *
     * Does NOT write anything to the database.
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
                    'Historical NEW range workbook preview completed.',

                'summary' =>
                    $analysis['summary'],

                'branches' =>
                    $analysis['branches'],

                'date_corrections_count' =>
                    count(
                        $analysis[
                            'date_corrections'
                        ]
                    ),

                'date_corrections' =>
                    $analysis[
                        'date_corrections'
                    ],

                'warnings_count' =>
                    count(
                        $analysis[
                            'warnings'
                        ]
                    ),

                'warnings' =>
                    $analysis[
                        'warnings'
                    ],

            ]);

        } catch (Throwable $exception) {

            return response()->json([

                'message' =>
                    'Unable to preview historical NEW range workbook.',

                'error' =>
                    $exception->getMessage(),

            ], 500);

        }

    }


    /**
     * =========================================================
     * IMPORT HISTORICAL SALES
     * =========================================================
     *
     * Writes historical SALES only.
     *
     * It does NOT import:
     *
     * - Opening Stock
     * - GRVs
     * - IBTs
     *
     * Those will be handled separately.
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
            // FILE HASH
            // ==================================================

            $realPath =
                $request
                    ->file('file')
                    ->getRealPath();


            if (!$realPath) {

                return response()->json([
                    'message' =>
                        'Unable to access the uploaded workbook.',
                ], 422);

            }


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
            // DUPLICATE FILE PROTECTION
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
                        'This workbook has already been imported.',

                    'existing_import' => [

                        'id' =>
                            $existingImport->id,

                        'source_filename' =>
                            $existingImport
                                ->source_filename,

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
            // ANALYSE WORKBOOK FIRST
            // ==================================================

            $analysis =
                $this->analyseWorkbook(
                    $request
                );


            // ==================================================
            // BLOCK IMPORT IF PREVIEW IS NOT CLEAN
            // ==================================================

            if (
                count(
                    $analysis[
                        'summary'
                    ][
                        'products_unmatched'
                    ]
                ) > 0
            ) {

                return response()->json([

                    'message' =>
                        'Historical import stopped because some products could not be matched.',

                    'products_unmatched' =>
                        $analysis[
                            'summary'
                        ][
                            'products_unmatched'
                        ],

                ], 422);

            }


            if (
                $analysis[
                    'summary'
                ][
                    'invalid_rows'
                ] > 0
            ) {

                return response()->json([

                    'message' =>
                        'Historical import stopped because invalid rows remain.',

                    'summary' =>
                        $analysis[
                            'summary'
                        ],

                    'warnings' =>
                        $analysis[
                            'warnings'
                        ],

                ], 422);

            }


            if (
                $analysis[
                    'summary'
                ][
                    'unresolved_dates'
                ] > 0
            ) {

                return response()->json([

                    'message' =>
                        'Historical import stopped because unresolved sale dates remain.',

                    'warnings' =>
                        $analysis[
                            'warnings'
                        ],

                ], 422);

            }


            if (
                $analysis[
                    'summary'
                ][
                    'future_dates'
                ] > 0
            ) {

                return response()->json([

                    'message' =>
                        'Historical import stopped because unexpected future dates remain.',

                    'warnings' =>
                        $analysis[
                            'warnings'
                        ],

                ], 422);

            }


            if (
                count(
                    $analysis[
                        'warnings'
                    ]
                ) > 0
            ) {

                return response()->json([

                    'message' =>
                        'Historical import stopped because preview warnings remain.',

                    'warnings' =>
                        $analysis[
                            'warnings'
                        ],

                ], 422);

            }


            // ==================================================
            // EXPECTED CONTROL TOTAL
            // ==================================================
            //
            // Based on our verified workbook preview.
            //
            // This is an additional safety check.
            //

            if (
                $analysis[
                    'summary'
                ][
                    'sales_rows'
                ] !== 575
            ) {

                return response()->json([

                    'message' =>
                        'Historical import stopped because the sales row count does not match the verified workbook.',

                    'expected_sales_rows' =>
                        575,

                    'actual_sales_rows' =>
                        $analysis[
                            'summary'
                        ][
                            'sales_rows'
                        ],

                ], 422);

            }


            // ==================================================
            // DATABASE TRANSACTION
            // ==================================================

            $result =
                DB::transaction(
                    function () use (
                        $request,
                        $fileHash,
                        $analysis
                    ) {

                        // ==========================================
                        // CREATE IMPORT HEADER
                        // ==========================================

                        $salesImport =
                            NewRangeSalesImport::create([

                                'source_filename' =>
                                    $request
                                        ->file('file')
                                        ->getClientOriginalName(),

                                'file_hash' =>
                                    $fileHash,

                                'sales_date' =>
                                    null,

                                'status' =>
                                    'processing',

                                'rows_read' =>
                                    $analysis[
                                        'rows_read'
                                    ],

                                'rows_imported' =>
                                    0,

                                'rows_skipped' =>
                                    0,

                                'rows_failed' =>
                                    0,

                                'imported_by' =>
                                    auth()->id(),

                                'imported_at' =>
                                    null,

                                'notes' =>
                                    'Historical NEW range sales migration.',

                            ]);


                        // ==========================================
                        // INSERT SALES
                        // ==========================================

                        $importedCount =
                            0;


                        foreach (
                            $analysis[
                                'sales'
                            ]
                            as $sale
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
                                    null,

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
                        // VERIFY INSERT COUNT
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
                                'Historical import count mismatch. Transaction has been cancelled.'
                            );

                        }


                        // ==========================================
                        // COMPLETE IMPORT HEADER
                        // ==========================================

                        $salesImport->update([

                            'status' =>
                                'completed',

                            'rows_imported' =>
                                $importedCount,

                            'rows_skipped' =>
                                0,

                            'rows_failed' =>
                                0,

                            'imported_at' =>
                                now(),

                            'notes' =>
                                'Historical NEW range sales successfully migrated. '
                                . $importedCount
                                . ' sales imported.',

                        ]);


                        return [

                            'sales_import_id' =>
                                $salesImport->id,

                            'rows_imported' =>
                                $importedCount,

                        ];

                    }
                );


            // ==================================================
            // SUCCESS RESPONSE
            // ==================================================

            return response()->json([

                'message' =>
                    'Historical NEW range sales imported successfully.',

                'sales_import_id' =>
                    $result[
                        'sales_import_id'
                    ],

                'summary' => [

                    'sales_rows_imported' =>
                        $result[
                            'rows_imported'
                        ],

                    'sales_units' =>
                        $analysis[
                            'summary'
                        ][
                            'sales_units'
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

                    'historical_cost_excl' =>
                        $analysis[
                            'summary'
                        ][
                            'historical_cost_excl'
                        ],

                    'gross_profit' =>
                        $analysis[
                            'summary'
                        ][
                            'gross_profit'
                        ],

                    'corrected_dates' =>
                        $analysis[
                            'summary'
                        ][
                            'corrected_dates'
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
                    'Unable to import historical NEW range sales.',

                'error' =>
                    $exception->getMessage(),

            ], 500);

        }

    }


    /**
     * =========================================================
     * ANALYSE WORKBOOK
     * =========================================================
     *
     * Shared by:
     *
     * preview()
     * import()
     */
    private function analyseWorkbook(
        Request $request
    ): array {

        $sheets =
            Excel::toArray(
                [],
                $request
                    ->file('file')
            );


        if (empty($sheets)) {

            throw new \RuntimeException(
                'The uploaded workbook contains no worksheets.'
            );

        }


        // ======================================================
        // BRANCH MAPPING
        // ======================================================

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


        // ======================================================
        // WORKBOOK SHEET ORDER
        // ======================================================

        $expectedSheets = [

            'CHINA',
            'BOSWORTH',
            'WEST',
            'SOWETO',

        ];


        // ======================================================
        // PRODUCTS
        // ======================================================

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


        // ======================================================
        // SUMMARY
        // ======================================================

        $summary = [

            'branches_found' =>
                0,

            'products_matched' =>
                [],

            'products_unmatched' =>
                [],

            'opening_rows' =>
                0,

            'grv_rows' =>
                0,

            'ibt_rows' =>
                0,

            'sales_rows' =>
                0,

            'sales_units' =>
                0,

            'sales_incl' =>
                0,

            'sales_excl' =>
                0,

            'historical_cost_incl' =>
                0,

            'historical_cost_excl' =>
                0,

            'gross_profit' =>
                0,

            'invalid_rows' =>
                0,

            'corrected_dates' =>
                0,

            'unresolved_dates' =>
                0,

            'future_dates' =>
                0,

        ];


        $branchSummary =
            [];


        $warnings =
            [];


        $dateCorrections =
            [];


        $sales =
            [];


        $rowsRead =
            0;


        // ======================================================
        // PROCESS SHEETS
        // ======================================================

        foreach (
            $sheets as $sheetIndex => $rows
        ) {

            $sheetName =
                $expectedSheets[
                    $sheetIndex
                ] ?? null;


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
                NewRangeBranch::query()
                    ->where(
                        'name',
                        $branchName
                    )
                    ->first();


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

                'opening_rows' =>
                    0,

                'grv_rows' =>
                    0,

                'ibt_rows' =>
                    0,

                'sales_rows' =>
                    0,

                'sales_units' =>
                    0,

                'sales_incl' =>
                    0,

                'sales_excl' =>
                    0,

                'gross_profit' =>
                    0,

                'invalid_rows' =>
                    0,

                'corrected_dates' =>
                    0,

                'unresolved_dates' =>
                    0,

                'future_dates' =>
                    0,

            ];


            $currentModel =
                null;


            $currentProduct =
                null;


            // ==================================================
            // PROCESS ROWS
            // ==================================================

            foreach (
                $rows as $rowIndex => $row
            ) {

                $rowsRead++;


                $excelRow =
                    $rowIndex + 1;


                // ==============================================
                // COLUMN MAPPING
                // ==============================================
                //
                // A Model
                // B Invoice
                // C GRV / IBT
                // D Received Date
                // E Qty Received
                // F Cost Incl
                // G Sale Date
                // H Sale Qty
                // I Selling Price Incl
                //

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

                $salePriceIncl =
                    $row[8] ?? null;


                // ==============================================
                // MODEL BLOCK
                // ==============================================

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
                        $possibleModel !== null
                    ) {

                        $currentModel =
                            $possibleModel;


                        $currentProduct =
                            $products->get(
                                $currentModel
                            );


                        if ($currentProduct) {

                            $summary[
                                'products_matched'
                            ][
                                $currentModel
                            ] =
                                true;

                        } else {

                            $summary[
                                'products_unmatched'
                            ][
                                $currentModel
                            ] =
                                true;

                        }

                    }

                }


                if (!$currentModel) {

                    continue;

                }


                // ==============================================
                // RECEIVING PREVIEW
                // ==============================================

                if (
                    $this->isValidNumber(
                        $receivedQty
                    ) &&
                    (float) $receivedQty > 0 &&
                    $this->isValidNumber(
                        $costIncl
                    ) &&
                    (float) $costIncl > 0 &&
                    $this->isUsableReceivedDate(
                        $receivedDate
                    )
                ) {

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
                        str_contains(
                            $invoiceText,
                            'OPEN STOCK'
                        )
                    ) {

                        $summary[
                            'opening_rows'
                        ]++;


                        $branchSummary[
                            $branchName
                        ][
                            'opening_rows'
                        ]++;

                    } elseif (
                        str_starts_with(
                            $referenceText,
                            'IBT'
                        )
                    ) {

                        $summary[
                            'ibt_rows'
                        ]++;


                        $branchSummary[
                            $branchName
                        ][
                            'ibt_rows'
                        ]++;

                    } else {

                        $summary[
                            'grv_rows'
                        ]++;


                        $branchSummary[
                            $branchName
                        ][
                            'grv_rows'
                        ]++;

                    }

                }


                // ==============================================
                // SALE DETECTION
                // ==============================================

                $looksLikeSale =
                    $this->isValidNumber(
                        $saleQty
                    ) &&
                    (float) $saleQty > 0 &&
                    $this->isValidNumber(
                        $salePriceIncl
                    ) &&
                    (float) $salePriceIncl > 0;


                if (!$looksLikeSale) {

                    continue;

                }


                // ==============================================
                // DATE
                // ==============================================

                $saleDateResult =
                    $this
                        ->parseHistoricalDate(
                            $saleDate,
                            $sheetName,
                            $excelRow
                        );


                if (
                    !$saleDateResult[
                        'date'
                    ]
                ) {

                    $summary[
                        'unresolved_dates'
                    ]++;


                    $summary[
                        'invalid_rows'
                    ]++;


                    $branchSummary[
                        $branchName
                    ][
                        'unresolved_dates'
                    ]++;


                    $branchSummary[
                        $branchName
                    ][
                        'invalid_rows'
                    ]++;


                    $warnings[] = [

                        'sheet' =>
                            $sheetName,

                        'row' =>
                            $excelRow,

                        'model' =>
                            $currentModel,

                        'original_date' =>
                            $saleDate,

                        'message' =>
                            $saleDateResult[
                                'message'
                            ],

                    ];


                    continue;

                }


                if (
                    $saleDateResult[
                        'corrected'
                    ]
                ) {

                    $summary[
                        'corrected_dates'
                    ]++;


                    $branchSummary[
                        $branchName
                    ][
                        'corrected_dates'
                    ]++;


                    $dateCorrections[] = [

                        'sheet' =>
                            $sheetName,

                        'row' =>
                            $excelRow,

                        'model' =>
                            $currentModel,

                        'original_date' =>
                            $saleDateResult[
                                'original'
                            ],

                        'corrected_date' =>
                            $saleDateResult[
                                'date'
                            ]->format(
                                'Y-m-d'
                            ),

                        'reason' =>
                            $saleDateResult[
                                'correction_reason'
                            ],

                    ];

                }


                if (
                    $saleDateResult[
                        'future'
                    ]
                ) {

                    $summary[
                        'future_dates'
                    ]++;


                    $branchSummary[
                        $branchName
                    ][
                        'future_dates'
                    ]++;


                    $warnings[] = [

                        'sheet' =>
                            $sheetName,

                        'row' =>
                            $excelRow,

                        'model' =>
                            $currentModel,

                        'parsed_date' =>
                            $saleDateResult[
                                'date'
                            ]->format(
                                'Y-m-d'
                            ),

                        'message' =>
                            'Sale date is later than the historical migration cut-off.',

                    ];

                }


                // ==============================================
                // PRODUCT VALIDATION
                // ==============================================

                if (!$currentProduct) {

                    $summary[
                        'invalid_rows'
                    ]++;


                    $branchSummary[
                        $branchName
                    ][
                        'invalid_rows'
                    ]++;


                    $warnings[] = [

                        'sheet' =>
                            $sheetName,

                        'row' =>
                            $excelRow,

                        'model' =>
                            $currentModel,

                        'message' =>
                            'Historical sale cannot be matched to the NEW product master.',

                    ];


                    continue;

                }


                // ==============================================
                // HISTORICAL COST REQUIRED
                // ==============================================

                if (
                    !$this->isValidNumber(
                        $costIncl
                    ) ||
                    (float) $costIncl <= 0
                ) {

                    $summary[
                        'invalid_rows'
                    ]++;


                    $branchSummary[
                        $branchName
                    ][
                        'invalid_rows'
                    ]++;


                    $warnings[] = [

                        'sheet' =>
                            $sheetName,

                        'row' =>
                            $excelRow,

                        'model' =>
                            $currentModel,

                        'message' =>
                            'Historical sale has no usable historical cost.',

                    ];


                    continue;

                }


                // ==============================================
                // VALUES
                // ==============================================

                $qty =
                    (float)
                    $saleQty;


                $unitSaleIncl =
                    (float)
                    $salePriceIncl;


                $unitCostIncl =
                    (float)
                    $costIncl;


                $totalSalesIncl =
                    round(
                        $unitSaleIncl *
                        $qty,
                        2
                    );


                $totalSalesExcl =
                    round(
                        $totalSalesIncl /
                        1.15,
                        2
                    );


                $unitCostExcl =
                    round(
                        $unitCostIncl /
                        1.15,
                        2
                    );


                $totalCostIncl =
                    round(
                        $unitCostIncl *
                        $qty,
                        2
                    );


                $totalCostExcl =
                    round(
                        $unitCostExcl *
                        $qty,
                        2
                    );


                $grossProfit =
                    round(
                        $totalSalesExcl -
                        $totalCostExcl,
                        2
                    );


                $gpPercentage =
                    $totalSalesExcl > 0
                        ? round(
                            (
                                $grossProfit /
                                $totalSalesExcl
                            ) * 100,
                            4
                        )
                        : null;


                // ==============================================
                // BUILD DATABASE SALE RECORD
                // ==============================================

                $sales[] = [

                    'product_id' =>
                        $currentProduct->id,

                    'branch_id' =>
                        $branch->id,

                    'sale_date' =>
                        $saleDateResult[
                            'date'
                        ]->format(
                            'Y-m-d'
                        ),

                    /*
                     * Historical workbook does not contain the
                     * morning sales PLU.
                     *
                     * Preserve the historical normalized model
                     * as our source identifier.
                     */
                    'original_plu' =>
                        $currentModel,

                    'description' =>
                        $currentProduct
                            ->description,

                    'quantity' =>
                        $qty,

                    'sales_excl' =>
                        $totalSalesExcl,

                    'sales_incl' =>
                        $totalSalesIncl,

                    'unit_cost_excl' =>
                        $unitCostExcl,

                    'total_cost_excl' =>
                        $totalCostExcl,

                    'gross_profit' =>
                        $grossProfit,

                    'gp_percentage' =>
                        $gpPercentage,

                    'source_sheet' =>
                        $sheetName,

                    'source_row' =>
                        $excelRow,

                ];


                // ==============================================
                // OVERALL TOTALS
                // ==============================================

                $summary[
                    'sales_rows'
                ]++;


                $summary[
                    'sales_units'
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
                    'historical_cost_incl'
                ] +=
                    $totalCostIncl;


                $summary[
                    'historical_cost_excl'
                ] +=
                    $totalCostExcl;


                $summary[
                    'gross_profit'
                ] +=
                    $grossProfit;


                // ==============================================
                // BRANCH TOTALS
                // ==============================================

                $branchSummary[
                    $branchName
                ][
                    'sales_rows'
                ]++;


                $branchSummary[
                    $branchName
                ][
                    'sales_units'
                ] +=
                    $qty;


                $branchSummary[
                    $branchName
                ][
                    'sales_incl'
                ] +=
                    $totalSalesIncl;


                $branchSummary[
                    $branchName
                ][
                    'sales_excl'
                ] +=
                    $totalSalesExcl;


                $branchSummary[
                    $branchName
                ][
                    'gross_profit'
                ] +=
                    $grossProfit;

            }

        }


        // ======================================================
        // MATCHED PRODUCT LIST
        // ======================================================

        $summary[
            'products_matched'
        ] =
            array_values(
                array_keys(
                    $summary[
                        'products_matched'
                    ]
                )
            );


        sort(
            $summary[
                'products_matched'
            ]
        );


        // ======================================================
        // UNMATCHED PRODUCT LIST
        // ======================================================

        $summary[
            'products_unmatched'
        ] =
            array_values(
                array_keys(
                    $summary[
                        'products_unmatched'
                    ]
                )
            );


        sort(
            $summary[
                'products_unmatched'
            ]
        );


        // ======================================================
        // ROUND TOTALS
        // ======================================================

        foreach (
            [
                'sales_incl',
                'sales_excl',
                'historical_cost_incl',
                'historical_cost_excl',
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


        foreach (
            $branchSummary
            as $branchName => $values
        ) {

            foreach (
                [
                    'sales_incl',
                    'sales_excl',
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

        }


        return [

            'summary' =>
                $summary,

            'branches' =>
                $branchSummary,

            'warnings' =>
                $warnings,

            'date_corrections' =>
                $dateCorrections,

            'sales' =>
                $sales,

            'rows_read' =>
                $rowsRead,

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


        if (
            !preg_match(
                '/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/',
                $value
            )
        ) {

            return null;

        }


        // ======================================================
        // HISTORICAL MODEL ALIASES
        // ======================================================

        $aliases = [

            'U246B-1' =>
                'U246B',

        ];


        if (
            isset(
                $aliases[
                    $value
                ]
            )
        ) {

            $value =
                $aliases[
                    $value
                ];

        }


        if (
            str_contains(
                $value,
                '-NEW'
            )
        ) {

            return preg_replace(
                '/-NEW-01$/',
                '-NEW',
                $value
            );

        }


        return
            $value . '-NEW';

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
     * RECEIVING DATE CHECK
     * =========================================================
     */
    private function isUsableReceivedDate(
        mixed $value
    ): bool {

        if (
            $value === null ||
            $value === ''
        ) {

            return false;

        }


        if (
            $value instanceof
            \DateTimeInterface
        ) {

            return true;

        }


        if (
            is_numeric(
                $value
            )
        ) {

            return
                (float)
                $value > 1 &&
                (float)
                $value < 100000;

        }


        $text =
            trim(
                (string)
                $value
            );


        if ($text === '') {

            return false;

        }


        $formats = [

            'Y-m-d',
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
                    $date &&
                    $date->format(
                        $format
                    ) === $text
                ) {

                    return true;

                }

            } catch (Throwable $exception) {

                // Try next format.

            }

        }


        return false;

    }


    /**
     * =========================================================
     * PARSE HISTORICAL SALE DATE
     * =========================================================
     */
    private function parseHistoricalDate(
        mixed $value,
        string $sheetName,
        int $excelRow
    ): array {

        $result = [

            'date' =>
                null,

            'original' =>
                $value,

            'corrected' =>
                false,

            'correction_reason' =>
                null,

            'future' =>
                false,

            'message' =>
                null,

        ];


        // ======================================================
        // KNOWN HISTORICAL CORRECTIONS
        // ======================================================

        $knownCorrection =
            $this
                ->getKnownHistoricalDateCorrection(
                    $sheetName,
                    $excelRow
                );


        if ($knownCorrection) {

            $date =
                Carbon::createFromFormat(
                    'Y-m-d',
                    $knownCorrection
                )
                    ->startOfDay();


            $result[
                'date'
            ] =
                $date;


            $result[
                'corrected'
            ] =
                true;


            $result[
                'correction_reason'
            ] =
                'Known historical workbook date correction.';


            return $result;

        }


        // ======================================================
        // EMPTY
        // ======================================================

        if (
            $value === null ||
            $value === ''
        ) {

            $result[
                'message'
            ] =
                'Historical sale date is blank.';


            return $result;

        }


        // ======================================================
        // DATE OBJECT
        // ======================================================

        if (
            $value instanceof
            \DateTimeInterface
        ) {

            $date =
                Carbon::instance(
                    \DateTime::createFromInterface(
                        $value
                    )
                )
                    ->startOfDay();


            $result[
                'date'
            ] =
                $date;


            $result[
                'future'
            ] =
                $this
                    ->isUnexpectedHistoricalFutureDate(
                        $date
                    );


            return $result;

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

                $excelDate =
                    ExcelDate::excelToDateTimeObject(
                        (float)
                        $value
                    );


                $date =
                    Carbon::instance(
                        $excelDate
                    )
                        ->startOfDay();


                $result[
                    'date'
                ] =
                    $date;


                $result[
                    'future'
                ] =
                    $this
                        ->isUnexpectedHistoricalFutureDate(
                            $date
                        );


                return $result;

            } catch (Throwable $exception) {

                $result[
                    'message'
                ] =
                    'Invalid Excel date serial.';


                return $result;

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


        if (
            $text === '' ||
            strtoupper(
                $text
            ) === '#VALUE!'
        ) {

            $result[
                'message'
            ] =
                'Historical sale date contains an Excel error.';


            return $result;

        }


        $originalText =
            $text;


        // ======================================================
        // REMOVE EXTRA SLASHES
        // ======================================================

        $text =
            trim(
                $text,
                " \t\n\r\0\x0B/"
            );


        // ======================================================
        // FIX 29/052026
        // ======================================================

        if (
            preg_match(
                '/^(\d{1,2})\/(\d{2})(\d{4})$/',
                $text,
                $matches
            )
        ) {

            $text =
                $matches[1]
                . '/'
                . $matches[2]
                . '/'
                . $matches[3];

        }


        // ======================================================
        // FIX 230/08/2026
        // ======================================================

        if (
            preg_match(
                '/^2(\d{2})\/(\d{2})\/(\d{4})$/',
                $text,
                $matches
            )
        ) {

            $possibleDay =
                (int)
                $matches[1];


            if (
                $possibleDay >= 1 &&
                $possibleDay <= 31
            ) {

                $text =
                    str_pad(
                        (string)
                        $possibleDay,
                        2,
                        '0',
                        STR_PAD_LEFT
                    )
                    . '/'
                    . $matches[2]
                    . '/'
                    . $matches[3];

            }

        }


        if (
            $text !==
            $originalText
        ) {

            $result[
                'corrected'
            ] =
                true;


            $result[
                'correction_reason'
            ] =
                'Automatic historical date formatting correction.';

        }


        // ======================================================
        // SUPPORTED FORMATS
        // ======================================================

        $formats = [

            'd/m/Y',
            'd-m-Y',
            'd.m.Y',
            'Y-m-d',
            'Y/m/d',

        ];


        foreach (
            $formats as $format
        ) {

            try {

                $date =
                    Carbon::createFromFormat(
                        $format,
                        $text
                    )
                        ->startOfDay();


                if (
                    $date->format(
                        $format
                    ) !== $text
                ) {

                    continue;

                }


                $result[
                    'date'
                ] =
                    $date;


                $result[
                    'future'
                ] =
                    $this
                        ->isUnexpectedHistoricalFutureDate(
                            $date
                        );


                return $result;

            } catch (Throwable $exception) {

                // Try next format.

            }

        }


        $result[
            'message'
        ] =
            "Unable to interpret historical date '{$originalText}'.";


        return $result;

    }


    /**
     * =========================================================
     * KNOWN HISTORICAL DATE CORRECTIONS
     * =========================================================
     */
    private function getKnownHistoricalDateCorrection(
        string $sheetName,
        int $excelRow
    ): ?string {

        $knownDateCorrections = [

            'CHINA' => [

                13 =>
                    '2026-06-26',

                133 =>
                    '2026-08-30',

                176 =>
                    '2026-07-29',

                450 =>
                    '2026-06-25',

            ],


            'WEST' => [

                32 =>
                    '2026-09-12',

                161 =>
                    '2026-09-15',

                303 =>
                    '2026-09-11',

                424 =>
                    '2026-09-14',

            ],

        ];


        return
            $knownDateCorrections[
                strtoupper(
                    $sheetName
                )
            ][
                $excelRow
            ] ?? null;

    }


    /**
     * =========================================================
     * FUTURE DATE CHECK
     * =========================================================
     */
    private function isUnexpectedHistoricalFutureDate(
        Carbon $date
    ): bool {

        $historicalCutoff =
            Carbon::create(
                2026,
                9,
                30,
                23,
                59,
                59
            );


        return
            $date->greaterThan(
                $historicalCutoff
            );

    }
}
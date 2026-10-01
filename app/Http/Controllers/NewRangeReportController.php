<?php

namespace App\Http\Controllers;

use App\Models\NewRangeBranch;
use App\Models\NewRangeProduct;
use App\Models\NewRangeSale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NewRangeReportController extends Controller
{
    /**
     * =========================================================
     * SALES & GP REPORT
     * =========================================================
     *
     * Optional filters:
     *
     * branch_id
     * product_id
     * year
     * month
     * date_from
     * date_to
     */
    public function salesGpReport(
        Request $request
    ): JsonResponse {

        // ======================================================
        // VALIDATE FILTERS
        // ======================================================

        $validated = $request->validate([

            'branch_id' => [
                'nullable',
                'integer',
                'exists:new_range_branches,id',
            ],

            'product_id' => [
                'nullable',
                'integer',
                'exists:new_range_products,id',
            ],

            'year' => [
                'nullable',
                'integer',
                'min:2000',
                'max:2100',
            ],

            'month' => [
                'nullable',
                'integer',
                'min:1',
                'max:12',
            ],

            'date_from' => [
                'nullable',
                'date',
            ],

            'date_to' => [
                'nullable',
                'date',
                'after_or_equal:date_from',
            ],

        ]);


        // ======================================================
        // BASE QUERY
        // ======================================================

        $query =
            NewRangeSale::query();


        // ======================================================
        // APPLY FILTERS
        // ======================================================

        $this->applyFilters(
            $query,
            $validated
        );


        // ======================================================
        // OVERALL SUMMARY
        // ======================================================

        $summaryQuery =
            clone $query;


        $summary =
            $summaryQuery
                ->selectRaw(
                    '
                    COUNT(*) as sales_rows,
                    COALESCE(SUM(quantity), 0) as units_sold,
                    COALESCE(SUM(sales_incl), 0) as sales_incl,
                    COALESCE(SUM(sales_excl), 0) as sales_excl,
                    COALESCE(SUM(total_cost_excl), 0) as total_cost_excl,
                    COALESCE(SUM(gross_profit), 0) as gross_profit
                    '
                )
                ->first();


        $salesExcl =
            (float)
            $summary->sales_excl;


        $grossProfit =
            (float)
            $summary->gross_profit;


        $gpPercentage =
            $salesExcl > 0
                ? round(
                    (
                        $grossProfit /
                        $salesExcl
                    ) * 100,
                    2
                )
                : 0;


        // ======================================================
        // BRANCH SUMMARY
        // ======================================================

        $branchQuery =
            NewRangeSale::query()
                ->join(
                    'new_range_branches',
                    'new_range_sales.branch_id',
                    '=',
                    'new_range_branches.id'
                );


        $this->applyFilters(
            $branchQuery,
            $validated,
            'new_range_sales'
        );


        $branches =
            $branchQuery
                ->selectRaw(
                    '
                    new_range_branches.id as branch_id,
                    new_range_branches.name as branch_name,

                    COUNT(new_range_sales.id) as sales_rows,

                    COALESCE(
                        SUM(new_range_sales.quantity),
                        0
                    ) as units_sold,

                    COALESCE(
                        SUM(new_range_sales.sales_incl),
                        0
                    ) as sales_incl,

                    COALESCE(
                        SUM(new_range_sales.sales_excl),
                        0
                    ) as sales_excl,

                    COALESCE(
                        SUM(new_range_sales.total_cost_excl),
                        0
                    ) as total_cost_excl,

                    COALESCE(
                        SUM(new_range_sales.gross_profit),
                        0
                    ) as gross_profit
                    '
                )
                ->groupBy(
                    'new_range_branches.id',
                    'new_range_branches.name'
                )
                ->orderBy(
                    'new_range_branches.name'
                )
                ->get()
                ->map(
                    function ($row) {

                        $salesExcl =
                            (float)
                            $row->sales_excl;


                        $grossProfit =
                            (float)
                            $row->gross_profit;


                        return [

                            'branch_id' =>
                                (int)
                                $row->branch_id,

                            'branch_name' =>
                                $row->branch_name,

                            'sales_rows' =>
                                (int)
                                $row->sales_rows,

                            'units_sold' =>
                                (float)
                                $row->units_sold,

                            'sales_incl' =>
                                round(
                                    (float)
                                    $row->sales_incl,
                                    2
                                ),

                            'sales_excl' =>
                                round(
                                    $salesExcl,
                                    2
                                ),

                            'total_cost_excl' =>
                                round(
                                    (float)
                                    $row->total_cost_excl,
                                    2
                                ),

                            'gross_profit' =>
                                round(
                                    $grossProfit,
                                    2
                                ),

                            'gp_percentage' =>
                                $salesExcl > 0
                                    ? round(
                                        (
                                            $grossProfit /
                                            $salesExcl
                                        ) * 100,
                                        2
                                    )
                                    : 0,

                        ];

                    }
                )
                ->values();


        // ======================================================
        // MONTHLY SUMMARY
        // ======================================================

        $monthlyQuery =
            NewRangeSale::query();


        $this->applyFilters(
            $monthlyQuery,
            $validated
        );


        $months =
            $monthlyQuery
                ->selectRaw(
                    '
                    YEAR(sale_date) as year,
                    MONTH(sale_date) as month,
                    DATE_FORMAT(
                        sale_date,
                        "%Y-%m"
                    ) as month_key,

                    COUNT(*) as sales_rows,

                    COALESCE(
                        SUM(quantity),
                        0
                    ) as units_sold,

                    COALESCE(
                        SUM(sales_incl),
                        0
                    ) as sales_incl,

                    COALESCE(
                        SUM(sales_excl),
                        0
                    ) as sales_excl,

                    COALESCE(
                        SUM(total_cost_excl),
                        0
                    ) as total_cost_excl,

                    COALESCE(
                        SUM(gross_profit),
                        0
                    ) as gross_profit
                    '
                )
                ->groupByRaw(
                    '
                    YEAR(sale_date),
                    MONTH(sale_date),
                    DATE_FORMAT(
                        sale_date,
                        "%Y-%m"
                    )
                    '
                )
                ->orderByRaw(
                    '
                    YEAR(sale_date),
                    MONTH(sale_date)
                    '
                )
                ->get()
                ->map(
                    function ($row) {

                        $salesExcl =
                            (float)
                            $row->sales_excl;


                        $grossProfit =
                            (float)
                            $row->gross_profit;


                        return [

                            'year' =>
                                (int)
                                $row->year,

                            'month' =>
                                (int)
                                $row->month,

                            'month_key' =>
                                $row->month_key,

                            'month_name' =>
                                date(
                                    'F',
                                    mktime(
                                        0,
                                        0,
                                        0,
                                        (int)
                                        $row->month,
                                        1
                                    )
                                ),

                            'sales_rows' =>
                                (int)
                                $row->sales_rows,

                            'units_sold' =>
                                (float)
                                $row->units_sold,

                            'sales_incl' =>
                                round(
                                    (float)
                                    $row->sales_incl,
                                    2
                                ),

                            'sales_excl' =>
                                round(
                                    $salesExcl,
                                    2
                                ),

                            'total_cost_excl' =>
                                round(
                                    (float)
                                    $row->total_cost_excl,
                                    2
                                ),

                            'gross_profit' =>
                                round(
                                    $grossProfit,
                                    2
                                ),

                            'gp_percentage' =>
                                $salesExcl > 0
                                    ? round(
                                        (
                                            $grossProfit /
                                            $salesExcl
                                        ) * 100,
                                        2
                                    )
                                    : 0,

                        ];

                    }
                )
                ->values();


        // ======================================================
        // PRODUCT SUMMARY
        // ======================================================

        $productQuery =
            NewRangeSale::query()
                ->join(
                    'new_range_products',
                    'new_range_sales.product_id',
                    '=',
                    'new_range_products.id'
                );


        $this->applyFilters(
            $productQuery,
            $validated,
            'new_range_sales'
        );


        $products =
            $productQuery
                ->selectRaw(
                    '
                    new_range_products.id as product_id,
                    new_range_products.model_number,
                    new_range_products.description,

                    COUNT(new_range_sales.id) as sales_rows,

                    COALESCE(
                        SUM(new_range_sales.quantity),
                        0
                    ) as units_sold,

                    COALESCE(
                        SUM(new_range_sales.sales_incl),
                        0
                    ) as sales_incl,

                    COALESCE(
                        SUM(new_range_sales.sales_excl),
                        0
                    ) as sales_excl,

                    COALESCE(
                        SUM(new_range_sales.total_cost_excl),
                        0
                    ) as total_cost_excl,

                    COALESCE(
                        SUM(new_range_sales.gross_profit),
                        0
                    ) as gross_profit
                    '
                )
                ->groupBy(
                    'new_range_products.id',
                    'new_range_products.model_number',
                    'new_range_products.description'
                )
                ->orderBy(
                    'new_range_products.model_number'
                )
                ->get()
                ->map(
                    function ($row) {

                        $salesExcl =
                            (float)
                            $row->sales_excl;


                        $grossProfit =
                            (float)
                            $row->gross_profit;


                        return [

                            'product_id' =>
                                (int)
                                $row->product_id,

                            'model_number' =>
                                $row->model_number,

                            'description' =>
                                $row->description,

                            'sales_rows' =>
                                (int)
                                $row->sales_rows,

                            'units_sold' =>
                                (float)
                                $row->units_sold,

                            'sales_incl' =>
                                round(
                                    (float)
                                    $row->sales_incl,
                                    2
                                ),

                            'sales_excl' =>
                                round(
                                    $salesExcl,
                                    2
                                ),

                            'total_cost_excl' =>
                                round(
                                    (float)
                                    $row->total_cost_excl,
                                    2
                                ),

                            'gross_profit' =>
                                round(
                                    $grossProfit,
                                    2
                                ),

                            'gp_percentage' =>
                                $salesExcl > 0
                                    ? round(
                                        (
                                            $grossProfit /
                                            $salesExcl
                                        ) * 100,
                                        2
                                    )
                                    : 0,

                        ];

                    }
                )
                ->values();


        // ======================================================
        // INDIVIDUAL SALES
        // ======================================================

        $salesQuery =
            NewRangeSale::query()
                ->with([
                    'product:id,model_number,description',
                    'branch:id,name',
                ]);


        $this->applyFilters(
            $salesQuery,
            $validated
        );


        $sales =
            $salesQuery
                ->orderByDesc(
                    'sale_date'
                )
                ->orderByDesc(
                    'id'
                )
                ->get()
                ->map(
                    function ($sale) {

                        return [

                            'id' =>
                                $sale->id,

                            'sale_date' =>
                                $sale
                                    ->sale_date
                                    ?->format(
                                        'Y-m-d'
                                    ),

                            'branch_id' =>
                                $sale->branch_id,

                            'branch_name' =>
                                $sale
                                    ->branch
                                    ?->name,

                            'product_id' =>
                                $sale->product_id,

                            'model_number' =>
                                $sale
                                    ->product
                                    ?->model_number,

                            'description' =>
                                $sale
                                    ->product
                                    ?->description,

                            'quantity' =>
                                (float)
                                $sale->quantity,

                            'sales_incl' =>
                                (float)
                                $sale->sales_incl,

                            'sales_excl' =>
                                (float)
                                $sale->sales_excl,

                            'unit_cost_excl' =>
                                (float)
                                $sale->unit_cost_excl,

                            'total_cost_excl' =>
                                (float)
                                $sale->total_cost_excl,

                            'gross_profit' =>
                                (float)
                                $sale->gross_profit,

                            'gp_percentage' =>
                                $sale->gp_percentage !== null
                                    ? (float)
                                    $sale->gp_percentage
                                    : 0,

                            'source_sheet' =>
                                $sale->source_sheet,

                            'source_row' =>
                                $sale->source_row,

                        ];

                    }
                );


        // ======================================================
        // FILTER OPTIONS
        // ======================================================

        $branchOptions =
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
                ]);


        $productOptions =
            NewRangeProduct::query()
                ->where(
                    'is_active',
                    true
                )
                ->orderBy(
                    'model_number'
                )
                ->get([
                    'id',
                    'model_number',
                    'description',
                ]);


        // ======================================================
        // AVAILABLE YEARS
        // ======================================================

        $years =
            NewRangeSale::query()
                ->selectRaw(
                    'YEAR(sale_date) as year'
                )
                ->distinct()
                ->orderBy(
                    'year'
                )
                ->pluck(
                    'year'
                )
                ->map(
                    fn ($year) =>
                        (int)
                        $year
                )
                ->values();


        // ======================================================
        // RESPONSE
        // ======================================================

        return response()->json([

            'message' =>
                'NEW range Sales & GP report loaded successfully.',

            'filters' => [

                'branch_id' =>
                    $validated[
                        'branch_id'
                    ] ?? null,

                'product_id' =>
                    $validated[
                        'product_id'
                    ] ?? null,

                'year' =>
                    $validated[
                        'year'
                    ] ?? null,

                'month' =>
                    $validated[
                        'month'
                    ] ?? null,

                'date_from' =>
                    $validated[
                        'date_from'
                    ] ?? null,

                'date_to' =>
                    $validated[
                        'date_to'
                    ] ?? null,

            ],

            'summary' => [

                'sales_rows' =>
                    (int)
                    $summary->sales_rows,

                'units_sold' =>
                    (float)
                    $summary->units_sold,

                'sales_incl' =>
                    round(
                        (float)
                        $summary->sales_incl,
                        2
                    ),

                'sales_excl' =>
                    round(
                        $salesExcl,
                        2
                    ),

                'total_cost_excl' =>
                    round(
                        (float)
                        $summary->total_cost_excl,
                        2
                    ),

                'gross_profit' =>
                    round(
                        $grossProfit,
                        2
                    ),

                'gp_percentage' =>
                    $gpPercentage,

            ],

            'by_branch' =>
                $branches,

            'by_month' =>
                $months,

            'by_product' =>
                $products,

            'sales' =>
                $sales,

            'options' => [

                'branches' =>
                    $branchOptions,

                'products' =>
                    $productOptions,

                'years' =>
                    $years,

            ],

        ]);

    }


    /**
     * =========================================================
     * APPLY FILTERS
     * =========================================================
     */
    private function applyFilters(
        $query,
        array $filters,
        string $table = 'new_range_sales'
    ): void {

        $prefix =
            $table . '.';


        // ======================================================
        // BRANCH
        // ======================================================

        if (
            !empty(
                $filters[
                    'branch_id'
                ]
            )
        ) {

            $query->where(
                $prefix . 'branch_id',
                $filters[
                    'branch_id'
                ]
            );

        }


        // ======================================================
        // PRODUCT
        // ======================================================

        if (
            !empty(
                $filters[
                    'product_id'
                ]
            )
        ) {

            $query->where(
                $prefix . 'product_id',
                $filters[
                    'product_id'
                ]
            );

        }


        // ======================================================
        // YEAR
        // ======================================================

        if (
            !empty(
                $filters[
                    'year'
                ]
            )
        ) {

            $query->whereYear(
                $prefix . 'sale_date',
                $filters[
                    'year'
                ]
            );

        }


        // ======================================================
        // MONTH
        // ======================================================

        if (
            !empty(
                $filters[
                    'month'
                ]
            )
        ) {

            $query->whereMonth(
                $prefix . 'sale_date',
                $filters[
                    'month'
                ]
            );

        }


        // ======================================================
        // DATE FROM
        // ======================================================

        if (
            !empty(
                $filters[
                    'date_from'
                ]
            )
        ) {

            $query->whereDate(
                $prefix . 'sale_date',
                '>=',
                $filters[
                    'date_from'
                ]
            );

        }


        // ======================================================
        // DATE TO
        // ======================================================

        if (
            !empty(
                $filters[
                    'date_to'
                ]
            )
        ) {

            $query->whereDate(
                $prefix . 'sale_date',
                '<=',
                $filters[
                    'date_to'
                ]
            );

        }

    }
}
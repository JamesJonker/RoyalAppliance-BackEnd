<?php

namespace App\Http\Controllers;

use App\Models\NewRangeProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class NewRangeProductController extends Controller
{
    /**
     * =========================================================
     * LIST PRODUCTS
     * =========================================================
     */
    public function index(): JsonResponse
    {
        $products = NewRangeProduct::query()
            ->orderBy('model_number')
            ->get();

        return response()->json([
            'data' => $products
        ]);
    }


    /**
     * =========================================================
     * SHOW PRODUCT
     * =========================================================
     */
    public function show(
        NewRangeProduct $newRangeProduct
    ): JsonResponse {

        return response()->json([
            'data' => $newRangeProduct
        ]);
    }


    /**
     * =========================================================
     * CREATE PRODUCT MANUALLY
     * =========================================================
     */
    public function store(
        Request $request
    ): JsonResponse {

        $validated = $request->validate([
            'model_number' => [
                'required',
                'string',
                'max:255',
                'unique:new_range_products,model_number'
            ],

            'description' => [
                'nullable',
                'string',
                'max:255'
            ],

            'cost_excl' => [
                'required',
                'numeric',
                'min:0'
            ],

            'cost_incl' => [
                'required',
                'numeric',
                'min:0'
            ],

            'retail_incl' => [
                'required',
                'numeric',
                'min:0'
            ],

            'is_active' => [
                'sometimes',
                'boolean'
            ]
        ]);


        $validated['model_number'] =
            $this->normalizeModelNumber(
                $validated['model_number']
            );


        $product = NewRangeProduct::create(
            $validated
        );


        return response()->json([
            'message' =>
                'NEW range product created successfully.',

            'data' => $product
        ], 201);
    }


    /**
     * =========================================================
     * UPDATE PRODUCT / PRICING
     * =========================================================
     */
    public function update(
        Request $request,
        NewRangeProduct $newRangeProduct
    ): JsonResponse {

        $validated = $request->validate([
            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:255'
            ],

            'cost_excl' => [
                'sometimes',
                'numeric',
                'min:0'
            ],

            'cost_incl' => [
                'sometimes',
                'numeric',
                'min:0'
            ],

            'retail_incl' => [
                'sometimes',
                'numeric',
                'min:0'
            ],

            'is_active' => [
                'sometimes',
                'boolean'
            ]
        ]);


        $newRangeProduct->update(
            $validated
        );


        return response()->json([
            'message' =>
                'NEW range product updated successfully.',

            'data' =>
                $newRangeProduct->fresh()
        ]);
    }


    /**
     * =========================================================
     * IMPORT PRODUCT MASTER
     * =========================================================
     *
     * Expected workbook:
     *
     * Column A = Model
     * Column B = Description
     * Column C = Cost Excl
     * Column D = Cost Incl
     * Column E = Retail Incl
     *
     */
    public function import(
        Request $request
    ): JsonResponse {

        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls'
            ]
        ]);


        try {

            /*
            |------------------------------------------------------
            | Read workbook
            |------------------------------------------------------
            */

            $sheets = Excel::toArray(
                [],
                $request->file('file')
            );


            if (
                empty($sheets) ||
                empty($sheets[0])
            ) {

                return response()->json([
                    'message' =>
                        'The uploaded workbook contains no data.'
                ], 422);
            }


            $rows = $sheets[0];


            /*
            |------------------------------------------------------
            | Statistics
            |------------------------------------------------------
            */

            $created = 0;

            $updated = 0;

            $skipped = 0;

            $errors = [];


            DB::beginTransaction();


            /*
            |------------------------------------------------------
            | Skip row 1 because it contains headings
            |------------------------------------------------------
            */

            foreach (
                array_slice(
                    $rows,
                    1
                ) as $index => $row
            ) {

                /*
                |--------------------------------------------------
                | Actual Excel row
                |--------------------------------------------------
                */

                $excelRow =
                    $index + 2;


                /*
                |--------------------------------------------------
                | Read columns
                |--------------------------------------------------
                */

                $model =
                    trim(
                        (string) (
                            $row[0] ?? ''
                        )
                    );

                $description =
                    trim(
                        (string) (
                            $row[1] ?? ''
                        )
                    );

                $costExcl =
                    $row[2] ?? null;

                $costIncl =
                    $row[3] ?? null;

                $retailIncl =
                    $row[4] ?? null;


                /*
                |--------------------------------------------------
                | Ignore blank rows
                |--------------------------------------------------
                */

                if ($model === '') {

                    $skipped++;

                    continue;
                }


                /*
                |--------------------------------------------------
                | Only import NEW range products
                |--------------------------------------------------
                |
                | This automatically ignores headings such as:
                |
                | DEFY FRIDGE
                |
                */

                $normalizedModel =
                    $this->normalizeModelNumber(
                        $model
                    );


                if (
                    !str_contains(
                        strtoupper(
                            $normalizedModel
                        ),
                        '-NEW'
                    )
                ) {

                    $skipped++;

                    continue;
                }


                /*
                |--------------------------------------------------
                | Validate pricing
                |--------------------------------------------------
                */

                if (
                    !is_numeric($costExcl) ||
                    !is_numeric($costIncl) ||
                    !is_numeric($retailIncl)
                ) {

                    $errors[] = [
                        'row' => $excelRow,
                        'model' => $model,
                        'message' =>
                            'Invalid cost or retail price.'
                    ];

                    $skipped++;

                    continue;
                }


                /*
                |--------------------------------------------------
                | Find existing product
                |--------------------------------------------------
                */

                $product =
                    NewRangeProduct::where(
                        'model_number',
                        $normalizedModel
                    )->first();


                /*
                |--------------------------------------------------
                | Update existing product
                |--------------------------------------------------
                */

                if ($product) {

                    $product->update([
                        'description' =>
                            $description !== ''
                                ? $description
                                : $product->description,

                        'cost_excl' =>
                            round(
                                (float) $costExcl,
                                2
                            ),

                        'cost_incl' =>
                            round(
                                (float) $costIncl,
                                2
                            ),

                        'retail_incl' =>
                            round(
                                (float) $retailIncl,
                                2
                            ),

                        'is_active' => true,
                    ]);


                    $updated++;

                }

                /*
                |--------------------------------------------------
                | Create new product
                |--------------------------------------------------
                */

                else {

                    NewRangeProduct::create([

                        'model_number' =>
                            $normalizedModel,

                        'description' =>
                            $description !== ''
                                ? $description
                                : null,

                        'cost_excl' =>
                            round(
                                (float) $costExcl,
                                2
                            ),

                        'cost_incl' =>
                            round(
                                (float) $costIncl,
                                2
                            ),

                        'retail_incl' =>
                            round(
                                (float) $retailIncl,
                                2
                            ),

                        'is_active' => true,
                    ]);


                    $created++;

                }

            }


            DB::commit();


            /*
            |------------------------------------------------------
            | Response
            |------------------------------------------------------
            */

            return response()->json([

                'message' =>
                    'NEW range product workbook imported successfully.',

                'summary' => [

                    'created' =>
                        $created,

                    'updated' =>
                        $updated,

                    'skipped' =>
                        $skipped,

                    'errors' =>
                        count($errors),

                    'total_products' =>
                        NewRangeProduct::count(),
                ],

                'import_errors' =>
                    $errors

            ]);

        }

        catch (Throwable $exception) {

            DB::rollBack();


            return response()->json([

                'message' =>
                    'Unable to import NEW range product workbook.',

                'error' =>
                    $exception->getMessage()

            ], 500);

        }
    }


    /**
     * =========================================================
     * NORMALIZE MODEL NUMBER
     * =========================================================
     *
     * Product master:
     *
     * DAC363-NEW
     *
     * Morning sales workbook:
     *
     * DAC363-NEW-01
     *
     * Both become:
     *
     * DAC363-NEW
     *
     */
    private function normalizeModelNumber(
        string $model
    ): string {

        $model =
            strtoupper(
                trim($model)
            );


        /*
        |----------------------------------------------------------
        | Remove spaces
        |----------------------------------------------------------
        */

        $model =
            preg_replace(
                '/\s+/',
                '',
                $model
            );


        /*
        |----------------------------------------------------------
        | Remove Grade A suffix from sales PLU
        |----------------------------------------------------------
        |
        | DAC363-NEW-01
        |
        | becomes:
        |
        | DAC363-NEW
        |
        */

        $model =
            preg_replace(
                '/-NEW-01$/i',
                '-NEW',
                $model
            );


        return $model;
    }
}
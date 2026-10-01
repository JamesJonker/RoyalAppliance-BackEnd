<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoanController extends Controller
{

    /*
    |--------------------------------------------------------------------------
    | APPLY FOR LOAN
    |--------------------------------------------------------------------------
    */

    public function apply(Request $request)
    {

        /*
        |--------------------------------------------------------------------------
        | VALIDATE REQUEST
        |--------------------------------------------------------------------------
        */

        $request->validate([

            'staff_id' => 'required|exists:staff,id',

            'amount' => 'required|numeric|min:1',

            'months' => 'required|in:1,2,3,6',

            'start_month' => 'required|date',

            'repayments' => 'required|array',

            'repayments.*' => 'required|numeric|min:0',

        ]);


        /*
        |--------------------------------------------------------------------------
        | CHECK NUMBER OF REPAYMENTS
        |--------------------------------------------------------------------------
        */

        if (
            count($request->repayments)
            !=
            (int) $request->months
        ) {

            return response()->json([

                'message' =>
                    'The number of repayments does not match the loan duration.'

            ], 422);
        }


        /*
        |--------------------------------------------------------------------------
        | CALCULATE REPAYMENT TOTAL
        |--------------------------------------------------------------------------
        */

        $repaymentTotal = 0;

        foreach ($request->repayments as $repayment) {

            $repaymentTotal +=
                (float) $repayment;
        }


        /*
        |--------------------------------------------------------------------------
        | ROUND TOTAL
        |--------------------------------------------------------------------------
        */

        $repaymentTotal =
            round($repaymentTotal, 2);


        $loanAmount =
            round((float) $request->amount, 2);


        /*
        |--------------------------------------------------------------------------
        | CHECK REPAYMENT TOTAL
        |--------------------------------------------------------------------------
        */

        if (
            abs(
                $repaymentTotal
                -
                $loanAmount
            ) > 0.01
        ) {

            return response()->json([

                'message' =>
                    'The repayment amounts must equal the loan amount.',

                'loan_amount' =>
                    $loanAmount,

                'repayment_total' =>
                    $repaymentTotal,

            ], 422);
        }


        /*
        |--------------------------------------------------------------------------
        | CREATE LOAN
        |--------------------------------------------------------------------------
        */

        DB::beginTransaction();


        try {

            /*
            |--------------------------------------------------------------------------
            | Calculate average monthly deduction
            |--------------------------------------------------------------------------
            |
            | This is kept in the loans table for compatibility
            | with your existing system.
            |
            */

            $monthlyDeduction =
                round(
                    $loanAmount
                    /
                    (int) $request->months,
                    2
                );


            /*
            |--------------------------------------------------------------------------
            | Create loan
            |--------------------------------------------------------------------------
            */

            $loan = Loan::create([

                'staff_id' =>
                    $request->staff_id,

                'amount' =>
                    $loanAmount,

                'months' =>
                    $request->months,

                'monthly_deduction' =>
                    $monthlyDeduction,

                'remaining_amount' =>
                    $loanAmount,

                'months_remaining' =>
                    $request->months,

                'start_month' =>
                    $request->start_month,

                'status' =>
                    'active',

            ]);


            /*
            |--------------------------------------------------------------------------
            | Create repayment records
            |--------------------------------------------------------------------------
            */

            foreach (
                $request->repayments
                as $index => $repayment
            ) {

                $loan->repayments()->create([

                    'month_number' =>
                        $index + 1,

                    'deduction_amount' =>
                        round(
                            (float) $repayment,
                            2
                        ),

                    'status' =>
                        'pending',

                ]);

            }


            /*
            |--------------------------------------------------------------------------
            | Commit transaction
            |--------------------------------------------------------------------------
            */

            DB::commit();


            /*
            |--------------------------------------------------------------------------
            | Return loan
            |--------------------------------------------------------------------------
            */

            return response()->json([

                'message' =>
                    'Loan applied successfully',

                'loan' =>
                    $loan->load('repayments'),

            ], 201);


        } catch (\Exception $e) {

            /*
            |--------------------------------------------------------------------------
            | Rollback if something failed
            |--------------------------------------------------------------------------
            */

            DB::rollBack();


            return response()->json([

                'message' =>
                    'Error creating loan.',

                'error' =>
                    $e->getMessage(),

            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | GET LOANS FOR STAFF
    |--------------------------------------------------------------------------
    */

    public function index($staffId)
    {

        $staff =
            Staff::findOrFail($staffId);


        $loans =
            $staff
                ->loans()
                ->with('repayments')
                ->get();


        return response()->json([

            'loans' =>
                $loans,

        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | GET LOANS BY STAFF
    |--------------------------------------------------------------------------
    */

    public function indexByStaff($staffId)
    {

        $loans =
            Loan::where(
                'staff_id',
                $staffId
            )
            ->with('repayments')
            ->get();


        return response()->json([

            'active' =>
                $loans
                    ->where(
                        'status',
                        'active'
                    )
                    ->values(),

            'completed' =>
                $loans
                    ->where(
                        'status',
                        'completed'
                    )
                    ->values(),

        ]);
    }
}
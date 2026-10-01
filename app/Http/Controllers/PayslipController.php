<?php

namespace App\Http\Controllers;

use App\Models\Payslip;
use App\Models\Staff;
use App\Models\Loan;
use App\Models\LeaveType;
use App\Models\StaffLeave;

use App\Services\LeaveService;
use App\Services\PayrollService;

use Barryvdh\DomPDF\Facade\Pdf;

use Carbon\Carbon;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayslipController extends Controller
{
    protected LeaveService $leaveService;

    protected PayrollService $payrollService;


    /*
    |--------------------------------------------------------------------------
    | CONSTRUCTOR
    |--------------------------------------------------------------------------
    */

    public function __construct(
        LeaveService $leaveService,
        PayrollService $payrollService
    ) {
        $this->leaveService =
            $leaveService;

        $this->payrollService =
            $payrollService;
    }


    /*
    |--------------------------------------------------------------------------
    | GENERATE SINGLE PAYSLIP
    |--------------------------------------------------------------------------
    */

    public function generate(
        Request $request
    ) {

        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        $validated =
            $request->validate([

                'staff_id' => [
                    'required',
                    'integer',
                    'exists:staff,id',
                ],

                'month' => [
                    'required',
                    'integer',
                    'min:1',
                    'max:12',
                ],

                'year' => [
                    'required',
                    'integer',
                    'min:2000',
                ],

                /*
                |--------------------------------------------------------------------------
                | FINAL / PRORATED PAY
                |--------------------------------------------------------------------------
                */

                'is_prorated' => [
                    'sometimes',
                    'boolean',
                ],

                'termination_date' => [
                    'nullable',
                    'date',
                ],

                'days_worked' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],

                'daily_rate' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],

                'leave_days_due' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],

                'leave_daily_rate' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],

            ]);


        /*
        |--------------------------------------------------------------------------
        | STAFF
        |--------------------------------------------------------------------------
        */

        $staff =
            Staff::with([
                'workSchedule.days',
            ])
            ->findOrFail(
                $validated['staff_id']
            );


        /*
        |--------------------------------------------------------------------------
        | CURRENT MONTH
        |--------------------------------------------------------------------------
        */

        $currentMonth =
            Carbon::create(
                (int) $validated['year'],
                (int) $validated['month'],
                1
            )
            ->startOfMonth();


        /*
        |--------------------------------------------------------------------------
        | PAY DATE
        |--------------------------------------------------------------------------
        */

        $payDate =
            Carbon::create(
                (int) $validated['year'],
                (int) $validated['month'],
                26
            );


        /*
        |--------------------------------------------------------------------------
        | FINAL / PRORATED FLAG
        |--------------------------------------------------------------------------
        */

        $isProrated =
            (bool) (
                $validated['is_prorated']
                ?? false
            );


        /*
        |--------------------------------------------------------------------------
        | TERMINATION DATE
        |--------------------------------------------------------------------------
        */

        $terminationDate =
            null;


        if (
            $isProrated
            &&
            !empty(
                $validated['termination_date']
            )
        ) {

            $terminationDate =
                Carbon::parse(
                    $validated['termination_date']
                )
                ->startOfDay();
        }


        /*
        |--------------------------------------------------------------------------
        | PERIOD ENDING
        |--------------------------------------------------------------------------
        */

        if (
            $isProrated
            &&
            $terminationDate
        ) {

            $periodEnding =
                $terminationDate
                    ->copy();

        } else {

            $periodEnding =
                $currentMonth
                    ->copy()
                    ->endOfMonth();
        }


        /*
        |--------------------------------------------------------------------------
        | LEAVE BALANCE AS-OF DATE
        |--------------------------------------------------------------------------
        |
        | For a final payslip, use the termination date.
        | Otherwise use the normal pay date.
        |
        */

        $leaveAsOfDate =
            $isProrated
            &&
            $terminationDate

                ? $terminationDate
                    ->copy()

                : $payDate
                    ->copy();


        /*
        |--------------------------------------------------------------------------
        | FINANCIAL MONTH NUMBER
        |--------------------------------------------------------------------------
        */

        $adjustedMonthNo =
            $this->getFinancialMonthNumber(
                (int)
                $validated['month']
            );


        /*
        |--------------------------------------------------------------------------
        | EXISTING PAYSLIP
        |--------------------------------------------------------------------------
        */

        $existingPayslip =
            Payslip::where(
                'staff_id',
                $staff->id
            )
            ->where(
                'year',
                $validated['year']
            )
            ->where(
                'month',
                $adjustedMonthNo
            )
            ->first();


        if (
            $existingPayslip
        ) {

            return response()->json([

                'message' =>
                    'A payslip has already been generated for this month.',

                'payslip' =>
                    $existingPayslip,

                'pdf_url' =>
                    $existingPayslip->pdf_path,

            ], 422);
        }


        /*
        |--------------------------------------------------------------------------
        | PAYROLL VALIDATION
        |--------------------------------------------------------------------------
        */

        try {

            $this->payrollService
                ->ensureEmployeeIsValidForPayroll(
                    $staff,
                    $payDate
                );

        } catch (
            \Throwable $e
        ) {

            return response()->json([

                'message' =>
                    'Employee cannot be processed for payroll.',

                'error' =>
                    $e->getMessage(),

            ], 422);
        }


        /*
        |--------------------------------------------------------------------------
        | LEAVE DATA
        |--------------------------------------------------------------------------
        */

        $leaveData =
            $this->getLeaveDataForPayslip(

                $staff,

                $leaveAsOfDate,

                $currentMonth,

                $periodEnding

            );


        $leaveBalances =
            $leaveData['balances'];


        $leaveTakenForPeriod =
            $leaveData['taken'];


        $totalLeaveTakenForPeriod =
            $leaveData['total_taken'];


        /*
        |--------------------------------------------------------------------------
        | DEFAULT NORMAL PAY VALUES
        |--------------------------------------------------------------------------
        */

        $daysWorked =
            null;


        $dailyRate =
            null;


        $workedGross =
            round(
                (float) (
                    $staff->salary
                    ?? 0
                ),
                2
            );


        $leaveDaysDue =
            0.00;


        $leaveDailyRate =
            0.00;


        $grossLeavePay =
            0.00;


        /*
        |--------------------------------------------------------------------------
        | FINAL / PRORATED PAY VALUES
        |--------------------------------------------------------------------------
        */

        if (
            $isProrated
        ) {

            /*
            |--------------------------------------------------------------------------
            | DAYS WORKED
            |--------------------------------------------------------------------------
            */

            $daysWorked =
                (float) (
                    $validated['days_worked']
                    ?? 0
                );


            /*
            |--------------------------------------------------------------------------
            | DAILY RATE
            |--------------------------------------------------------------------------
            */

            $dailyRate =
                (float) (
                    $validated['daily_rate']
                    ?? 0
                );


            /*
            |--------------------------------------------------------------------------
            | LEAVE DAYS DUE
            |--------------------------------------------------------------------------
            */

            $leaveDaysDue =
                (float) (
                    $validated['leave_days_due']
                    ?? 0
                );


            /*
            |--------------------------------------------------------------------------
            | LEAVE DAILY RATE
            |--------------------------------------------------------------------------
            |
            | If no separate rate is entered,
            | use the normal daily rate.
            |
            */

            if (
                isset(
                    $validated['leave_daily_rate']
                )
                &&
                $validated['leave_daily_rate']
                    !== null
                &&
                $validated['leave_daily_rate']
                    !== ''
            ) {

                $leaveDailyRate =
                    (float)
                    $validated['leave_daily_rate'];

            } else {

                $leaveDailyRate =
                    $dailyRate;
            }


            /*
            |--------------------------------------------------------------------------
            | VALIDATION
            |--------------------------------------------------------------------------
            */

            if (
                $dailyRate <= 0
            ) {

                return response()->json([

                    'message' =>
                        'A valid daily rate is required for a final/prorated payslip.',

                ], 422);
            }


            if (
                $daysWorked < 0
            ) {

                return response()->json([

                    'message' =>
                        'Days worked cannot be less than zero.',

                ], 422);
            }


            if (
                $leaveDaysDue > 0
                &&
                $leaveDailyRate <= 0
            ) {

                return response()->json([

                    'message' =>
                        'A valid leave rate per day is required when leave is paid out.',

                ], 422);
            }


            /*
            |--------------------------------------------------------------------------
            | WORKED GROSS
            |--------------------------------------------------------------------------
            */

            $workedGross =
                round(
                    $daysWorked
                    *
                    $dailyRate,
                    2
                );


            /*
            |--------------------------------------------------------------------------
            | GROSS LEAVE PAY
            |--------------------------------------------------------------------------
            */

            $grossLeavePay =
                round(
                    $leaveDaysDue
                    *
                    $leaveDailyRate,
                    2
                );
        }


        /*
        |--------------------------------------------------------------------------
        | TOTAL GROSS
        |--------------------------------------------------------------------------
        */

        $gross =
            round(
                $workedGross
                +
                $grossLeavePay,
                2
            );


        /*
        |--------------------------------------------------------------------------
        | LOANS
        |--------------------------------------------------------------------------
        */

        $loans =
            Loan::where(
                'staff_id',
                $staff->id
            )
            ->where(
                'status',
                'active'
            )
            ->with(
                'repayments'
            )
            ->get();


        /*
        |--------------------------------------------------------------------------
        | REPAYMENTS FOR THIS PAYSLIP
        |--------------------------------------------------------------------------
        */

        $repaymentsForPayslip =
            collect();


        foreach (
            $loans
            as $loan
        ) {

            if (
                !$loan->start_month
            ) {

                continue;
            }


            $loanStartMonth =
                Carbon::parse(
                    $loan->start_month
                )
                ->startOfMonth();


            if (
                $currentMonth->lt(
                    $loanStartMonth
                )
            ) {

                continue;
            }


            $repaymentMonthNumber =
                $loanStartMonth
                    ->diffInMonths(
                        $currentMonth
                    )
                +
                1;


            $repayment =
                $loan
                    ->repayments
                    ->where(
                        'month_number',
                        $repaymentMonthNumber
                    )
                    ->first();


            if (
                !$repayment
            ) {

                continue;
            }


            if (
                $repayment->status
                === 'paid'
            ) {

                continue;
            }


            $repaymentsForPayslip
                ->push(
                    $repayment
                );
        }


        /*
        |--------------------------------------------------------------------------
        | PAYROLL CALCULATION
        |--------------------------------------------------------------------------
        */

        try {

            /*
            |--------------------------------------------------------------------------
            | FINAL / PRORATED PAYROLL
            |--------------------------------------------------------------------------
            */

            if (
                $isProrated
            ) {

                /*
                |--------------------------------------------------------------------------
                | AGE AT PAY DATE
                |--------------------------------------------------------------------------
                */

                $age =
                    Carbon::parse(
                        $staff->date_of_birth
                    )
                    ->diffInYears(
                        $payDate
                    );


                /*
                |--------------------------------------------------------------------------
                | INITIAL PAYROLL CALCULATION
                |--------------------------------------------------------------------------
                |
                | PAYE is calculated using total gross:
                |
                | worked gross + leave payout
                |
                */

                $payroll =
                    $this->payrollService
                        ->calculatePayroll(

                            $gross,

                            $age,

                            0.00,

                            0.00,

                            0.00,

                            $payDate,

                            false,

                            $staff

                        );


                /*
                |--------------------------------------------------------------------------
                | UIF ON WORKED PAY
                |--------------------------------------------------------------------------
                */

                $workedUif =
                    $this->payrollService
                        ->calculateUIFEmployee(
                            $workedGross,
                            $payDate
                        );


                /*
                |--------------------------------------------------------------------------
                | UIF ON LEAVE PAY
                |--------------------------------------------------------------------------
                */

                $leaveUif =
                    $this->payrollService
                        ->calculateUIFEmployee(
                            $grossLeavePay,
                            $payDate
                        );


                /*
                |--------------------------------------------------------------------------
                | TOTAL EMPLOYEE UIF
                |--------------------------------------------------------------------------
                */

                $totalUif =
                    round(
                        $workedUif
                        +
                        $leaveUif,
                        2
                    );


                /*
                |--------------------------------------------------------------------------
                | EMPLOYER UIF - WORKED PAY
                |--------------------------------------------------------------------------
                */

                $workedEmployerUif =
                    $this->payrollService
                        ->calculateUIFEmployer(
                            $workedGross,
                            $payDate
                        );


                /*
                |--------------------------------------------------------------------------
                | EMPLOYER UIF - LEAVE PAY
                |--------------------------------------------------------------------------
                */

                $leaveEmployerUif =
                    $this->payrollService
                        ->calculateUIFEmployer(
                            $grossLeavePay,
                            $payDate
                        );


                /*
                |--------------------------------------------------------------------------
                | TOTAL EMPLOYER UIF
                |--------------------------------------------------------------------------
                */

                $totalEmployerUif =
                    round(
                        $workedEmployerUif
                        +
                        $leaveEmployerUif,
                        2
                    );


                /*
                |--------------------------------------------------------------------------
                | PAYE
                |--------------------------------------------------------------------------
                */

                $paye =
                    (float)
                    $payroll['paye'];


                /*
                |--------------------------------------------------------------------------
                | LOAN
                |--------------------------------------------------------------------------
                */

                $loanDeduction =
                    (float)
                    $payroll['loan_deductions'];


                /*
                |--------------------------------------------------------------------------
                | OTHER DEDUCTIONS
                |--------------------------------------------------------------------------
                */

                $otherDeductions =
                    (float) (
                        $payroll['other_deductions']
                        ?? 0
                    );


                /*
                |--------------------------------------------------------------------------
                | TOTAL DEDUCTIONS
                |--------------------------------------------------------------------------
                |
                | PAYE
                | + worked UIF
                | + leave UIF
                | + loan
                | + other deductions
                |
                */

                $correctedTotalDeductions =
                    round(
                        $paye
                        +
                        $totalUif
                        +
                        $loanDeduction
                        +
                        $otherDeductions,
                        2
                    );


                /*
                |--------------------------------------------------------------------------
                | NET FINAL PAY
                |--------------------------------------------------------------------------
                */

                $correctedNetSalary =
                    round(
                        $gross
                        -
                        $correctedTotalDeductions,
                        2
                    );


                /*
                |--------------------------------------------------------------------------
                | CORRECT EMPLOYER COST
                |--------------------------------------------------------------------------
                */

                $correctedEmployerCost =
                    round(
                        $gross
                        +
                        $totalEmployerUif
                        +
                        (float) (
                            $payroll['sdl']
                            ?? 0
                        ),
                        2
                    );


                /*
                |--------------------------------------------------------------------------
                | OVERRIDE PAYROLL VALUES
                |--------------------------------------------------------------------------
                */

                $payroll['uif_employee'] =
                    $totalUif;


                $payroll['uif_employer'] =
                    $totalEmployerUif;


                $payroll['total_deductions'] =
                    $correctedTotalDeductions;


                $payroll['net_salary'] =
                    $correctedNetSalary;


                $payroll['employer_cost'] =
                    $correctedEmployerCost;


                /*
                |--------------------------------------------------------------------------
                | YTD BEFORE CURRENT PAYSLIP
                |--------------------------------------------------------------------------
                */

                $ytd =
                    $this->payrollService
                        ->calculateYTD(
                            $staff,
                            $payDate
                        );


                /*
                |--------------------------------------------------------------------------
                | YTD GROSS
                |--------------------------------------------------------------------------
                */

                $payroll['ytd_gross'] =
                    round(
                        (float)
                        $ytd['gross']
                        +
                        $gross,
                        2
                    );


                /*
                |--------------------------------------------------------------------------
                | YTD PAYE
                |--------------------------------------------------------------------------
                */

                $payroll['ytd_paye'] =
                    round(
                        (float)
                        $ytd['paye']
                        +
                        $paye,
                        2
                    );


                /*
                |--------------------------------------------------------------------------
                | YTD UIF
                |--------------------------------------------------------------------------
                */

                $payroll['ytd_uif'] =
                    round(
                        (float)
                        $ytd['uif']
                        +
                        $totalUif,
                        2
                    );


                /*
                |--------------------------------------------------------------------------
                | YTD DEDUCTIONS
                |--------------------------------------------------------------------------
                */

                $payroll['ytd_deductions'] =
                    round(
                        (float)
                        $ytd['deductions']
                        +
                        $correctedTotalDeductions,
                        2
                    );


                /*
                |--------------------------------------------------------------------------
                | YTD NET
                |--------------------------------------------------------------------------
                */

                $payroll['ytd_net'] =
                    round(
                        (float)
                        $ytd['net']
                        +
                        $correctedNetSalary,
                        2
                    );

            } else {

                /*
                |--------------------------------------------------------------------------
                | NORMAL MONTHLY PAYROLL
                |--------------------------------------------------------------------------
                */

                $payroll =
                    $this->payrollService
                        ->calculateEmployeePayroll(

                            $staff,

                            0.00,

                            0.00,

                            0.00,

                            $payDate

                        );


                /*
                |--------------------------------------------------------------------------
                | NORMAL PAYSLIP HAS NO LEAVE PAYOUT UIF
                |--------------------------------------------------------------------------
                */

                $workedUif =
                    (float)
                    $payroll['uif_employee'];


                $leaveUif =
                    0.00;
            }

        } catch (
            \Throwable $e
        ) {

            return response()->json([

                'message' =>
                    'Payroll calculation failed.',

                'error' =>
                    $e->getMessage(),

            ], 422);
        }


        /*
        |--------------------------------------------------------------------------
        | FINAL PAYROLL VALUES
        |--------------------------------------------------------------------------
        */

        $paye =
            (float)
            $payroll['paye'];


        $uif =
            (float)
            $payroll['uif_employee'];


        $loanDeduction =
            (float)
            $payroll['loan_deductions'];


        $deductions =
            (float)
            $payroll['total_deductions'];


        $net =
            (float)
            $payroll['net_salary'];


        /*
        |--------------------------------------------------------------------------
        | LEAVE PAY DEDUCTION ALLOCATION
        |--------------------------------------------------------------------------
        */

        $leavePaye =
            0.00;


        $netLeavePay =
            0.00;


        if (
            $isProrated
            &&
            $grossLeavePay > 0
            &&
            $gross > 0
        ) {

            /*
            |--------------------------------------------------------------------------
            | PAYE ALLOCATION
            |--------------------------------------------------------------------------
            |
            | PAYE was calculated against total remuneration.
            |
            | We allocate a portion to leave for display purposes.
            |
            */

            $leaveShare =
                $grossLeavePay
                /
                $gross;


            $leavePaye =
                round(
                    $paye
                    *
                    $leaveShare,
                    2
                );


            /*
            |--------------------------------------------------------------------------
            | LEAVE UIF
            |--------------------------------------------------------------------------
            |
            | This is the actual UIF calculated separately
            | on the leave payout.
            |
            */

            $leaveUif =
                round(
                    (float)
                    $leaveUif,
                    2
                );


            /*
            |--------------------------------------------------------------------------
            | NET LEAVE PAY
            |--------------------------------------------------------------------------
            */

            $netLeavePay =
                round(
                    max(
                        0,
                        $grossLeavePay
                        -
                        $leavePaye
                        -
                        $leaveUif
                    ),
                    2
                );
        }


        /*
        |--------------------------------------------------------------------------
        | YTD VALUES
        |--------------------------------------------------------------------------
        */

        $ytdGross =
            (float)
            $payroll['ytd_gross'];


        $ytdDeductions =
            (float)
            $payroll['ytd_deductions'];


        $ytdNet =
            (float)
            $payroll['ytd_net'];


        /*
        |--------------------------------------------------------------------------
        | DATABASE TRANSACTION
        |--------------------------------------------------------------------------
        */

        DB::beginTransaction();


        try {

            /*
            |--------------------------------------------------------------------------
            | CREATE PAYSLIP
            |--------------------------------------------------------------------------
            */

            $payslip =
                Payslip::create([

                    /*
                    |--------------------------------------------------------------------------
                    | STAFF
                    |--------------------------------------------------------------------------
                    */

                    'staff_id' =>
                        $staff->id,


                    /*
                    |--------------------------------------------------------------------------
                    | PERIOD
                    |--------------------------------------------------------------------------
                    */

                    'month' =>
                        $adjustedMonthNo,

                    'year' =>
                        $validated['year'],


                    /*
                    |--------------------------------------------------------------------------
                    | MAIN PAYROLL
                    |--------------------------------------------------------------------------
                    */

                    'gross_salary' =>
                        $gross,

                    'paye' =>
                        $paye,

                    'uif' =>
                        $uif,

                    'deductions' =>
                        $deductions,

                    'net_salary' =>
                        $net,


                    /*
                    |--------------------------------------------------------------------------
                    | YTD
                    |--------------------------------------------------------------------------
                    */

                    'ytd_gross' =>
                        $ytdGross,

                    'ytd_deductions' =>
                        $ytdDeductions,

                    'ytd_net' =>
                        $ytdNet,


                    /*
                    |--------------------------------------------------------------------------
                    | DATES
                    |--------------------------------------------------------------------------
                    */

                    'generated_at' =>
                        now(),

                    'pay_date' =>
                        $payDate
                            ->toDateString(),

                    'period_ending' =>
                        $periodEnding
                            ->toDateString(),


                    /*
                    |--------------------------------------------------------------------------
                    | FINAL / PRORATED PAY
                    |--------------------------------------------------------------------------
                    */

                    'is_prorated' =>
                        $isProrated,

                    'termination_date' =>
                        $terminationDate
                            ? $terminationDate
                                ->toDateString()
                            : null,

                    'days_worked' =>
                        $daysWorked,

                    'daily_rate' =>
                        $dailyRate,

                    'worked_gross' =>
                        $workedGross,

                    'leave_days_due' =>
                        $leaveDaysDue,

                    'leave_daily_rate' =>
                        $leaveDailyRate,

                    'gross_leave_pay' =>
                        $grossLeavePay,


                    /*
                    |--------------------------------------------------------------------------
                    | LEAVE TAX / UIF
                    |--------------------------------------------------------------------------
                    */

                    'leave_paye' =>
                        $leavePaye,

                    'leave_uif' =>
                        $leaveUif,

                    'net_leave_pay' =>
                        $netLeavePay,

                    'gross_remuneration' =>
                        $gross,

                ]);


            /*
            |--------------------------------------------------------------------------
            | MARK LOAN REPAYMENTS PAID
            |--------------------------------------------------------------------------
            */

            foreach (
                $repaymentsForPayslip
                as $repayment
            ) {

                $repayment->status =
                    'paid';


                $repayment->save();
            }


            /*
            |--------------------------------------------------------------------------
            | COMPLETE LOANS
            |--------------------------------------------------------------------------
            */

            foreach (
                $loans
                as $loan
            ) {

                $loan->load(
                    'repayments'
                );


                $pendingRepayments =
                    $loan
                        ->repayments
                        ->where(
                            'status',
                            '!=',
                            'paid'
                        )
                        ->count();


                if (
                    $pendingRepayments
                    === 0
                    &&
                    $loan
                        ->repayments
                        ->count() > 0
                ) {

                    $loan->status =
                        'completed';


                    $loan->remaining_amount =
                        0;


                    $loan->remaining_months =
                        0;


                    $loan->save();
                }
            }


            DB::commit();

        } catch (
            \Throwable $e
        ) {

            DB::rollBack();


            return response()->json([

                'message' =>
                    'Error creating payslip.',

                'error' =>
                    $e->getMessage(),

            ], 500);
        }


        /*
        |--------------------------------------------------------------------------
        | GENERATE PDF
        |--------------------------------------------------------------------------
        */

        $pdfContent =
            $this->generatePayslipPdfContent(

                $staff,

                $payslip,

                $loanDeduction,

                $loans,

                $payDate,

                $periodEnding,

                $leaveBalances,

                $leaveTakenForPeriod,

                $totalLeaveTakenForPeriod

            );


        /*
        |--------------------------------------------------------------------------
        | PAYSLIP DIRECTORY
        |--------------------------------------------------------------------------
        */

        $payslipDirectory =
            public_path(
                'payslips'
            );


        if (
            !is_dir(
                $payslipDirectory
            )
        ) {

            mkdir(
                $payslipDirectory,
                0755,
                true
            );
        }


        /*
        |--------------------------------------------------------------------------
        | SAVE PDF
        |--------------------------------------------------------------------------
        */

        $publicPath =
            public_path(
                "payslips/{$payslip->id}.pdf"
            );


        file_put_contents(
            $publicPath,
            $pdfContent
        );


        /*
        |--------------------------------------------------------------------------
        | PDF URL
        |--------------------------------------------------------------------------
        */

        $pdfUrl =
            url(
                "payslips/{$payslip->id}.pdf"
            );


        /*
        |--------------------------------------------------------------------------
        | UPDATE PAYSLIP
        |--------------------------------------------------------------------------
        */

        $payslip->update([

            'pdf_path' =>
                $pdfUrl,

        ]);


        /*
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */

        return response()->json([

            'message' =>
                $isProrated
                    ? 'Final payslip generated.'
                    : 'Payslip generated.',

            'payslip' =>
                $payslip,

            'pdf_url' =>
                $pdfUrl,

            'loan_deduction' =>
                $loanDeduction,

            'leave_balances' =>
                $leaveBalances,

            'leave_taken' =>
                $leaveTakenForPeriod,

            'total_leave_taken' =>
                $totalLeaveTakenForPeriod,


            /*
            |--------------------------------------------------------------------------
            | FINAL PAY BREAKDOWN
            |--------------------------------------------------------------------------
            */

            'final_pay' => [

                'is_prorated' =>
                    $isProrated,

                'days_worked' =>
                    $daysWorked,

                'daily_rate' =>
                    $dailyRate,

                'worked_gross' =>
                    $workedGross,

                'worked_uif' =>
                    $workedUif
                    ?? 0,

                'leave_days_due' =>
                    $leaveDaysDue,

                'leave_daily_rate' =>
                    $leaveDailyRate,

                'gross_leave_pay' =>
                    $grossLeavePay,

                'leave_paye' =>
                    $leavePaye,

                'leave_uif' =>
                    $leaveUif,

                'net_leave_pay' =>
                    $netLeavePay,

                'total_uif' =>
                    $uif,

                'gross_remuneration' =>
                    $gross,

            ],


            /*
            |--------------------------------------------------------------------------
            | PAYROLL
            |--------------------------------------------------------------------------
            */

            'payroll' => [

                'paye' =>
                    $paye,

                'uif_employee' =>
                    $uif,

                'uif_employer' =>
                    $payroll['uif_employer'],

                'sdl' =>
                    $payroll['sdl'],

                'loan_deductions' =>
                    $loanDeduction,

                'total_deductions' =>
                    $deductions,

                'net_salary' =>
                    $net,

                'gross' =>
                    $gross,

                'ytd_gross' =>
                    $ytdGross,

                'ytd_paye' =>
                    $payroll['ytd_paye']
                    ?? null,

                'ytd_uif' =>
                    $payroll['ytd_uif']
                    ?? null,

                'ytd_deductions' =>
                    $ytdDeductions,

                'ytd_net' =>
                    $ytdNet,

            ],

        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | GENERATE RANGE
    |--------------------------------------------------------------------------
    */

    public function generateRange(
        Request $request
    ) {

        $validated =
            $request->validate([

                'staff_id' => [
                    'required',
                    'integer',
                    'exists:staff,id',
                ],

                'months' => [
                    'required',
                    'integer',
                    'min:1',
                    'max:24',
                ],

                'end_month' => [
                    'required',
                    'integer',
                    'min:1',
                    'max:12',
                ],

                'end_year' => [
                    'required',
                    'integer',
                    'min:2000',
                ],

            ]);


        /*
        |--------------------------------------------------------------------------
        | STAFF
        |--------------------------------------------------------------------------
        */

        $staff =
            Staff::findOrFail(
                $validated['staff_id']
            );


        /*
        |--------------------------------------------------------------------------
        | END DATE
        |--------------------------------------------------------------------------
        */

        $endDate =
            Carbon::create(
                $validated['end_year'],
                $validated['end_month'],
                1
            )
            ->startOfMonth();


        /*
        |--------------------------------------------------------------------------
        | START DATE
        |--------------------------------------------------------------------------
        */

        $startDate =
            $endDate
                ->copy()
                ->subMonths(
                    $validated['months']
                    -
                    1
                );


        /*
        |--------------------------------------------------------------------------
        | RESULTS
        |--------------------------------------------------------------------------
        */

        $results =
            [];


        /*
        |--------------------------------------------------------------------------
        | GENERATE EACH MONTH
        |--------------------------------------------------------------------------
        */

        for (
            $current =
                $startDate->copy();

            $current->lte(
                $endDate
            );

            $current->addMonth()
        ) {

            /*
            |--------------------------------------------------------------------------
            | FINANCIAL MONTH
            |--------------------------------------------------------------------------
            */

            $financialMonth =
                $this
                    ->getFinancialMonthNumber(
                        $current->month
                    );


            /*
            |--------------------------------------------------------------------------
            | EXISTING PAYSLIP
            |--------------------------------------------------------------------------
            */

            $existing =
                Payslip::where(
                    'staff_id',
                    $staff->id
                )
                ->where(
                    'year',
                    $current->year
                )
                ->where(
                    'month',
                    $financialMonth
                )
                ->first();


            if (
                $existing
            ) {

                $results[] = [

                    'month' =>
                        $current->month,

                    'year' =>
                        $current->year,

                    'status' =>
                        'skipped',

                    'payslip_id' =>
                        $existing->id,

                    'pdf_url' =>
                        $existing->pdf_path,

                    'filename' =>
                        $this->buildPayslipFilename(
                            $staff,
                            $current
                        ),

                    'message' =>
                        'Payslip already exists.',

                ];


                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | INTERNAL REQUEST
            |--------------------------------------------------------------------------
            */

            $generateRequest =
                Request::create(

                    '/api/payslips/generate',

                    'POST',

                    [

                        'staff_id' =>
                            $staff->id,

                        'month' =>
                            $current->month,

                        'year' =>
                            $current->year,

                    ]

                );


            /*
            |--------------------------------------------------------------------------
            | GENERATE
            |--------------------------------------------------------------------------
            */

            try {

                $response =
                    $this->generate(
                        $generateRequest
                    );


                $data =
                    $response->getData(
                        true
                    );

            } catch (
                \Throwable $e
            ) {

                $results[] = [

                    'month' =>
                        $current->month,

                    'year' =>
                        $current->year,

                    'status' =>
                        'skipped',

                    'message' =>
                        'Unable to generate payslip.',

                    'error' =>
                        $e->getMessage(),

                ];


                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | SUCCESS
            |--------------------------------------------------------------------------
            */

            if (
                $response->getStatusCode()
                === 200
            ) {

                $results[] = [

                    'month' =>
                        $current->month,

                    'year' =>
                        $current->year,

                    'status' =>
                        'generated',

                    'payslip_id' =>
                        $data['payslip']['id']
                        ?? null,

                    'pdf_url' =>
                        $data['pdf_url']
                        ?? null,

                    'filename' =>
                        $this->buildPayslipFilename(
                            $staff,
                            $current
                        ),

                ];

            } else {

                $results[] = [

                    'month' =>
                        $current->month,

                    'year' =>
                        $current->year,

                    'status' =>
                        'skipped',

                    'message' =>
                        $data['message']
                        ??
                        'Unable to generate payslip.',

                    'error' =>
                        $data['error']
                        ??
                        null,

                ];
            }
        }


        /*
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */

        return response()->json([

            'message' =>
                'Payslip range processing completed.',

            'staff' => [

                'id' =>
                    $staff->id,

                'employee_number' =>
                    $staff->employee_number,

                'name' =>
                    trim(
                        $staff->first_name
                        .
                        ' '
                        .
                        $staff->last_name
                    ),

            ],

            'months_requested' =>
                $validated['months'],

            'start_month' =>
                $startDate->format(
                    'Y-m'
                ),

            'end_month' =>
                $endDate->format(
                    'Y-m'
                ),

            'results' =>
                $results,

        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | GENERATE ALL STAFF - CURRENT MONTH
    |--------------------------------------------------------------------------
    */

    public function generateAllCurrentMonth()
    {

        /*
        |--------------------------------------------------------------------------
        | CURRENT MONTH
        |--------------------------------------------------------------------------
        */

        $currentMonth =
            now()
                ->startOfMonth();


        /*
        |--------------------------------------------------------------------------
        | STAFF
        |--------------------------------------------------------------------------
        */

        $staffMembers =
            Staff::orderBy(
                'last_name'
            )
            ->orderBy(
                'first_name'
            )
            ->get();


        /*
        |--------------------------------------------------------------------------
        | RESULTS
        |--------------------------------------------------------------------------
        */

        $results =
            [];


        foreach (
            $staffMembers
            as $staff
        ) {

            /*
            |--------------------------------------------------------------------------
            | FINANCIAL MONTH
            |--------------------------------------------------------------------------
            */

            $financialMonth =
                $this
                    ->getFinancialMonthNumber(
                        $currentMonth->month
                    );


            /*
            |--------------------------------------------------------------------------
            | EXISTING PAYSLIP
            |--------------------------------------------------------------------------
            */

            $existing =
                Payslip::where(
                    'staff_id',
                    $staff->id
                )
                ->where(
                    'year',
                    $currentMonth->year
                )
                ->where(
                    'month',
                    $financialMonth
                )
                ->first();


            if (
                $existing
            ) {

                $results[] =
                    $this->buildBulkResult(

                        $staff,

                        $currentMonth,

                        'skipped',

                        $existing->id,

                        $existing->pdf_path,

                        'Payslip already exists.'

                    );


                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | INTERNAL REQUEST
            |--------------------------------------------------------------------------
            */

            $generateRequest =
                Request::create(

                    '/api/payslips/generate',

                    'POST',

                    [

                        'staff_id' =>
                            $staff->id,

                        'month' =>
                            $currentMonth->month,

                        'year' =>
                            $currentMonth->year,

                    ]

                );


            /*
            |--------------------------------------------------------------------------
            | GENERATE
            |--------------------------------------------------------------------------
            */

            try {

                $response =
                    $this->generate(
                        $generateRequest
                    );


                $data =
                    $response->getData(
                        true
                    );

            } catch (
                \Throwable $e
            ) {

                $results[] =
                    $this->buildBulkResult(

                        $staff,

                        $currentMonth,

                        'skipped',

                        null,

                        null,

                        $e->getMessage()

                    );


                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | SUCCESS / FAILURE
            |--------------------------------------------------------------------------
            */

            if (
                $response->getStatusCode()
                === 200
            ) {

                $results[] =
                    $this->buildBulkResult(

                        $staff,

                        $currentMonth,

                        'generated',

                        $data['payslip']['id']
                        ?? null,

                        $data['pdf_url']
                        ?? null,

                        null

                    );

            } else {

                $results[] =
                    $this->buildBulkResult(

                        $staff,

                        $currentMonth,

                        'skipped',

                        null,

                        null,

                        $data['message']
                        ??
                        'Unable to generate payslip.'

                    );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | COUNTS
        |--------------------------------------------------------------------------
        */

        $generated =
            collect(
                $results
            )
            ->where(
                'status',
                'generated'
            )
            ->count();


        $skipped =
            collect(
                $results
            )
            ->where(
                'status',
                'skipped'
            )
            ->count();


        /*
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */

        return response()->json([

            'message' =>
                'Current month payslip generation completed.',

            'month' =>
                $currentMonth->format(
                    'F Y'
                ),

            'month_number' =>
                $currentMonth->month,

            'year' =>
                $currentMonth->year,

            'total_staff' =>
                count(
                    $staffMembers
                ),

            'generated' =>
                $generated,

            'skipped' =>
                $skipped,

            'results' =>
                $results,

        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | FINANCIAL MONTH NUMBER
    |--------------------------------------------------------------------------
    */

    protected function getFinancialMonthNumber(
        int $calendarMonth
    ): int {

        $financialYearStartMonth =
            3;


        return
            $calendarMonth >=
            $financialYearStartMonth

                ? $calendarMonth
                    -
                    $financialYearStartMonth
                    +
                    1

                : $calendarMonth
                    +
                    (
                        12
                        -
                        $financialYearStartMonth
                        +
                        1
                    );
    }


    /*
    |--------------------------------------------------------------------------
    | LEAVE DATA
    |--------------------------------------------------------------------------
    */

    protected function getLeaveDataForPayslip(
        Staff $staff,
        Carbon $asOfDate,
        Carbon $periodStart,
        Carbon $periodEnd
    ): array {

        /*
        |--------------------------------------------------------------------------
        | ACTIVE LEAVE TYPES
        |--------------------------------------------------------------------------
        */

        $leaveTypes =
            LeaveType::where(
                'is_active',
                true
            )
            ->orderBy(
                'id'
            )
            ->get();


        /*
        |--------------------------------------------------------------------------
        | LEAVE BALANCES
        |--------------------------------------------------------------------------
        */

        $balances =
            [];


        foreach (
            $leaveTypes
            as $leaveType
        ) {

            $balance =
                $this
                    ->leaveService
                    ->getLeaveBalance(
                        $staff,
                        $leaveType,
                        $asOfDate
                    );


            $balance['leave_type_id'] =
                $leaveType->id;


            $balance['leave_type'] =
                $leaveType->name;


            $balance['leave_code'] =
                $leaveType->code;


            $balances[] =
                $balance;
        }


        /*
        |--------------------------------------------------------------------------
        | APPROVED LEAVE DURING PAY PERIOD
        |--------------------------------------------------------------------------
        */

        $approvedLeaves =
            StaffLeave::with([
                'leaveType',
            ])
            ->where(
                'staff_id',
                $staff->id
            )
            ->where(
                'status',
                StaffLeave::STATUS_APPROVED
            )
            ->whereDate(
                'start_date',
                '<=',
                $periodEnd
            )
            ->whereDate(
                'end_date',
                '>=',
                $periodStart
            )
            ->orderBy(
                'start_date'
            )
            ->get();


        /*
        |--------------------------------------------------------------------------
        | LEAVE TAKEN
        |--------------------------------------------------------------------------
        */

        $taken =
            [];


        $totalTaken =
            0.00;


        foreach (
            $approvedLeaves
            as $leave
        ) {

            $leaveStart =
                Carbon::parse(
                    $leave->start_date
                )
                ->startOfDay();


            $leaveEnd =
                Carbon::parse(
                    $leave->end_date
                )
                ->startOfDay();


            /*
            |--------------------------------------------------------------------------
            | OVERLAP START
            |--------------------------------------------------------------------------
            */

            $overlapStart =
                $leaveStart->greaterThan(
                    $periodStart
                )

                    ? $leaveStart

                    : $periodStart
                        ->copy();


            /*
            |--------------------------------------------------------------------------
            | OVERLAP END
            |--------------------------------------------------------------------------
            */

            $overlapEnd =
                $leaveEnd->lessThan(
                    $periodEnd
                )

                    ? $leaveEnd

                    : $periodEnd
                        ->copy();


            if (
                $overlapStart->gt(
                    $overlapEnd
                )
            ) {

                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | FULL LEAVE RECORD INSIDE PERIOD
            |--------------------------------------------------------------------------
            */

            if (
                $leaveStart->gte(
                    $periodStart
                )
                &&
                $leaveEnd->lte(
                    $periodEnd
                )
            ) {

                $daysTaken =
                    (float)
                    $leave->days_taken;

            } else {

                /*
                |--------------------------------------------------------------------------
                | PARTIAL OVERLAP
                |--------------------------------------------------------------------------
                */

                $daysTaken =
                    $this
                        ->leaveService
                        ->calculateLeaveDays(

                            $staff,

                            $overlapStart,

                            $overlapEnd

                        );


                if (
                    $leave->duration_type
                    === 'half'
                ) {

                    $daysTaken *=
                        0.5;
                }
            }


            /*
            |--------------------------------------------------------------------------
            | ROUND
            |--------------------------------------------------------------------------
            */

            $daysTaken =
                round(
                    (float)
                    $daysTaken,
                    2
                );


            if (
                $daysTaken <= 0
            ) {

                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | TOTAL
            |--------------------------------------------------------------------------
            */

            $totalTaken +=
                $daysTaken;


            /*
            |--------------------------------------------------------------------------
            | RECORD
            |--------------------------------------------------------------------------
            */

            $taken[] = [

                'id' =>
                    $leave->id,

                'leave_type_id' =>
                    $leave->leave_type_id,

                'leave_type' =>
                    $leave->leaveType
                        ? $leave->leaveType->name
                        : 'Unknown',

                'leave_code' =>
                    $leave->leaveType
                        ? $leave->leaveType->code
                        : null,

                'start_date' =>
                    $leave->start_date,

                'end_date' =>
                    $leave->end_date,

                'days_taken' =>
                    $daysTaken,

                'duration_type' =>
                    $leave->duration_type
                    ??
                    'full',

                'half_day_period' =>
                    $leave->half_day_period,

                'reason' =>
                    $leave->reason,

            ];
        }


        /*
        |--------------------------------------------------------------------------
        | RESULT
        |--------------------------------------------------------------------------
        */

        return [

            'balances' =>
                $balances,

            'taken' =>
                $taken,

            'total_taken' =>
                round(
                    $totalTaken,
                    2
                ),

        ];
    }


    /*
    |--------------------------------------------------------------------------
    | GENERATE PDF
    |--------------------------------------------------------------------------
    */

    private function generatePayslipPdfContent(
        $staff,
        $payslip,
        $loanDeduction,
        $loans,
        $payDate,
        $periodEnding,
        $leaveBalances,
        $leaveTakenForPeriod,
        $totalLeaveTakenForPeriod
    ) {

        $pdf =
            Pdf::loadView(

                'pdf.payslip',

                [

                    'staff' =>
                        $staff,

                    'payslip' =>
                        $payslip,

                    'loans' =>
                        $loans,

                    'loanDeductions' =>
                        $loanDeduction,

                    'pay_date' =>
                        $payDate,

                    'period_ending' =>
                        $periodEnding,

                    'street' =>
                        $staff->street,

                    'complex' =>
                        $staff->complex,

                    'suburb' =>
                        $staff->suburb,

                    'postal_code' =>
                        $staff->postal_code,

                    'leaveBalances' =>
                        $leaveBalances,

                    'leaveTakenForPeriod' =>
                        $leaveTakenForPeriod,

                    'totalLeaveTakenForPeriod' =>
                        $totalLeaveTakenForPeriod,

                ]

            );


        return $pdf->output();
    }


    /*
    |--------------------------------------------------------------------------
    | GET PAYSLIPS FOR STAFF
    |--------------------------------------------------------------------------
    */

    public function index(
        int $staffId
    ) {

        $payslips =
            Payslip::where(
                'staff_id',
                $staffId
            )
            ->orderByDesc(
                'pay_date'
            )
            ->get();


        return response()->json(
            $payslips
        );
    }


    /*
    |--------------------------------------------------------------------------
    | DOWNLOAD
    |--------------------------------------------------------------------------
    */

    public function download(
        int $id
    ) {

        /*
        |--------------------------------------------------------------------------
        | PAYSLIP
        |--------------------------------------------------------------------------
        */

        $payslip =
            Payslip::findOrFail(
                $id
            );


        /*
        |--------------------------------------------------------------------------
        | STANDARD PUBLIC PDF
        |--------------------------------------------------------------------------
        */

        $publicPath =
            public_path(
                "payslips/{$id}.pdf"
            );


        if (
            file_exists(
                $publicPath
            )
        ) {

            return response()->download(
                $publicPath
            );
        }


        /*
        |--------------------------------------------------------------------------
        | STORED PDF URL
        |--------------------------------------------------------------------------
        */

        if (
            $payslip->pdf_path
        ) {

            $parsedPath =
                parse_url(
                    $payslip->pdf_path,
                    PHP_URL_PATH
                );


            if (
                $parsedPath
            ) {

                $fullPath =
                    public_path(
                        ltrim(
                            $parsedPath,
                            '/'
                        )
                    );


                if (
                    file_exists(
                        $fullPath
                    )
                ) {

                    return response()->download(
                        $fullPath
                    );
                }
            }
        }


        return response()->json([

            'message' =>
                'Payslip PDF not found.',

        ], 404);
    }


    /*
    |--------------------------------------------------------------------------
    | BUILD PAYSLIP FILENAME
    |--------------------------------------------------------------------------
    */

    protected function buildPayslipFilename(
        Staff $staff,
        Carbon $date
    ): string {

        /*
        |--------------------------------------------------------------------------
        | EMPLOYEE NAME
        |--------------------------------------------------------------------------
        */

        $employeeName =
            trim(
                $staff->first_name
                .
                '_'
                .
                $staff->last_name
            );


        $employeeName =
            preg_replace(
                '/[^A-Za-z0-9_-]+/',
                '_',
                $employeeName
            );


        /*
        |--------------------------------------------------------------------------
        | EMPLOYEE NUMBER
        |--------------------------------------------------------------------------
        */

        $employeeNumber =
            '';


        if (
            $staff->employee_number
        ) {

            $employeeNumber =
                preg_replace(
                    '/[^A-Za-z0-9_-]+/',
                    '_',
                    $staff->employee_number
                );
        }


        /*
        |--------------------------------------------------------------------------
        | FILENAME
        |--------------------------------------------------------------------------
        */

        if (
            $employeeNumber
        ) {

            return
                $employeeName
                .
                '_'
                .
                $employeeNumber
                .
                '_'
                .
                $date->format(
                    'F_Y'
                )
                .
                '.pdf';
        }


        return
            $employeeName
            .
            '_'
            .
            $date->format(
                'F_Y'
            )
            .
            '.pdf';
    }


    /*
    |--------------------------------------------------------------------------
    | BUILD BULK RESULT
    |--------------------------------------------------------------------------
    */

    protected function buildBulkResult(
        Staff $staff,
        Carbon $month,
        string $status,
        ?int $payslipId,
        ?string $pdfUrl,
        ?string $message
    ): array {

        return [

            'staff_id' =>
                $staff->id,

            'employee_number' =>
                $staff->employee_number,

            'employee_name' =>
                trim(
                    $staff->first_name
                    .
                    ' '
                    .
                    $staff->last_name
                ),

            'month' =>
                $month->month,

            'year' =>
                $month->year,

            'status' =>
                $status,

            'payslip_id' =>
                $payslipId,

            'pdf_url' =>
                $pdfUrl,

            'filename' =>
                $this->buildPayslipFilename(
                    $staff,
                    $month
                ),

            'message' =>
                $message,

        ];
    }
}
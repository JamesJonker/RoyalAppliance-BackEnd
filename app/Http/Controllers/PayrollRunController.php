<?php

namespace App\Http\Controllers;

use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Staff;
use App\Models\StatutorySetting;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class PayrollRunController extends Controller
{
    protected PayrollService $payrollService;

    public function __construct(PayrollService $payrollService)
    {
        $this->payrollService = $payrollService;
    }


    /*
    |--------------------------------------------------------------------------
    | PAYROLL PERIODS
    |--------------------------------------------------------------------------
    */

    public function periods(): JsonResponse
    {
        try {

            $periods = PayrollPeriod::query()
                ->orderByDesc('id')
                ->get();

            return response()->json([
                'data' => $periods,
            ]);

        } catch (Throwable $e) {

            return response()->json([
                'message' =>
                    'Unable to load payroll periods.',

                'error' =>
                    $e->getMessage(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | CREATE PAYROLL PERIOD
    |--------------------------------------------------------------------------
    */

    public function storePeriod(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'calendar_month' => ['required', 'integer', 'between:1,12'],
            'calendar_year' => ['required', 'integer', 'between:2000,2100'],
            'pay_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            return DB::transaction(function () use ($validated) {
                $calendarMonth = (int) $validated['calendar_month'];
                $calendarYear = (int) $validated['calendar_year'];

                $existingPeriod = PayrollPeriod::query()
                    ->where('calendar_month', $calendarMonth)
                    ->where('calendar_year', $calendarYear)
                    ->lockForUpdate()
                    ->first();

                if ($existingPeriod) {
                    return response()->json([
                        'message' => 'A payroll period already exists for this month and year.',
                        'data' => $existingPeriod,
                    ], 422);
                }

                if ($calendarMonth >= 3) {
                    $financialYearStart = $calendarYear;
                    $financialMonth = $calendarMonth - 2;
                } else {
                    $financialYearStart = $calendarYear - 1;
                    $financialMonth = $calendarMonth + 10;
                }

                $periodStart = Carbon::create($calendarYear, $calendarMonth, 1)->startOfDay();
                $periodEnd = $periodStart->copy()->endOfMonth()->startOfDay();

                $payDate = !empty($validated['pay_date'])
                    ? Carbon::parse($validated['pay_date'])->startOfDay()
                    : Carbon::create($calendarYear, $calendarMonth, 26)->startOfDay();

                if ($payDate->lt($periodStart) || $payDate->gt($periodEnd)) {
                    return response()->json([
                        'message' => 'The pay date must fall inside the selected payroll month.',
                    ], 422);
                }

                $period = new PayrollPeriod();
                $period->financial_year_start = $financialYearStart;
                $period->financial_month = $financialMonth;
                $period->calendar_month = $calendarMonth;
                $period->calendar_year = $calendarYear;
                $period->period_start = $periodStart->toDateString();
                $period->period_end = $periodEnd->toDateString();
                $period->pay_date = $payDate->toDateString();
                $period->status = 'open';
                $period->notes = $validated['notes'] ?? null;
                $period->save();

                return response()->json([
                    'message' => 'Payroll period created successfully.',
                    'data' => $period->fresh(),
                ], 201);
            });
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Unable to create payroll period.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ELIGIBLE STAFF FOR A PAYROLL PERIOD
    |--------------------------------------------------------------------------
    */

    public function eligibleStaff(PayrollPeriod $payrollPeriod): JsonResponse
    {
        if (!$payrollPeriod->pay_date) {
            return response()->json([
                'message' => 'The payroll period does not have a pay date.',
            ], 422);
        }

        $payDate = Carbon::parse($payrollPeriod->pay_date)->toDateString();

        $staff = Staff::query()
            ->where(function ($query) use ($payDate) {
                $query->whereNull('start_date')
                    ->orWhereDate('start_date', '<=', $payDate);
            })
            ->orderBy('branch')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get([
                'id',
                'employee_number',
                'first_name',
                'last_name',
                'branch',
                'position',
                'employment_type',
                'start_date',
            ]);

        return response()->json([
            'data' => $staff,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = PayrollRun::with([
            'payrollPeriod',
        ])
            ->orderByDesc('id');

        if ($request->filled('payroll_period_id')) {
            $query->where(
                'payroll_period_id',
                $request->payroll_period_id
            );
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->status
            );
        }

        $perPage = (int) $request->get('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $runs = $query->paginate($perPage);

        return response()->json($runs);
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE PAYROLL RUN
    |--------------------------------------------------------------------------
    */

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payroll_period_id' => [
                'required',
                'integer',
                'exists:payroll_periods,id',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'staff_ids' => [
                'required',
                'array',
                'min:1',
            ],

            'staff_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:staff,id',
            ],
        ]);

        return DB::transaction(function () use ($validated) {

            $period = PayrollPeriod::lockForUpdate()
                ->findOrFail(
                    $validated['payroll_period_id']
                );

            if ($period->isLocked()) {
                return response()->json([
                    'message' =>
                        'This payroll period is already locked and cannot accept a new payroll run.',
                ], 422);
            }

            if ($period->status === 'closed') {
                return response()->json([
                    'message' =>
                        'This payroll period is closed.',
                ], 422);
            }

            $lastRunNumber = PayrollRun::where(
                'payroll_period_id',
                $period->id
            )->max('run_number');

            $runNumber = ((int) $lastRunNumber) + 1;

            $run = PayrollRun::create([
                'payroll_period_id' => $period->id,
                'run_number' => $runNumber,
                'status' => 'draft',
                'notes' => $validated['notes'] ?? null,
            ]);

            $run->staffMembers()->sync(
                array_values($validated['staff_ids'])
            );

            if ($period->status === 'open') {
                $period->update([
                    'status' => 'processing',
                ]);
            }

            $run->load([
                'payrollPeriod',
                'staffMembers',
            ]);

            return response()->json([
                'message' =>
                    'Payroll run created successfully.',

                'data' => $run,
            ], 201);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */

    public function show(PayrollRun $payrollRun): JsonResponse
    {
        $payrollRun->load([
            'payrollPeriod',
            'payrollItems',
            'staffMembers',
        ]);

        return response()->json([
            'data' => $payrollRun,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        PayrollRun $payrollRun
    ): JsonResponse {

        if ($payrollRun->isLocked()) {
            return response()->json([
                'message' =>
                    'This payroll run is locked and cannot be changed.',
            ], 422);
        }

        $validated = $request->validate([
            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ]);

        $payrollRun->update([
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'message' =>
                'Payroll run updated successfully.',

            'data' => $payrollRun->fresh([
                'payrollPeriod',
            ]),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CALCULATE PAYROLL RUN
    |--------------------------------------------------------------------------
    */

    public function calculate(
        PayrollRun $payrollRun
    ): JsonResponse {

        try {

            return DB::transaction(function () use ($payrollRun) {

                /*
                |--------------------------------------------------------------------------
                | Lock payroll run
                |--------------------------------------------------------------------------
                */

                $payrollRun = PayrollRun::lockForUpdate()
                    ->findOrFail($payrollRun->id);

                /*
                |--------------------------------------------------------------------------
                | Check status
                |--------------------------------------------------------------------------
                */

                if (!$payrollRun->canCalculate()) {
                    return response()->json([
                        'message' =>
                            'This payroll run cannot be calculated in its current status.',
                    ], 422);
                }

                /*
                |--------------------------------------------------------------------------
                | Load payroll period
                |--------------------------------------------------------------------------
                */

                $period = PayrollPeriod::lockForUpdate()
                    ->findOrFail(
                        $payrollRun->payroll_period_id
                    );

                if ($period->isLocked()) {
                    return response()->json([
                        'message' =>
                            'The payroll period is already locked and cannot be recalculated.',
                    ], 422);
                }

                /*
                |--------------------------------------------------------------------------
                | Pay date is required
                |--------------------------------------------------------------------------
                */

                if (!$period->pay_date) {
                    return response()->json([
                        'message' =>
                            'The payroll period does not have a pay date.',
                    ], 422);
                }

                $payDate = Carbon::parse(
                    $period->pay_date
                );

                /*
                |--------------------------------------------------------------------------
                | Do not recalculate when a payment run exists
                |--------------------------------------------------------------------------
                */

                if ($payrollRun->paymentRuns()->exists()) {
                    return response()->json([
                        'message' =>
                            'A payment run already exists for this payroll run. The payroll cannot be recalculated.',
                    ], 422);
                }

                /*
                |--------------------------------------------------------------------------
                | Mark as calculating
                |--------------------------------------------------------------------------
                */

                $payrollRun->update([
                    'status' => 'calculating',
                    'calculation_started_at' => now(),
                    'calculation_completed_at' => null,
                ]);

                /*
                |--------------------------------------------------------------------------
                | Delete previous payroll items
                |--------------------------------------------------------------------------
                |
                | A draft/calculating run may be recalculated.
                |
                */

                PayrollItem::where(
                    'payroll_run_id',
                    $payrollRun->id
                )->delete();

                /*
                |--------------------------------------------------------------------------
                | Reset payroll totals
                |--------------------------------------------------------------------------
                */

                $payrollRun->resetTotals();

                /*
                |--------------------------------------------------------------------------
                | Get staff
                |--------------------------------------------------------------------------
                |
                | Your Staff model does not currently contain:
                |
                | - termination_date
                | | - employment_status
                |
                | Therefore we only use start_date to determine whether
                | the employee existed on the payroll date.
                |
                */

                $selectedStaffIds = $payrollRun
                    ->staffMembers()
                    ->pluck('staff.id');

                if ($selectedStaffIds->isEmpty()) {
                    return response()->json([
                        'message' =>
                            'No staff members have been selected for this payroll run.',
                    ], 422);
                }

                $staffMembers = Staff::query()
                    ->with([
                        'workSchedule.days',
                    ])
                    ->whereIn('id', $selectedStaffIds)
                    ->where(function ($query) use ($payDate) {

                        $query->whereNull('start_date')
                            ->orWhereDate(
                                'start_date',
                                '<=',
                                $payDate->toDateString()
                            );
                    })
                    ->orderBy('employee_number')
                    ->get();

                /*
                |--------------------------------------------------------------------------
                | No employees
                |--------------------------------------------------------------------------
                */

                if ($staffMembers->isEmpty()) {

                    $payrollRun->update([
                        'status' => 'calculated',
                        'employee_count' => 0,
                        'total_gross' => 0,
                        'total_paye' => 0,
                        'total_employee_uif' => 0,
                        'total_employer_uif' => 0,
                        'total_sdl' => 0,
                        'total_deductions' => 0,
                        'total_net' => 0,
                        'total_employer_cost' => 0,
                        'calculation_completed_at' => now(),
                    ]);

                    return response()->json([
                        'message' =>
                            'Payroll run calculated, but no employees were found for this pay date.',

                        'data' => $payrollRun->fresh([
                            'payrollPeriod',
                            'payrollItems',
                        ]),
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Get UIF ceiling
                |--------------------------------------------------------------------------
                */

                $uifCeiling =
                    (float) StatutorySetting::getDecimal(
                        'uif_monthly_ceiling',
                        $payDate
                    );

                /*
                |--------------------------------------------------------------------------
                | Payroll totals
                |--------------------------------------------------------------------------
                */

                $employeeCount = 0;

                $totalGross = 0.00;
                $totalPaye = 0.00;
                $totalEmployeeUif = 0.00;
                $totalEmployerUif = 0.00;
                $totalSdl = 0.00;
                $totalDeductions = 0.00;
                $totalNet = 0.00;
                $totalEmployerCost = 0.00;

                /*
                |--------------------------------------------------------------------------
                | Calculate every employee
                |--------------------------------------------------------------------------
                */

                foreach ($staffMembers as $staff) {

                    /*
                    |--------------------------------------------------------------------------
                    | Validate employee
                    |--------------------------------------------------------------------------
                    */

                    $validation =
                        $this->payrollService
                            ->validateEmployeeForPayroll(
                                $staff,
                                $payDate
                            );

                    /*
                    |--------------------------------------------------------------------------
                    | Invalid employee
                    |--------------------------------------------------------------------------
                    |
                    | Create an excluded payroll item so the problem can
                    | be seen and corrected instead of losing the employee.
                    |
                    */

                    if (!$validation['valid']) {

                        PayrollItem::create([

                            'payroll_run_id' =>
                                $payrollRun->id,

                            'staff_id' =>
                                $staff->id,

                            /*
                            |--------------------------------------------------------------------------
                            | Employee Snapshot
                            |--------------------------------------------------------------------------
                            */

                            'employee_number' =>
                                $staff->employee_number,

                            'first_name' =>
                                $staff->first_name,

                            'last_name' =>
                                $staff->last_name,

                            /*
                            | Staff model uses Id_number.
                            */
                            'id_number' =>
                                $staff->Id_number,

                            /*
                            | Employee tax number snapshot.
                            */
                            'tax_number' =>
                                $staff->tax_number,

                            'employment_type' =>
                                $staff->employment_type,

                            'position' =>
                                $staff->position,

                            'branch' =>
                                $staff->branch,

                            /*
                            |--------------------------------------------------------------------------
                            | Employment
                            |--------------------------------------------------------------------------
                            */

                            'start_date' =>
                                $staff->start_date,

                            'termination_date' => null,

                            'termination_reason' => null,

                            /*
                            |--------------------------------------------------------------------------
                            | Hours
                            |--------------------------------------------------------------------------
                            */

                            'hours_worked' => 0,
                            'ordinary_hours' => 0,
                            'overtime_hours' => 0,

                            /*
                            |--------------------------------------------------------------------------
                            | Earnings
                            |--------------------------------------------------------------------------
                            */

                            'basic_salary' => 0,
                            'overtime_amount' => 0,
                            'allowances' => 0,
                            'bonus' => 0,
                            'commission' => 0,
                            'other_earnings' => 0,
                            'gross_salary' => 0,

                            /*
                            |--------------------------------------------------------------------------
                            | UIF
                            |--------------------------------------------------------------------------
                            */

                            'uif_liable_amount' => 0,
                            'employee_uif' => 0,
                            'employer_uif' => 0,
                            'uif_liable' => false,
                            'uif_exclusion_code' => null,

                            /*
                            |--------------------------------------------------------------------------
                            | PAYE
                            |--------------------------------------------------------------------------
                            */

                            'taxable_income' => 0,
                            'paye' => 0,

                            /*
                            |--------------------------------------------------------------------------
                            | SDL
                            |--------------------------------------------------------------------------
                            */

                            'sdl_liable_amount' => 0,
                            'sdl' => 0,

                            /*
                            |--------------------------------------------------------------------------
                            | Deductions
                            |--------------------------------------------------------------------------
                            */

                            'loan_deduction' => 0,
                            'leave_deduction' => 0,
                            'other_deductions' => 0,
                            'total_deductions' => 0,

                            /*
                            |--------------------------------------------------------------------------
                            | Net
                            |--------------------------------------------------------------------------
                            */

                            'net_salary' => 0,
                            'employer_cost' => 0,

                            /*
                            |--------------------------------------------------------------------------
                            | Leave
                            |--------------------------------------------------------------------------
                            */

                            'annual_leave_taken' => 0,
                            'annual_leave_balance' => 0,

                            'sick_leave_taken' => 0,
                            'sick_leave_balance' => 0,

                            'family_leave_taken' => 0,
                            'family_leave_balance' => 0,

                            /*
                            |--------------------------------------------------------------------------
                            | Calculation Snapshot
                            |--------------------------------------------------------------------------
                            */

                            'calculation_data' => [
                                'pay_date' =>
                                    $payDate->toDateString(),

                                'errors' =>
                                    $validation['errors'],
                            ],

                            /*
                            |--------------------------------------------------------------------------
                            | Status
                            |--------------------------------------------------------------------------
                            */

                            'status' => 'excluded',

                            'exclusion_reason' =>
                                implode(
                                    ' ',
                                    $validation['errors']
                                ),
                        ]);

                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Calculate employee
                    |--------------------------------------------------------------------------
                    |
                    | PayrollService remains the single source for:
                    |
                    | PAYE
                    | UIF
                    | SDL
                    | Loan deductions
                    | Net salary
                    |
                    */

                    $calculation =
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
                    | Extract calculation values
                    |--------------------------------------------------------------------------
                    */

                    $basicSalary =
                        (float) $calculation['basic_salary'];

                    $overtime =
                        (float) $calculation['overtime'];

                    $allowances =
                        (float) $calculation['allowances'];

                    $bonuses =
                        (float) $calculation['bonuses'];

                    $gross =
                        (float) $calculation['gross'];

                    $paye =
                        (float) $calculation['paye'];

                    $employeeUif =
                        (float) $calculation['uif_employee'];

                    $employerUif =
                        (float) $calculation['uif_employer'];

                    $sdl =
                        (float) $calculation['sdl'];

                    $loanDeductions =
                        (float) $calculation['loan_deductions'];

                    $otherDeductions =
                        (float) $calculation['other_deductions'];

                    $totalEmployeeDeductions =
                        (float) $calculation['total_deductions'];

                    $netSalary =
                        (float) $calculation['net_salary'];

                    $employerCost =
                        (float) $calculation['employer_cost'];

                    /*
                    |--------------------------------------------------------------------------
                    | UIF liable amount
                    |--------------------------------------------------------------------------
                    */

                    $uifLiableAmount = round(
                        min(
                            max($gross, 0),
                            $uifCeiling
                        ),
                        2
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Create payroll item
                    |--------------------------------------------------------------------------
                    */

                    PayrollItem::create([

                        'payroll_run_id' =>
                            $payrollRun->id,

                        'staff_id' =>
                            $staff->id,

                        /*
                        |--------------------------------------------------------------------------
                        | Employee Snapshot
                        |--------------------------------------------------------------------------
                        */

                        'employee_number' =>
                            $staff->employee_number,

                        'first_name' =>
                            $staff->first_name,

                        'last_name' =>
                            $staff->last_name,

                        /*
                        | Staff model uses Id_number.
                        */
                        'id_number' =>
                            $staff->Id_number,

                        /*
                        | Employee tax number snapshot.
                        */
                        'tax_number' =>
                            $staff->tax_number,

                        'employment_type' =>
                            $staff->employment_type,

                        'position' =>
                            $staff->position,

                        'branch' =>
                            $staff->branch,

                        /*
                        |--------------------------------------------------------------------------
                        | Employment
                        |--------------------------------------------------------------------------
                        */

                        'start_date' =>
                            $staff->start_date,

                        'termination_date' => null,

                        'termination_reason' => null,

                        /*
                        |--------------------------------------------------------------------------
                        | Hours
                        |--------------------------------------------------------------------------
                        |
                        | Current PayrollService works from monthly salary.
                        | It does not calculate working hours.
                        |
                        */

                        'hours_worked' => 0,
                        'ordinary_hours' => 0,
                        'overtime_hours' => 0,

                        /*
                        |--------------------------------------------------------------------------
                        | Earnings
                        |--------------------------------------------------------------------------
                        */

                        'basic_salary' =>
                            round($basicSalary, 2),

                        'overtime_amount' =>
                            round($overtime, 2),

                        'allowances' =>
                            round($allowances, 2),

                        'bonus' =>
                            round($bonuses, 2),

                        'commission' => 0,

                        'other_earnings' => 0,

                        'gross_salary' =>
                            round($gross, 2),

                        /*
                        |--------------------------------------------------------------------------
                        | UIF
                        |--------------------------------------------------------------------------
                        */

                        'uif_liable_amount' =>
                            $uifLiableAmount,

                        'employee_uif' =>
                            round($employeeUif, 2),

                        'employer_uif' =>
                            round($employerUif, 2),

                        'uif_liable' =>
                            $employeeUif > 0,

                        'uif_exclusion_code' => null,

                        /*
                        |--------------------------------------------------------------------------
                        | PAYE
                        |--------------------------------------------------------------------------
                        */

                        'taxable_income' =>
                            round($gross, 2),

                        'paye' =>
                            round($paye, 2),

                        /*
                        |--------------------------------------------------------------------------
                        | SDL
                        |--------------------------------------------------------------------------
                        */

                        'sdl_liable_amount' =>
                            round($gross, 2),

                        'sdl' =>
                            round($sdl, 2),

                        /*
                        |--------------------------------------------------------------------------
                        | Deductions
                        |--------------------------------------------------------------------------
                        */

                        'loan_deduction' =>
                            round($loanDeductions, 2),

                        /*
                        | Leave deduction is currently not calculated
                        | by PayrollService.
                        */
                        'leave_deduction' => 0,

                        'other_deductions' =>
                            round($otherDeductions, 2),

                        'total_deductions' =>
                            round($totalEmployeeDeductions, 2),

                        /*
                        |--------------------------------------------------------------------------
                        | Net
                        |--------------------------------------------------------------------------
                        */

                        'net_salary' =>
                            round($netSalary, 2),

                        'employer_cost' =>
                            round($employerCost, 2),

                        /*
                        |--------------------------------------------------------------------------
                        | Leave Snapshot
                        |--------------------------------------------------------------------------
                        */

                        'annual_leave_taken' => 0,
                        'annual_leave_balance' => 0,

                        'sick_leave_taken' => 0,
                        'sick_leave_balance' => 0,

                        'family_leave_taken' => 0,
                        'family_leave_balance' => 0,

                        /*
                        |--------------------------------------------------------------------------
                        | Calculation Snapshot
                        |--------------------------------------------------------------------------
                        */

                        'calculation_data' => [
                            'pay_date' =>
                                $payDate->toDateString(),

                            'tax_year' =>
                                $calculation['tax_year'] ?? null,

                            'age' =>
                                $calculation['age'] ?? null,

                            'ytd_gross' =>
                                $calculation['ytd_gross'] ?? 0,

                            'ytd_paye' =>
                                $calculation['ytd_paye'] ?? 0,

                            'ytd_uif' =>
                                $calculation['ytd_uif'] ?? 0,

                            'ytd_deductions' =>
                                $calculation['ytd_deductions'] ?? 0,

                            'ytd_net' =>
                                $calculation['ytd_net'] ?? 0,
                        ],

                        /*
                        |--------------------------------------------------------------------------
                        | Status
                        |--------------------------------------------------------------------------
                        */

                        'status' => 'calculated',

                        'exclusion_reason' => null,
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | Add employee totals
                    |--------------------------------------------------------------------------
                    */

                    $employeeCount++;

                    $totalGross += $gross;
                    $totalPaye += $paye;
                    $totalEmployeeUif += $employeeUif;
                    $totalEmployerUif += $employerUif;
                    $totalSdl += $sdl;
                    $totalDeductions += $totalEmployeeDeductions;
                    $totalNet += $netSalary;
                    $totalEmployerCost += $employerCost;
                }

                /*
                |--------------------------------------------------------------------------
                | Update Payroll Run
                |--------------------------------------------------------------------------
                */

                $payrollRun->update([

                    'status' =>
                        'calculated',

                    'employee_count' =>
                        $employeeCount,

                    'total_gross' =>
                        round($totalGross, 2),

                    'total_paye' =>
                        round($totalPaye, 2),

                    'total_employee_uif' =>
                        round($totalEmployeeUif, 2),

                    'total_employer_uif' =>
                        round($totalEmployerUif, 2),

                    'total_sdl' =>
                        round($totalSdl, 2),

                    'total_deductions' =>
                        round($totalDeductions, 2),

                    'total_net' =>
                        round($totalNet, 2),

                    'total_employer_cost' =>
                        round($totalEmployerCost, 2),

                    'calculation_completed_at' =>
                        now(),
                ]);

                /*
                |--------------------------------------------------------------------------
                | Load results
                |--------------------------------------------------------------------------
                */

                $payrollRun->load([
                    'payrollPeriod',
                    'payrollItems',
                ]);

                /*
                |--------------------------------------------------------------------------
                | Return result
                |--------------------------------------------------------------------------
                */

                return response()->json([

                    'message' =>
                        'Payroll run calculated successfully.',

                    'data' =>
                        $payrollRun,

                    'summary' => [

                        'employees_calculated' =>
                            $employeeCount,

                        'employees_excluded' =>
                            $payrollRun->payrollItems
                                ->where(
                                    'status',
                                    'excluded'
                                )
                                ->count(),

                        'total_gross' =>
                            round($totalGross, 2),

                        'total_paye' =>
                            round($totalPaye, 2),

                        'total_employee_uif' =>
                            round($totalEmployeeUif, 2),

                        'total_employer_uif' =>
                            round($totalEmployerUif, 2),

                        'total_sdl' =>
                            round($totalSdl, 2),

                        'total_deductions' =>
                            round($totalDeductions, 2),

                        'total_net' =>
                            round($totalNet, 2),

                        'total_employer_cost' =>
                            round($totalEmployerCost, 2),
                    ],
                ]);
            });

        } catch (Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | Return failed calculation to draft
            |--------------------------------------------------------------------------
            */

            try {

                $payrollRun->refresh();

                if ($payrollRun->status === 'calculating') {

                    $payrollRun->update([
                        'status' => 'draft',
                        'calculation_completed_at' => null,
                    ]);
                }

            } catch (Throwable $ignored) {

                /*
                | Keep the original exception.
                */
            }

            return response()->json([

                'message' =>
                    'Payroll calculation failed.',

                'error' =>
                    $e->getMessage(),

            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CANCEL
    |--------------------------------------------------------------------------
    */

    public function destroy(
        PayrollRun $payrollRun
    ): JsonResponse {

        if (!$payrollRun->canCancel()) {
            return response()->json([
                'message' =>
                    'This payroll run cannot be cancelled in its current status.',
            ], 422);
        }

        $payrollRun->update([
            'status' =>
                'cancelled',

            'cancelled_by' =>
                auth()->id(),

            'cancelled_at' =>
                now(),

            'cancellation_reason' =>
                'Payroll run cancelled by user.',
        ]);

        return response()->json([
            'message' =>
                'Payroll run cancelled successfully.',

            'data' =>
                $payrollRun->fresh([
                    'payrollPeriod',
                ]),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | APPROVE
    |--------------------------------------------------------------------------
    */

    public function approve(
        PayrollRun $payrollRun
    ): JsonResponse {

        return DB::transaction(function () use ($payrollRun) {

            $payrollRun = PayrollRun::lockForUpdate()
                ->findOrFail($payrollRun->id);

            if (!$payrollRun->canApprove()) {
                return response()->json([
                    'message' =>
                        'Only a calculated payroll run can be approved.',
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | Make sure there are calculated employees
            |--------------------------------------------------------------------------
            */

            $calculatedCount = PayrollItem::where(
                'payroll_run_id',
                $payrollRun->id
            )
                ->where(
                    'status',
                    'calculated'
                )
                ->count();

            if ($calculatedCount === 0) {
                return response()->json([
                    'message' =>
                        'This payroll run has no calculated payroll items and cannot be approved.',
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | Do not approve excluded employees
            |--------------------------------------------------------------------------
            */

       /*     $excludedCount = PayrollItem::where(
                'payroll_run_id',
                $payrollRun->id
            )
                ->where(
                    'status',
                    'excluded'
                )
                ->count();

            if ($excludedCount > 0) {
                return response()->json([
                    'message' =>
                        'This payroll run contains '
                        . $excludedCount
                        . ' excluded employee(s). Resolve the exclusions before approving the payroll.',
                ], 422);
            }*/

            /*
            |--------------------------------------------------------------------------
            | Lock payroll period
            |--------------------------------------------------------------------------
            */

            $period = PayrollPeriod::lockForUpdate()
                ->findOrFail(
                    $payrollRun->payroll_period_id
                );

            if ($period->isLocked()) {
                return response()->json([
                    'message' =>
                        'The payroll period is already locked.',
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | Approve payroll run
            |--------------------------------------------------------------------------
            */

            $payrollRun->update([

                'status' =>
                    'approved',

                'approved_by' =>
                    auth()->id(),

                'approved_at' =>
                    now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | Approve payroll period
            |--------------------------------------------------------------------------
            */

            $period->update([

                'status' =>
                    'approved',

                'approved_by' =>
                    auth()->id(),

                'approved_at' =>
                    now(),
            ]);

            return response()->json([
                'message' =>
                    'Payroll run approved successfully.',

                'data' =>
                    $payrollRun->fresh([
                        'payrollPeriod',
                        'payrollItems',
                    ]),
            ]);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | MARK PAID
    |--------------------------------------------------------------------------
    */

    public function markPaid(
        PayrollRun $payrollRun
    ): JsonResponse {

        return DB::transaction(function () use ($payrollRun) {

            $payrollRun = PayrollRun::lockForUpdate()
                ->findOrFail($payrollRun->id);

            if (!$payrollRun->canPay()) {
                return response()->json([
                    'message' =>
                        'Only an approved payroll run can be marked as paid.',
                ], 422);
            }

            $period = PayrollPeriod::lockForUpdate()
                ->findOrFail(
                    $payrollRun->payroll_period_id
                );

            /*
            |--------------------------------------------------------------------------
            | Mark payroll run as paid
            |--------------------------------------------------------------------------
            */

            $payrollRun->update([

                'status' =>
                    'paid',

                'paid_by' =>
                    auth()->id(),

                'paid_at' =>
                    now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | Mark payroll period as paid
            |--------------------------------------------------------------------------
            */

            $period->update([

                'status' =>
                    'paid',

                'paid_by' =>
                    auth()->id(),

                'paid_at' =>
                    now(),
            ]);

            return response()->json([
                'message' =>
                    'Payroll run marked as paid.',

                'data' =>
                    $payrollRun->fresh([
                        'payrollPeriod',
                        'payrollItems',
                    ]),
            ]);
        });
    }
}

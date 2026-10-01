<?php

namespace App\Http\Controllers;

use App\Models\PaymentRun;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\StaffBankDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PaymentRunController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index(Request $request): JsonResponse
    {
        $query = PaymentRun::with([
            'payrollRun.payrollPeriod',
        ])
            ->orderByDesc('id');

        if ($request->filled('payroll_run_id')) {
            $query->where(
                'payroll_run_id',
                $request->payroll_run_id
            );
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->status
            );
        }

        $perPage = min(
            max(
                (int) $request->get('per_page', 20),
                1
            ),
            100
        );

        return response()->json(
            $query->paginate($perPage)
        );
    }


    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    |
    | Creates a payment run from an approved payroll run.
    |
    |--------------------------------------------------------------------------
    */

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payroll_run_id' => [
                'required',
                'integer',
                'exists:payroll_runs,id',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ]);

        try {

            $paymentRun = DB::transaction(
                function () use ($validated) {

                    /*
                    |--------------------------------------------------------------------------
                    | Lock Payroll Run
                    |--------------------------------------------------------------------------
                    */

                    $payrollRun = PayrollRun::with(
                        'payrollPeriod'
                    )
                        ->lockForUpdate()
                        ->findOrFail(
                            $validated['payroll_run_id']
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | Payroll Must Be Approved
                    |--------------------------------------------------------------------------
                    */

                    if (!$payrollRun->isApproved()) {
                        abort(
                            422,
                            'The payroll run must be approved before a payment run can be created.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Prevent Duplicate Active Payment Run
                    |--------------------------------------------------------------------------
                    */

                    $activeExists = PaymentRun::where(
                        'payroll_run_id',
                        $payrollRun->id
                    )
                        ->where(
                            'status',
                            '!=',
                            'cancelled'
                        )
                        ->exists();

                    if ($activeExists) {
                        abort(
                            422,
                            'An active payment run already exists for this payroll run.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Get Calculated Payroll Items
                    |--------------------------------------------------------------------------
                    */

                    $payrollItems = PayrollItem::where(
                        'payroll_run_id',
                        $payrollRun->id
                    )
                        ->where(
                            'status',
                            'calculated'
                        )
                        ->orderBy(
                            'employee_number'
                        )
                        ->lockForUpdate()
                        ->get();

                    if ($payrollItems->isEmpty()) {
                        abort(
                            422,
                            'There are no calculated payroll items available for payment.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Next Payment Run Number
                    |--------------------------------------------------------------------------
                    */

                    $lastRunNumber = PaymentRun::where(
                        'payroll_run_id',
                        $payrollRun->id
                    )
                        ->max('run_number');

                    $runNumber =
                        ((int) $lastRunNumber) + 1;

                    /*
                    |--------------------------------------------------------------------------
                    | Create Payment Run
                    |--------------------------------------------------------------------------
                    */

                    $paymentRun = PaymentRun::create([
                        'payroll_run_id' =>
                            $payrollRun->id,

                        'run_number' =>
                            $runNumber,

                        'status' =>
                            'draft',

                        'employee_count' =>
                            0,

                        'total_amount' =>
                            0,

                        'valid_count' =>
                            0,

                        'invalid_count' =>
                            0,

                        'notes' =>
                            $validated['notes'] ?? null,
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | Create Payment Items
                    |--------------------------------------------------------------------------
                    */

                    foreach ($payrollItems as $payrollItem) {

                        $bankDetails =
                            StaffBankDetails::where(
                                'staff_id',
                                $payrollItem->staff_id
                            )->first();

                        $netSalary = round(
                            (float) $payrollItem->net_salary,
                            2
                        );

                        $errors = [];

                        /*
                        |--------------------------------------------------------------------------
                        | Initial Bank Validation
                        |--------------------------------------------------------------------------
                        */

                        if (!$bankDetails) {

                            $errors[] =
                                'Bank details have not been captured.';

                        } else {

                            if (empty($bankDetails->bank_name)) {
                                $errors[] =
                                    'Bank name is missing.';
                            }

                            if (
                                empty(
                                    $bankDetails->account_holder_name
                                )
                            ) {
                                $errors[] =
                                    'Account holder name is missing.';
                            }

                            if (
                                empty(
                                    $bankDetails->account_number
                                )
                            ) {
                                $errors[] =
                                    'Account number is missing.';
                            }

                            if (
                                empty(
                                    $bankDetails->account_type
                                )
                            ) {
                                $errors[] =
                                    'Account type is missing.';
                            }

                            if (
                                empty(
                                    $bankDetails->branch_code
                                )
                            ) {
                                $errors[] =
                                    'Branch code is missing.';
                            }

                            if (
                                empty(
                                    $bankDetails->payment_method
                                )
                            ) {
                                $errors[] =
                                    'Payment method is missing.';
                            }
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | Net Salary Validation
                        |--------------------------------------------------------------------------
                        */

                        if ($netSalary <= 0) {
                            $errors[] =
                                'Net salary must be greater than zero.';
                        }

                        $initialStatus =
                            empty($errors)
                                ? 'pending'
                                : 'invalid';

                        /*
                        |--------------------------------------------------------------------------
                        | Create Payment Item Snapshot
                        |--------------------------------------------------------------------------
                        */

                        $paymentRun
                            ->paymentItems()
                            ->create([

                                'payroll_item_id' =>
                                    $payrollItem->id,

                                'staff_id' =>
                                    $payrollItem->staff_id,

                                'employee_number' =>
                                    $payrollItem->employee_number,

                                'first_name' =>
                                    $payrollItem->first_name,

                                'last_name' =>
                                    $payrollItem->last_name,

                                'bank_name' =>
                                    $bankDetails?->bank_name,

                                'account_holder_name' =>
                                    $bankDetails?->account_holder_name,

                                'account_number' =>
                                    $bankDetails?->account_number,

                                'account_type' =>
                                    $bankDetails?->account_type,

                                'branch_code' =>
                                    $bankDetails?->branch_code,

                                'payment_method' =>
                                    $bankDetails?->payment_method,

                                'payroll_net_salary' =>
                                    $netSalary,

                                'payment_amount' =>
                                    $netSalary,

                                'status' =>
                                    $initialStatus,

                                'validation_errors' =>
                                    empty($errors)
                                        ? null
                                        : implode(
                                            "\n",
                                            $errors
                                        ),

                                'validated_at' =>
                                    empty($errors)
                                        ? null
                                        : now(),
                            ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Recalculate Payment Totals
                    |--------------------------------------------------------------------------
                    */

                    $paymentRun->recalculateTotals();

                    return $paymentRun->fresh([
                        'payrollRun.payrollPeriod',
                        'paymentItems',
                    ]);
                }
            );

            return response()->json([
                'message' =>
                    'Payment run created successfully.',

                'payment_run' =>
                    $paymentRun,

                'summary' => [

                    'employee_count' =>
                        $paymentRun->employee_count,

                    'valid_count' =>
                        $paymentRun->valid_count,

                    'invalid_count' =>
                        $paymentRun->invalid_count,

                    'total_amount' =>
                        $paymentRun->total_amount,
                ],
            ], 201);

        } catch (Throwable $e) {

            report($e);

            return response()->json([
                'message' =>
                    $e instanceof
                    \Symfony\Component\HttpKernel\Exception\HttpException
                        ? $e->getMessage()
                        : 'Unable to create the payment run.',
            ], 422);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */

    public function show(
        PaymentRun $paymentRun
    ): JsonResponse {

        $paymentRun->load([
            'payrollRun.payrollPeriod',
            'paymentItems.payrollItem',
            'paymentItems.staff',
        ]);

        return response()->json(
            $paymentRun
        );
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDATE PAYMENT RUN
    |--------------------------------------------------------------------------
    */

    public function validateRun(
        PaymentRun $paymentRun
    ): JsonResponse {

        try {

            $paymentRun = DB::transaction(
                function () use ($paymentRun) {

                    /*
                    |--------------------------------------------------------------------------
                    | Lock Payment Run
                    |--------------------------------------------------------------------------
                    */

                    $paymentRun =
                        PaymentRun::lockForUpdate()
                            ->findOrFail(
                                $paymentRun->id
                            );

                    if (!$paymentRun->canValidate()) {
                        abort(
                            422,
                            'This payment run cannot be validated in its current status.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Start Validation
                    |--------------------------------------------------------------------------
                    */

                    $paymentRun->update([
                        'status' =>
                            'validating',

                        'validation_started_at' =>
                            now(),

                        'validation_completed_at' =>
                            null,
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | Load Payment Items
                    |--------------------------------------------------------------------------
                    */

                    $paymentItems =
                        $paymentRun
                            ->paymentItems()
                            ->lockForUpdate()
                            ->get();

                    if ($paymentItems->isEmpty()) {
                        abort(
                            422,
                            'This payment run contains no payment items.'
                        );
                    }

                    $validCount = 0;
                    $invalidCount = 0;
                    $totalAmount = 0;

                    /*
                    |--------------------------------------------------------------------------
                    | Validate Every Employee
                    |--------------------------------------------------------------------------
                    */

                    foreach (
                        $paymentItems as $paymentItem
                    ) {

                        $errors = [];

                        /*
                        |--------------------------------------------------------------------------
                        | Refresh Current Banking Details
                        |--------------------------------------------------------------------------
                        |
                        | Banking information may have been completed after the
                        | payment run was originally created.
                        |
                        */

                        $bankDetails =
                            StaffBankDetails::where(
                                'staff_id',
                                $paymentItem->staff_id
                            )->first();

                        if ($bankDetails) {

                            $paymentItem->update([

                                'bank_name' =>
                                    $bankDetails->bank_name,

                                'account_holder_name' =>
                                    $bankDetails
                                        ->account_holder_name,

                                'account_number' =>
                                    $bankDetails
                                        ->account_number,

                                'account_type' =>
                                    $bankDetails
                                        ->account_type,

                                'branch_code' =>
                                    $bankDetails
                                        ->branch_code,

                                'payment_method' =>
                                    $bankDetails
                                        ->payment_method,
                            ]);

                            $paymentItem->refresh();

                        } else {

                            /*
                            |--------------------------------------------------------------------------
                            | Banking Record Removed / Missing
                            |--------------------------------------------------------------------------
                            */

                            $paymentItem->update([
                                'bank_name' =>
                                    null,

                                'account_holder_name' =>
                                    null,

                                'account_number' =>
                                    null,

                                'account_type' =>
                                    null,

                                'branch_code' =>
                                    null,

                                'payment_method' =>
                                    null,
                            ]);

                            $paymentItem->refresh();
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | Amount Validation
                        |--------------------------------------------------------------------------
                        */

                        if (
                            !$paymentItem
                                ->amountMatchesPayroll()
                        ) {
                            $errors[] =
                                'Payment amount does not match payroll net salary.';
                        }

                        if (
                            (float)
                            $paymentItem->payment_amount
                            <= 0
                        ) {
                            $errors[] =
                                'Payment amount must be greater than zero.';
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | Bank Details Validation
                        |--------------------------------------------------------------------------
                        */

                        if (
                            !$paymentItem
                                ->hasBankDetails()
                        ) {
                            $errors[] =
                                'Required bank details are incomplete.';
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | Account Number Must Contain Digits Only
                        |--------------------------------------------------------------------------
                        */

                        if (
                            !empty(
                                $paymentItem->account_number
                            )
                            &&
                            !preg_match(
                                '/^[0-9]+$/',
                                (string)
                                $paymentItem->account_number
                            )
                        ) {
                            $errors[] =
                                'Account number must contain numbers only.';
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | Branch Code Must Contain Digits Only
                        |--------------------------------------------------------------------------
                        */

                        if (
                            !empty(
                                $paymentItem->branch_code
                            )
                            &&
                            !preg_match(
                                '/^[0-9]+$/',
                                (string)
                                $paymentItem->branch_code
                            )
                        ) {
                            $errors[] =
                                'Branch code must contain numbers only.';
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | Update Item Status
                        |--------------------------------------------------------------------------
                        */

                        if (empty($errors)) {

                            $paymentItem->update([
                                'status' =>
                                    'valid',

                                'validation_errors' =>
                                    null,

                                'validated_at' =>
                                    now(),
                            ]);

                            $validCount++;

                            $totalAmount += round(
                                (float)
                                $paymentItem->payment_amount,
                                2
                            );

                        } else {

                            $paymentItem->update([

                                'status' =>
                                    'invalid',

                                'validation_errors' =>
                                    implode(
                                        "\n",
                                        $errors
                                    ),

                                'validated_at' =>
                                    now(),
                            ]);

                            $invalidCount++;
                        }
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Update Payment Run Totals
                    |--------------------------------------------------------------------------
                    */

                    $paymentRun->update([

                        'status' =>
                            'validated',

                        'employee_count' =>
                            $paymentItems->count(),

                        'valid_count' =>
                            $validCount,

                        'invalid_count' =>
                            $invalidCount,

                        'total_amount' =>
                            round(
                                $totalAmount,
                                2
                            ),

                        'validation_completed_at' =>
                            now(),
                    ]);

                    return $paymentRun->fresh([
                        'payrollRun.payrollPeriod',
                        'paymentItems',
                    ]);
                }
            );

            return response()->json([
                'message' =>
                    'Payment run validation completed.',

                'payment_run' =>
                    $paymentRun,
            ]);

        } catch (Throwable $e) {

            report($e);

            return response()->json([
                'message' =>
                    $e instanceof
                    \Symfony\Component\HttpKernel\Exception\HttpException
                        ? $e->getMessage()
                        : 'Unable to validate the payment run.',
            ], 422);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | APPROVE PAYMENT RUN
    |--------------------------------------------------------------------------
    */

    public function approve(
        PaymentRun $paymentRun
    ): JsonResponse {

        try {

            $paymentRun = DB::transaction(
                function () use ($paymentRun) {

                    $paymentRun =
                        PaymentRun::lockForUpdate()
                            ->findOrFail(
                                $paymentRun->id
                            );

                    /*
                    |--------------------------------------------------------------------------
                    | Status Check
                    |--------------------------------------------------------------------------
                    */

                    if (!$paymentRun->canApprove()) {
                        abort(
                            422,
                            'This payment run cannot be approved. All payment items must be valid.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Re-check Invalid Items
                    |--------------------------------------------------------------------------
                    */

                    $invalidCount =
                        $paymentRun
                            ->paymentItems()
                            ->where(
                                'status',
                                'invalid'
                            )
                            ->count();

                    if ($invalidCount > 0) {
                        abort(
                            422,
                            'The payment run contains invalid payment items.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Make Sure We Have Valid Payments
                    |--------------------------------------------------------------------------
                    */

                    $validCount =
                        $paymentRun
                            ->paymentItems()
                            ->where(
                                'status',
                                'valid'
                            )
                            ->count();

                    if ($validCount <= 0) {
                        abort(
                            422,
                            'There are no valid payments to approve.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Underlying Payroll Must Still Be Approved
                    |--------------------------------------------------------------------------
                    */

                    $payrollRun =
                        PayrollRun::lockForUpdate()
                            ->find(
                                $paymentRun
                                    ->payroll_run_id
                            );

                    if (
                        !$payrollRun ||
                        !$payrollRun->isApproved()
                    ) {
                        abort(
                            422,
                            'The underlying payroll run must remain approved.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Approve
                    |--------------------------------------------------------------------------
                    */

                    $paymentRun->update([

                        'status' =>
                            'approved',

                        'approved_by' =>
                            auth()->id(),

                        'approved_at' =>
                            now(),
                    ]);

                    return $paymentRun->fresh([
                        'payrollRun.payrollPeriod',
                        'paymentItems',
                    ]);
                }
            );

            return response()->json([
                'message' =>
                    'Payment run approved successfully.',

                'payment_run' =>
                    $paymentRun,
            ]);

        } catch (Throwable $e) {

            report($e);

            return response()->json([
                'message' =>
                    $e instanceof
                    \Symfony\Component\HttpKernel\Exception\HttpException
                        ? $e->getMessage()
                        : 'Unable to approve the payment run.',
            ], 422);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | EXPORT PAYMENT CSV
    |--------------------------------------------------------------------------
    |
    | Creates the CSV payment file.
    |
    | IMPORTANT:
    | The final layout can later be adjusted to exactly match the
    | Standard Bank payment-file specification.
    |
    |--------------------------------------------------------------------------
    */

    public function export(
        PaymentRun $paymentRun
    ) {

        try {

            $result = DB::transaction(
                function () use ($paymentRun) {

                    /*
                    |--------------------------------------------------------------------------
                    | Lock Payment Run
                    |--------------------------------------------------------------------------
                    */

                    $paymentRun =
                        PaymentRun::lockForUpdate()
                            ->findOrFail(
                                $paymentRun->id
                            );

                    /*
                    |--------------------------------------------------------------------------
                    | Must Be Approved Before Export
                    |--------------------------------------------------------------------------
                    */

                    if (!$paymentRun->canExport()) {
                        abort(
                            422,
                            'Only an approved payment run can be exported.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Get Valid Payments
                    |--------------------------------------------------------------------------
                    */

                    $paymentItems =
                        $paymentRun
                            ->paymentItems()
                            ->where(
                                'status',
                                'valid'
                            )
                            ->orderBy(
                                'employee_number'
                            )
                            ->get();

                    if ($paymentItems->isEmpty()) {
                        abort(
                            422,
                            'There are no valid payments to export.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Final Safety Validation
                    |--------------------------------------------------------------------------
                    */

                    foreach (
                        $paymentItems as $paymentItem
                    ) {

                        if (
                            !$paymentItem
                                ->amountMatchesPayroll()
                        ) {
                            abort(
                                422,
                                'Payment amount mismatch detected for employee ' .
                                (
                                    $paymentItem
                                        ->employee_number
                                    ??
                                    $paymentItem
                                        ->staff_id
                                ) .
                                '.'
                            );
                        }

                        if (
                            !$paymentItem
                                ->hasBankDetails()
                        ) {
                            abort(
                                422,
                                'Incomplete bank details detected for employee ' .
                                (
                                    $paymentItem
                                        ->employee_number
                                    ??
                                    $paymentItem
                                        ->staff_id
                                ) .
                                '.'
                            );
                        }

                        if (
                            (float)
                            $paymentItem->payment_amount
                            <= 0
                        ) {
                            abort(
                                422,
                                'Invalid payment amount detected.'
                            );
                        }
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Filename
                    |--------------------------------------------------------------------------
                    */

                    $filename =
                        'payment_run_' .
                        $paymentRun->id .
                        '_' .
                        now()->format(
                            'Ymd_His'
                        ) .
                        '.csv';

                    $path =
                        'payment-runs/' .
                        $filename;

                    /*
                    |--------------------------------------------------------------------------
                    | Build CSV In Memory
                    |--------------------------------------------------------------------------
                    */

                    $handle = fopen(
                        'php://temp',
                        'w+'
                    );

                    if ($handle === false) {
                        abort(
                            500,
                            'Unable to create payment export.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | CSV Headers
                    |--------------------------------------------------------------------------
                    */

                    fputcsv(
                        $handle,
                        [
                            'employee_number',
                            'employee_name',
                            'bank_name',
                            'account_holder_name',
                            'account_number',
                            'account_type',
                            'branch_code',
                            'payment_method',
                            'amount',
                        ]
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | CSV Employees
                    |--------------------------------------------------------------------------
                    */

                    foreach (
                        $paymentItems as $paymentItem
                    ) {

                        fputcsv(
                            $handle,
                            [
                                $paymentItem
                                    ->employee_number,

                                $paymentItem
                                    ->employee_name,

                                $paymentItem
                                    ->bank_name,

                                $paymentItem
                                    ->account_holder_name,

                                $paymentItem
                                    ->account_number,

                                $paymentItem
                                    ->account_type,

                                $paymentItem
                                    ->branch_code,

                                $paymentItem
                                    ->payment_method,

                                number_format(
                                    (float)
                                    $paymentItem
                                        ->payment_amount,
                                    2,
                                    '.',
                                    ''
                                ),
                            ]
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Read CSV
                    |--------------------------------------------------------------------------
                    */

                    rewind($handle);

                    $csvContents =
                        stream_get_contents(
                            $handle
                        );

                    fclose($handle);

                    if ($csvContents === false) {
                        abort(
                            500,
                            'Unable to read the generated payment export.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Save CSV
                    |--------------------------------------------------------------------------
                    */

                    Storage::disk('local')->put(
                        $path,
                        $csvContents
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Confirm File Exists
                    |--------------------------------------------------------------------------
                    */

                    if (
                        !Storage::disk('local')
                            ->exists($path)
                    ) {
                        abort(
                            500,
                            'The payment export file could not be created.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Mark Payment Run Exported
                    |--------------------------------------------------------------------------
                    */

                    $paymentRun->update([

                        'status' =>
                            'exported',

                        'export_file_name' =>
                            $filename,

                        'exported_at' =>
                            now(),

                        'exported_by' =>
                            auth()->id(),
                    ]);

                    return [
                        'paymentRun' =>
                            $paymentRun->fresh([
                                'payrollRun.payrollPeriod',
                            ]),

                        'path' =>
                            $path,

                        'filename' =>
                            $filename,
                    ];
                }
            );

            /*
            |--------------------------------------------------------------------------
            | Physical File Path
            |--------------------------------------------------------------------------
            |
            | IMPORTANT:
            |
            | We deliberately do NOT use:
            |
            | Storage::disk('local')->path()
            |
            | or:
            |
            | Storage::disk('local')->download()
            |
            | This avoids the Intelephense undefined-method warnings.
            |
            */

            $fullPath = storage_path(
                'app/' . $result['path']
            );

            /*
            |--------------------------------------------------------------------------
            | Verify Physical File
            |--------------------------------------------------------------------------
            */

            if (!file_exists($fullPath)) {
                abort(
                    500,
                    'The generated payment export file could not be found.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Download CSV
            |--------------------------------------------------------------------------
            */

            return response()->download(
                $fullPath,
                $result['filename'],
                [
                    'Content-Type' =>
                        'text/csv; charset=UTF-8',
                ]
            );

        } catch (Throwable $e) {

            report($e);

            return response()->json([
                'message' =>
                    $e instanceof
                    \Symfony\Component\HttpKernel\Exception\HttpException
                        ? $e->getMessage()
                        : 'Unable to export the payment run.',
            ], 422);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | MARK PAYMENT RUN AS PAID
    |--------------------------------------------------------------------------
    */

    public function markPaid(
        PaymentRun $paymentRun
    ): JsonResponse {

        try {

            $paymentRun = DB::transaction(
                function () use ($paymentRun) {

                    /*
                    |--------------------------------------------------------------------------
                    | Lock Payment Run
                    |--------------------------------------------------------------------------
                    */

                    $paymentRun =
                        PaymentRun::lockForUpdate()
                            ->findOrFail(
                                $paymentRun->id
                            );

                    /*
                    |--------------------------------------------------------------------------
                    | Must Be Exported
                    |--------------------------------------------------------------------------
                    */

                    if (
                        !$paymentRun
                            ->canMarkAsPaid()
                    ) {
                        abort(
                            422,
                            'Only an exported payment run can be marked as paid.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Lock Payroll Run
                    |--------------------------------------------------------------------------
                    */

                    $payrollRun =
                        PayrollRun::lockForUpdate()
                            ->find(
                                $paymentRun
                                    ->payroll_run_id
                            );

                    if (!$payrollRun) {
                        abort(
                            422,
                            'The payroll run associated with this payment run could not be found.'
                        );
                    }

                    if (
                        !$payrollRun
                            ->isApproved()
                    ) {
                        abort(
                            422,
                            'The underlying payroll run must be approved before payment can be completed.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Lock Payroll Period
                    |--------------------------------------------------------------------------
                    */

                    $payrollPeriod =
                        PayrollPeriod::lockForUpdate()
                            ->find(
                                $payrollRun
                                    ->payroll_period_id
                            );

                    if (!$payrollPeriod) {
                        abort(
                            422,
                            'The payroll period could not be found.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Get Valid Payment Items
                    |--------------------------------------------------------------------------
                    */

                    $paymentItems =
                        $paymentRun
                            ->paymentItems()
                            ->where(
                                'status',
                                'valid'
                            )
                            ->lockForUpdate()
                            ->get();

                    if ($paymentItems->isEmpty()) {
                        abort(
                            422,
                            'There are no valid payment items to mark as paid.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Final Integrity Check
                    |--------------------------------------------------------------------------
                    */

                    $totalAmount = 0;

                    foreach (
                        $paymentItems as $paymentItem
                    ) {

                        if (
                            !$paymentItem
                                ->amountMatchesPayroll()
                        ) {
                            abort(
                                422,
                                'A payment amount does not match payroll. Payment cannot be completed.'
                            );
                        }

                        if (
                            !$paymentItem
                                ->hasBankDetails()
                        ) {
                            abort(
                                422,
                                'A payment item has incomplete bank details. Payment cannot be completed.'
                            );
                        }

                        if (
                            (float)
                            $paymentItem
                                ->payment_amount
                            <= 0
                        ) {
                            abort(
                                422,
                                'A payment item has an invalid payment amount.'
                            );
                        }

                        $totalAmount += round(
                            (float)
                            $paymentItem
                                ->payment_amount,
                            2
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Verify Total
                    |--------------------------------------------------------------------------
                    */

                    if (
                        round(
                            (float)
                            $paymentRun
                                ->total_amount,
                            2
                        )
                        !==
                        round(
                            $totalAmount,
                            2
                        )
                    ) {
                        abort(
                            422,
                            'Payment run total does not match the payment items.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Mark Items Paid
                    |--------------------------------------------------------------------------
                    */

                    $paymentRun
                        ->paymentItems()
                        ->where(
                            'status',
                            'valid'
                        )
                        ->update([

                            'status' =>
                                'paid',

                            'paid_at' =>
                                now(),
                        ]);

                    /*
                    |--------------------------------------------------------------------------
                    | Mark Payment Run Paid
                    |--------------------------------------------------------------------------
                    */

                    $paymentRun->update([

                        'status' =>
                            'paid',

                        'paid_by' =>
                            auth()->id(),

                        'paid_at' =>
                            now(),
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | Mark Payroll Run Paid
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
                    | Mark Payroll Period Paid
                    |--------------------------------------------------------------------------
                    */

                    $payrollPeriod->update([

                        'status' =>
                            'paid',

                        'paid_by' =>
                            auth()->id(),

                        'paid_at' =>
                            now(),
                    ]);

                    return $paymentRun->fresh([
                        'payrollRun.payrollPeriod',
                        'paymentItems',
                    ]);
                }
            );

            return response()->json([
                'message' =>
                    'Payment run marked as paid successfully.',

                'payment_run' =>
                    $paymentRun,
            ]);

        } catch (Throwable $e) {

            report($e);

            return response()->json([
                'message' =>
                    $e instanceof
                    \Symfony\Component\HttpKernel\Exception\HttpException
                        ? $e->getMessage()
                        : 'Unable to mark the payment run as paid.',
            ], 422);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CANCEL PAYMENT RUN
    |--------------------------------------------------------------------------
    */

    public function cancel(
        Request $request,
        PaymentRun $paymentRun
    ): JsonResponse {

        $validated = $request->validate([
            'reason' => [
                'required',
                'string',
                'max:5000',
            ],
        ]);

        try {

            $paymentRun = DB::transaction(
                function () use (
                    $paymentRun,
                    $validated
                ) {

                    $paymentRun =
                        PaymentRun::lockForUpdate()
                            ->findOrFail(
                                $paymentRun->id
                            );

                    /*
                    |--------------------------------------------------------------------------
                    | Can Payment Run Be Cancelled?
                    |--------------------------------------------------------------------------
                    */

                    if (!$paymentRun->canCancel()) {
                        abort(
                            422,
                            'This payment run cannot be cancelled in its current status.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Cancel
                    |--------------------------------------------------------------------------
                    */

                    $paymentRun->update([

                        'status' =>
                            'cancelled',

                        'cancelled_by' =>
                            auth()->id(),

                        'cancelled_at' =>
                            now(),

                        'cancellation_reason' =>
                            $validated['reason'],
                    ]);

                    return $paymentRun->fresh([
                        'payrollRun.payrollPeriod',
                    ]);
                }
            );

            return response()->json([
                'message' =>
                    'Payment run cancelled successfully.',

                'payment_run' =>
                    $paymentRun,
            ]);

        } catch (Throwable $e) {

            report($e);

            return response()->json([
                'message' =>
                    $e instanceof
                    \Symfony\Component\HttpKernel\Exception\HttpException
                        ? $e->getMessage()
                        : 'Unable to cancel the payment run.',
            ], 422);
        }
    }
}
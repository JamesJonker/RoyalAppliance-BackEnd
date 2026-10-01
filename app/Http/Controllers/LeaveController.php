<?php

namespace App\Http\Controllers;

use App\Models\LeaveType;
use App\Models\Staff;
use App\Models\StaffLeave;
use App\Services\LeaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    protected LeaveService $leaveService;

    public function __construct(LeaveService $leaveService)
    {
        $this->leaveService = $leaveService;
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER: CALCULATE DAYS TAKEN
    |--------------------------------------------------------------------------
    */

    /**
     * Calculate leave days using the employee's work schedule.
     *
     * Full day:
     *   Store Friday   = 1.00
     *   Store Saturday = 0.50
     *
     * Half day:
     *   Store Friday   = 0.50
     *   Store Saturday = 0.25
     *
     * The actual work schedule calculation remains inside LeaveService.
     */
    protected function calculateDaysTaken(
        Staff $staff,
        string $startDate,
        string $endDate,
        string $durationType = 'full'
    ): float {

        $days = $this->leaveService->calculateLeaveDays(
            $staff,
            $startDate,
            $endDate
        );

        if ($durationType === 'half') {
            $days *= 0.5;
        }

        return round(
            (float) $days,
            2
        );
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER: NORMALIZE DURATION
    |--------------------------------------------------------------------------
    */

    protected function normalizeDurationType(
        ?string $durationType
    ): string {

        return $durationType === 'half'
            ? 'half'
            : 'full';
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER: HALF DAY VALIDATION
    |--------------------------------------------------------------------------
    */

    /**
     * A half-day leave request must be for one date only.
     */
    protected function validateHalfDayRange(
        string $durationType,
        string $startDate,
        string $endDate
    ): ?JsonResponse {

        if (
            $durationType === 'half' &&
            $startDate !== $endDate
        ) {

            return response()->json([
                'message' =>
                    'A half-day leave request must have the same start and end date.',
            ], 422);
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    /**
     * GET /api/leaves
     *
     * Optional:
     * ?staff_id=2
     * ?leave_type_id=1
     * ?status=approved
     * ?from_date=2026-03-01
     * ?to_date=2027-02-28
     * ?per_page=20
     */
    public function index(Request $request): JsonResponse
    {
        $query = StaffLeave::with([
            'staff',
            'leaveType',
            'approver',
        ]);

        if ($request->filled('staff_id')) {
            $query->where(
                'staff_id',
                $request->staff_id
            );
        }

        if ($request->filled('leave_type_id')) {
            $query->where(
                'leave_type_id',
                $request->leave_type_id
            );
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->status
            );
        }

        if ($request->filled('from_date')) {
            $query->whereDate(
                'start_date',
                '>=',
                $request->from_date
            );
        }

        if ($request->filled('to_date')) {
            $query->whereDate(
                'end_date',
                '<=',
                $request->to_date
            );
        }

        $perPage = min(
            max(
                (int) $request->get(
                    'per_page',
                    20
                ),
                1
            ),
            100
        );

        $leaves = $query
            ->orderBy(
                'start_date',
                'desc'
            )
            ->orderBy(
                'id',
                'desc'
            )
            ->paginate(
                $perPage
            );

        return response()->json(
            $leaves
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */

    /**
     * GET /api/leaves/{id}
     */
    public function show(int $id): JsonResponse
    {
        $leave = StaffLeave::with([
            'staff',
            'leaveType',
            'approver',
        ])->findOrFail($id);

        return response()->json([
            'leave' => $leave,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    /**
     * POST /api/leaves
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([

            'staff_id' => [
                'required',
                'integer',
                'exists:staff,id',
            ],

            'leave_type_id' => [
                'required',
                'integer',
                'exists:leave_types,id',
            ],

            'start_date' => [
                'required',
                'date',
            ],

            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
            ],

            'duration_type' => [
                'required',
                'in:full,half',
            ],

            'half_day_period' => [
                'nullable',
                'required_if:duration_type,half',
                'in:morning,afternoon',
            ],

            'reason' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $durationType = $this->normalizeDurationType(
            $validated['duration_type']
        );

        $halfDayError = $this->validateHalfDayRange(
            $durationType,
            $validated['start_date'],
            $validated['end_date']
        );

        if ($halfDayError) {
            return $halfDayError;
        }

        /*
        |--------------------------------------------------------------------------
        | Staff
        |--------------------------------------------------------------------------
        */

        $staff = Staff::with([
            'workSchedule.days',
        ])->findOrFail(
            $validated['staff_id']
        );

        /*
        |--------------------------------------------------------------------------
        | Leave Type
        |--------------------------------------------------------------------------
        */

        $leaveType = LeaveType::findOrFail(
            $validated['leave_type_id']
        );

        if (!$leaveType->is_active) {
            return response()->json([
                'message' =>
                    'This leave type is currently inactive.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Work Schedule
        |--------------------------------------------------------------------------
        */

        if (!$staff->workSchedule) {
            return response()->json([
                'message' =>
                    'This employee does not have a work schedule assigned. '
                    . 'Please assign a work schedule before capturing leave.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Calculate Days
        |--------------------------------------------------------------------------
        */

        $daysTaken = $this->calculateDaysTaken(
            $staff,
            $validated['start_date'],
            $validated['end_date'],
            $durationType
        );

        if ($daysTaken <= 0) {
            return response()->json([
                'message' =>
                    'The selected dates do not contain any working leave days.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Check Overlap
        |--------------------------------------------------------------------------
        */

        $overlappingLeave = StaffLeave::where(
            'staff_id',
            $staff->id
        )
            ->whereIn('status', [
                StaffLeave::STATUS_PENDING,
                StaffLeave::STATUS_APPROVED,
            ])
            ->where(function ($query) use ($validated) {

                $query
                    ->whereDate(
                        'start_date',
                        '<=',
                        $validated['end_date']
                    )
                    ->whereDate(
                        'end_date',
                        '>=',
                        $validated['start_date']
                    );

            })
            ->first();

        if ($overlappingLeave) {
            return response()->json([
                'message' =>
                    'The employee already has leave covering part or all of these dates.',

                'existing_leave' =>
                    $overlappingLeave,
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Get Balance
        |--------------------------------------------------------------------------
        */

        $balance = $this->leaveService->getLeaveBalance(
            $staff,
            $leaveType,
            $validated['end_date']
        );

        $remaining = round(
            (float) ($balance['remaining'] ?? 0),
            2
        );

        /*
        |--------------------------------------------------------------------------
        | Unpaid Leave Does Not Use A Balance
        |--------------------------------------------------------------------------
        */

        if (
            strtoupper((string) $leaveType->code) !== 'UNPAID'
            &&
            $daysTaken > $remaining
        ) {

            return response()->json([
                'message' =>
                    'The employee does not have enough leave available.',

                'days_requested' =>
                    $daysTaken,

                'leave_remaining' =>
                    $remaining,

                'shortfall' =>
                    round(
                        $daysTaken - $remaining,
                        2
                    ),
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Create Leave
        |--------------------------------------------------------------------------
        */

        $leave = StaffLeave::create([

            'staff_id' =>
                $staff->id,

            'leave_type_id' =>
                $leaveType->id,

            'start_date' =>
                $validated['start_date'],

            'end_date' =>
                $validated['end_date'],

            'days_taken' =>
                $daysTaken,

            'duration_type' =>
                $durationType,

            'half_day_period' =>
                $durationType === 'half'
                    ? $validated['half_day_period']
                    : null,

            'status' =>
                StaffLeave::STATUS_PENDING,

            'reason' =>
                $validated['reason'] ?? null,

            'notes' =>
                $validated['notes'] ?? null,

        ]);

        $leave->load([
            'staff',
            'leaveType',
            'approver',
        ]);

        return response()->json([

            'message' =>
                'Leave captured successfully.',

            'leave' =>
                $leave,

            'days_taken' =>
                $daysTaken,

            'balance' =>
                $balance,

        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    /**
     * PUT /api/leaves/{id}
     */
    public function update(
        Request $request,
        int $id
    ): JsonResponse {

        $leave = StaffLeave::findOrFail(
            $id
        );

        if (
            $leave->status !==
            StaffLeave::STATUS_PENDING
        ) {

            return response()->json([
                'message' =>
                    'Only pending leave can be edited.',
            ], 422);
        }

        $validated = $request->validate([

            'staff_id' => [
                'required',
                'integer',
                'exists:staff,id',
            ],

            'leave_type_id' => [
                'required',
                'integer',
                'exists:leave_types,id',
            ],

            'start_date' => [
                'required',
                'date',
            ],

            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
            ],

            'duration_type' => [
                'required',
                'in:full,half',
            ],

            'half_day_period' => [
                'nullable',
                'required_if:duration_type,half',
                'in:morning,afternoon',
            ],

            'reason' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $durationType = $this->normalizeDurationType(
            $validated['duration_type']
        );

        $halfDayError = $this->validateHalfDayRange(
            $durationType,
            $validated['start_date'],
            $validated['end_date']
        );

        if ($halfDayError) {
            return $halfDayError;
        }

        $staff = Staff::with([
            'workSchedule.days',
        ])->findOrFail(
            $validated['staff_id']
        );

        $leaveType = LeaveType::findOrFail(
            $validated['leave_type_id']
        );

        if (!$leaveType->is_active) {
            return response()->json([
                'message' =>
                    'This leave type is currently inactive.',
            ], 422);
        }

        if (!$staff->workSchedule) {
            return response()->json([
                'message' =>
                    'This employee does not have a work schedule assigned.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Calculate Days
        |--------------------------------------------------------------------------
        */

        $daysTaken = $this->calculateDaysTaken(
            $staff,
            $validated['start_date'],
            $validated['end_date'],
            $durationType
        );

        if ($daysTaken <= 0) {
            return response()->json([
                'message' =>
                    'The selected dates do not contain any working leave days.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Overlap
        |--------------------------------------------------------------------------
        */

        $overlappingLeave = StaffLeave::where(
            'staff_id',
            $staff->id
        )
            ->where(
                'id',
                '!=',
                $leave->id
            )
            ->whereIn('status', [
                StaffLeave::STATUS_PENDING,
                StaffLeave::STATUS_APPROVED,
            ])
            ->where(function ($query) use ($validated) {

                $query
                    ->whereDate(
                        'start_date',
                        '<=',
                        $validated['end_date']
                    )
                    ->whereDate(
                        'end_date',
                        '>=',
                        $validated['start_date']
                    );

            })
            ->first();

        if ($overlappingLeave) {
            return response()->json([
                'message' =>
                    'The employee already has leave covering part or all of these dates.',

                'existing_leave' =>
                    $overlappingLeave,
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Balance
        |--------------------------------------------------------------------------
        */

        $balance = $this->leaveService->getLeaveBalance(
            $staff,
            $leaveType,
            $validated['end_date']
        );

        $remaining = round(
            (float) ($balance['remaining'] ?? 0),
            2
        );

        if (
            strtoupper((string) $leaveType->code) !== 'UNPAID'
            &&
            $daysTaken > $remaining
        ) {

            return response()->json([
                'message' =>
                    'The employee does not have enough leave available.',

                'days_requested' =>
                    $daysTaken,

                'leave_remaining' =>
                    $remaining,

                'shortfall' =>
                    round(
                        $daysTaken - $remaining,
                        2
                    ),
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Update
        |--------------------------------------------------------------------------
        */

        $leave->update([

            'staff_id' =>
                $staff->id,

            'leave_type_id' =>
                $leaveType->id,

            'start_date' =>
                $validated['start_date'],

            'end_date' =>
                $validated['end_date'],

            'days_taken' =>
                $daysTaken,

            'duration_type' =>
                $durationType,

            'half_day_period' =>
                $durationType === 'half'
                    ? $validated['half_day_period']
                    : null,

            'reason' =>
                $validated['reason'] ?? null,

            'notes' =>
                $validated['notes'] ?? null,

        ]);

        $leave->load([
            'staff',
            'leaveType',
            'approver',
        ]);

        return response()->json([

            'message' =>
                'Leave updated successfully.',

            'leave' =>
                $leave,

            'days_taken' =>
                $daysTaken,

            'balance' =>
                $balance,

        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    /**
     * DELETE /api/leaves/{id}
     */
    public function destroy(
        int $id
    ): JsonResponse {

        $leave = StaffLeave::findOrFail(
            $id
        );

        if (
            $leave->status ===
            StaffLeave::STATUS_APPROVED
        ) {

            return response()->json([
                'message' =>
                    'Approved leave cannot be deleted. Cancel it instead.',
            ], 422);
        }

        $leave->delete();

        return response()->json([
            'message' =>
                'Leave deleted successfully.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | APPROVE
    |--------------------------------------------------------------------------
    */

    /**
     * POST /api/leaves/{id}/approve
     */
    public function approve(
        Request $request,
        int $id
    ): JsonResponse {

        $leave = StaffLeave::with([
            'staff.workSchedule.days',
            'leaveType',
        ])->findOrFail(
            $id
        );

        if (
            $leave->status !==
            StaffLeave::STATUS_PENDING
        ) {

            return response()->json([
                'message' =>
                    'Only pending leave can be approved.',
            ], 422);
        }

        if (!$leave->staff->workSchedule) {

            return response()->json([
                'message' =>
                    'This employee does not have a work schedule assigned.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Existing Records
        |--------------------------------------------------------------------------
        |
        | Older leave records may not have duration_type.
        | They are treated as full-day.
        |--------------------------------------------------------------------------
        */

        $durationType = $this->normalizeDurationType(
            $leave->duration_type
        );

        /*
        |--------------------------------------------------------------------------
        | Recalculate Days
        |--------------------------------------------------------------------------
        */

        $calculatedDays = $this->calculateDaysTaken(
            $leave->staff,
            $leave->start_date,
            $leave->end_date,
            $durationType
        );

        if ($calculatedDays <= 0) {

            return response()->json([
                'message' =>
                    'The selected leave dates do not contain any working leave days.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Get Balance
        |--------------------------------------------------------------------------
        */

        $balance = $this->leaveService->getLeaveBalance(
            $leave->staff,
            $leave->leaveType,
            $leave->end_date
        );

        $remaining = round(
            (float) ($balance['remaining'] ?? 0),
            2
        );

        /*
        |--------------------------------------------------------------------------
        | Check Balance
        |--------------------------------------------------------------------------
        */

        if (
            strtoupper((string) $leave->leaveType->code) !== 'UNPAID'
            &&
            $calculatedDays > $remaining
        ) {

            return response()->json([
                'message' =>
                    'The employee no longer has enough leave available to approve this request.',

                'days_requested' =>
                    $calculatedDays,

                'leave_remaining' =>
                    $remaining,

                'shortfall' =>
                    round(
                        $calculatedDays - $remaining,
                        2
                    ),
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Approver
        |--------------------------------------------------------------------------
        */

        $approvedBy = auth()->id();

        /*
        |--------------------------------------------------------------------------
        | Approve
        |--------------------------------------------------------------------------
        */

        $leave->update([

            'status' =>
                StaffLeave::STATUS_APPROVED,

            'days_taken' =>
                $calculatedDays,

            'duration_type' =>
                $durationType,

            'approved_by' =>
                $approvedBy,

            'approved_at' =>
                now(),

        ]);

        $leave->load([
            'staff',
            'leaveType',
            'approver',
        ]);

        return response()->json([

            'message' =>
                'Leave approved successfully.',

            'leave' =>
                $leave,

            'days_taken' =>
                $calculatedDays,

        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | REJECT
    |--------------------------------------------------------------------------
    */

    /**
     * POST /api/leaves/{id}/reject
     */
    public function reject(
        Request $request,
        int $id
    ): JsonResponse {

        $leave = StaffLeave::findOrFail(
            $id
        );

        if (
            $leave->status !==
            StaffLeave::STATUS_PENDING
        ) {

            return response()->json([
                'message' =>
                    'Only pending leave can be rejected.',
            ], 422);
        }

        $validated = $request->validate([
            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $leave->update([

            'status' =>
                StaffLeave::STATUS_REJECTED,

            'notes' =>
                $validated['notes']
                ?? $leave->notes,

        ]);

        $leave->load([
            'staff',
            'leaveType',
            'approver',
        ]);

        return response()->json([

            'message' =>
                'Leave rejected successfully.',

            'leave' =>
                $leave,

        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CANCEL
    |--------------------------------------------------------------------------
    */

    /**
     * POST /api/leaves/{id}/cancel
     */
    public function cancel(
        int $id
    ): JsonResponse {

        $leave = StaffLeave::with([
            'staff',
            'leaveType',
        ])->findOrFail(
            $id
        );

        if (
            !in_array(
                $leave->status,
                [
                    StaffLeave::STATUS_PENDING,
                    StaffLeave::STATUS_APPROVED,
                ],
                true
            )
        ) {

            return response()->json([
                'message' =>
                    'Only pending or approved leave can be cancelled.',
            ], 422);
        }

        $leave->update([
            'status' =>
                StaffLeave::STATUS_CANCELLED,
        ]);

        $leave->load([
            'staff',
            'leaveType',
            'approver',
        ]);

        return response()->json([

            'message' =>
                'Leave cancelled successfully.',

            'leave' =>
                $leave,

        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | BALANCE
    |--------------------------------------------------------------------------
    */

    /**
     * GET /api/staff/{staff}/leave-balance
     */
    public function balance(
        Request $request,
        int $staffId
    ): JsonResponse {

        $staff = Staff::with([
            'workSchedule.days',
        ])->findOrFail(
            $staffId
        );

        /*
        |--------------------------------------------------------------------------
        | Specific Leave Type
        |--------------------------------------------------------------------------
        */

        if ($request->filled('leave_type_id')) {

            $leaveType = LeaveType::findOrFail(
                $request->leave_type_id
            );

            $balance = $this->leaveService->getLeaveBalance(
                $staff,
                $leaveType,
                $request->get('as_of_date')
            );

            return response()->json([
                'staff' =>
                    $staff,

                'balance' =>
                    $balance,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | All Leave Types
        |--------------------------------------------------------------------------
        */

        $leaveTypes = LeaveType::where(
            'is_active',
            true
        )
            ->orderBy('id')
            ->get();

        $balances = [];

        foreach (
            $leaveTypes
            as $leaveType
        ) {

            $balance =
                $this->leaveService->getLeaveBalance(
                    $staff,
                    $leaveType,
                    $request->get('as_of_date')
                );

            $balance['leave_type_id'] =
                $leaveType->id;

            $balance['leave_type'] =
                $leaveType->name;

            $balance['code'] =
                $leaveType->code;

            $balances[] =
                $balance;
        }

        return response()->json([

            'staff' =>
                $staff,

            'balances' =>
                $balances,

        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SUMMARY
    |--------------------------------------------------------------------------
    */

    /**
     * GET /api/staff/{staff}/leave-summary
     */
    public function summary(
        Request $request,
        int $staffId
    ): JsonResponse {

        $staff = Staff::with([
            'workSchedule.days',
        ])->findOrFail(
            $staffId
        );

        $leaveTypes = LeaveType::where(
            'is_active',
            true
        )
            ->orderBy('id')
            ->get();

        $balances = [];

        foreach (
            $leaveTypes
            as $leaveType
        ) {

            $balance =
                $this->leaveService->getLeaveBalance(
                    $staff,
                    $leaveType,
                    $request->get('as_of_date')
                );

            $balance['leave_type_id'] =
                $leaveType->id;

            $balance['leave_type'] =
                $leaveType->name;

            $balance['code'] =
                $leaveType->code;

            $balances[] =
                $balance;
        }

        $recentLeave = StaffLeave::with([
            'leaveType',
            'approver',
        ])
            ->where(
                'staff_id',
                $staff->id
            )
            ->orderBy(
                'start_date',
                'desc'
            )
            ->orderBy(
                'id',
                'desc'
            )
            ->limit(20)
            ->get();

        return response()->json([

            'staff' =>
                $staff,

            'balances' =>
                $balances,

            'recent_leave' =>
                $recentLeave,

        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | LEAVE TYPES
    |--------------------------------------------------------------------------
    */

    /**
     * GET /api/leave-types
     */
    public function types(): JsonResponse
    {
        $leaveTypes = LeaveType::where(
            'is_active',
            true
        )
            ->orderBy('id')
            ->get();

        return response()->json(
            $leaveTypes
        );
    }
}
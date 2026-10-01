<?php

namespace App\Services;

use App\Models\LeaveType;
use App\Models\Staff;
use App\Models\StaffLeave;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class LeaveService
{
    /*
    |--------------------------------------------------------------------------
    | CONSTANTS
    |--------------------------------------------------------------------------
    */

    protected int $annualLeaveStartMonth = 3;

    protected int $annualLeaveCycleMonths = 12;

    protected int $sickLeaveCycleMonths = 36;

    protected int $sickLeaveDaysWorkedRatio = 26;

    protected int $sickLeaveWeeks = 6;

    protected float $familyResponsibilityDays = 3.00;


    /*
    |--------------------------------------------------------------------------
    | YEARS OF SERVICE
    |--------------------------------------------------------------------------
    */

    public function getYearsOfService(
        Staff $staff,
        ?CarbonInterface $asOfDate = null
    ): int {

        if (!$staff->start_date) {
            return 0;
        }

        $startDate =
            $this->parseDate(
                $staff->start_date
            );

        $asOfDate =
            $asOfDate
                ? $this->parseDate(
                    $asOfDate
                )
                : Carbon::today();

        if (
            $asOfDate->lt(
                $startDate
            )
        ) {
            return 0;
        }

        return
            $startDate->diffInYears(
                $asOfDate
            );
    }


    /*
    |--------------------------------------------------------------------------
    | LEAVE TYPE
    |--------------------------------------------------------------------------
    */

    protected function getCalculationMethod(
        LeaveType $leaveType
    ): string {

        if (
            !empty(
                $leaveType->calculation_method
            )
        ) {

            return strtoupper(
                (string)
                $leaveType->calculation_method
            );
        }

        return match (
            strtoupper(
                (string)
                $leaveType->code
            )
        ) {

            'ANNUAL' =>
                'MONTHLY',

            'SICK' =>
                'SICK_36_MONTH',

            'FAMILY' =>
                'FIXED_ANNUAL',

            'UNPAID' =>
                'NONE',

            default =>
                'NONE',
        };
    }


    /*
    |--------------------------------------------------------------------------
    | ANNUAL ENTITLEMENT
    |--------------------------------------------------------------------------
    */

    public function getAnnualEntitlement(
        Staff $staff,
        LeaveType $leaveType,
        ?CarbonInterface $asOfDate = null
    ): float {

        $method =
            $this->getCalculationMethod(
                $leaveType
            );


        /*
        |--------------------------------------------------------------------------
        | UNPAID
        |--------------------------------------------------------------------------
        */

        if (
            $method === 'NONE'
        ) {

            return 0.00;
        }


        /*
        |--------------------------------------------------------------------------
        | FAMILY RESPONSIBILITY
        |--------------------------------------------------------------------------
        */

        if (
            $method === 'FIXED_ANNUAL'
        ) {

            if (
                strtoupper(
                    (string)
                    $leaveType->code
                )
                ===
                'FAMILY'
            ) {

                return
                    $this
                        ->familyResponsibilityDays;
            }

            return 0.00;
        }


        /*
        |--------------------------------------------------------------------------
        | SICK LEAVE
        |--------------------------------------------------------------------------
        */

        if (
            $method === 'SICK_36_MONTH'
        ) {

            return 0.00;
        }


        /*
        |--------------------------------------------------------------------------
        | ANNUAL LEAVE
        |--------------------------------------------------------------------------
        */

        if (
            strtoupper(
                (string)
                $leaveType->code
            )
            ===
            'ANNUAL'
        ) {

            /*
            |--------------------------------------------------------------------------
            | GET NORMAL WEEKLY SCHEDULE
            |--------------------------------------------------------------------------
            */

            $schedule =
                $this
                    ->getWorkScheduleFractions(
                        $staff
                    );


            /*
            |--------------------------------------------------------------------------
            | COUNT SCHEDULED DAYS
            |--------------------------------------------------------------------------
            |
            | Any day greater than zero counts as a scheduled day.
            |
            | Example:
            |
            | Monday    1.00
            | Tuesday   1.00
            | Wednesday 1.00
            | Thursday  1.00
            | Friday    1.00
            | Saturday  0.50
            |
            | = 6 scheduled working days
            |
            */

            $scheduledDays =
                0;


            foreach (
                $schedule
                as $fraction
            ) {

                if (
                    (float)
                    $fraction
                    >
                    0
                ) {

                    $scheduledDays++;
                }
            }


            /*
            |--------------------------------------------------------------------------
            | SIX DAY SCHEDULE
            |--------------------------------------------------------------------------
            */

            if (
                $scheduledDays >= 6
            ) {

                return 18.00;
            }


            /*
            |--------------------------------------------------------------------------
            | FIVE DAY SCHEDULE
            |--------------------------------------------------------------------------
            */

            return 16.00;
        }


        return 0.00;
    }


    /*
    |--------------------------------------------------------------------------
    | MONTHLY ACCRUAL
    |--------------------------------------------------------------------------
    */

    public function getMonthlyAccrual(
        Staff $staff,
        LeaveType $leaveType,
        ?CarbonInterface $asOfDate = null
    ): float {

        if (
            $this->getCalculationMethod(
                $leaveType
            )
            !==
            'MONTHLY'
        ) {

            return 0.00;
        }


        $annualEntitlement =
            $this->getAnnualEntitlement(
                $staff,
                $leaveType,
                $asOfDate
            );


        if (
            $annualEntitlement <= 0
        ) {

            return 0.00;
        }


        return round(
            $annualEntitlement
            /
            12,
            4
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ANNUAL LEAVE YEAR
    |--------------------------------------------------------------------------
    */

    public function getLeaveYearStart(
        ?CarbonInterface $date = null
    ): Carbon {

        $date =
            $date
                ? $this->parseDate(
                    $date
                )
                : Carbon::today();


        if (
            $date->month
            >=
            $this->annualLeaveStartMonth
        ) {

            return Carbon::create(
                $date->year,
                $this->annualLeaveStartMonth,
                1
            )
            ->startOfDay();
        }


        return Carbon::create(
            $date->year - 1,
            $this->annualLeaveStartMonth,
            1
        )
        ->startOfDay();
    }


    public function getLeaveYearEnd(
        ?CarbonInterface $date = null
    ): Carbon {

        return
            $this
                ->getLeaveYearStart(
                    $date
                )
                ->copy()
                ->addMonths(
                    $this->annualLeaveCycleMonths
                )
                ->subDay()
                ->endOfDay();
    }


    /*
    |--------------------------------------------------------------------------
    | ACCRUED ANNUAL LEAVE
    |--------------------------------------------------------------------------
    */

    public function calculateAccruedLeave(
        Staff $staff,
        LeaveType $leaveType,
        ?CarbonInterface $asOfDate = null
    ): float {

        if (
            !$staff->start_date
        ) {

            return 0.00;
        }


        $method =
            $this->getCalculationMethod(
                $leaveType
            );


        if (
            $method !== 'MONTHLY'
        ) {

            return 0.00;
        }


        $asOfDate =
            $asOfDate
                ? $this->parseDate(
                    $asOfDate
                )
                : Carbon::today();


        $startDate =
            $this->parseDate(
                $staff->start_date
            );


        if (
            $asOfDate->lt(
                $startDate
            )
        ) {

            return 0.00;
        }


        $leaveYearStart =
            $this->getLeaveYearStart(
                $asOfDate
            );


        $leaveYearEnd =
            $this->getLeaveYearEnd(
                $asOfDate
            );


        /*
        |--------------------------------------------------------------------------
        | ACCRUAL START
        |--------------------------------------------------------------------------
        |
        | Start at the later of:
        |
        | - company leave year start
        | - employee start date
        |
        */

        $accrualStart =
            $leaveYearStart
                ->copy();


        if (
            $startDate->gt(
                $accrualStart
            )
        ) {

            $accrualStart =
                $startDate
                    ->copy();
        }


        if (
            $accrualStart->gt(
                $leaveYearEnd
            )
        ) {

            return 0.00;
        }


        /*
        |--------------------------------------------------------------------------
        | NUMBER OF ACCRUAL MONTHS
        |--------------------------------------------------------------------------
        */

        $completedMonths =
            $this->getCompletedAccrualMonths(
                $accrualStart,
                $asOfDate
            );


        /*
        |--------------------------------------------------------------------------
        | MONTHLY ACCRUAL
        |--------------------------------------------------------------------------
        */

        $monthlyAccrual =
            $this->getMonthlyAccrual(
                $staff,
                $leaveType,
                $asOfDate
            );


        /*
        |--------------------------------------------------------------------------
        | ACCRUED
        |--------------------------------------------------------------------------
        */

        $accrued =
            $completedMonths
            *
            $monthlyAccrual;


        /*
        |--------------------------------------------------------------------------
        | CAP AT ANNUAL ENTITLEMENT
        |--------------------------------------------------------------------------
        */

        $annualEntitlement =
            $this->getAnnualEntitlement(
                $staff,
                $leaveType,
                $asOfDate
            );


        $accrued =
            min(
                $accrued,
                $annualEntitlement
            );


        return round(
            $accrued,
            2
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ACCRUAL MONTH COUNT
    |--------------------------------------------------------------------------
    |
    | Current month counts.
    |
    | Example:
    |
    | Start July
    | As-of September
    |
    | July      = 1
    | August    = 2
    | September = 3
    |
    */

    protected function getCompletedAccrualMonths(
        Carbon $startDate,
        Carbon $asOfDate
    ): int {

        $startMonth =
            $startDate
                ->copy()
                ->startOfMonth();


        $asOfMonth =
            $asOfDate
                ->copy()
                ->startOfMonth();


        if (
            $asOfMonth->lt(
                $startMonth
            )
        ) {

            return 0;
        }


        return
            $startMonth
                ->diffInMonths(
                    $asOfMonth
                )
            +
            1;
    }


    /*
    |--------------------------------------------------------------------------
    | SICK LEAVE CYCLE
    |--------------------------------------------------------------------------
    */

    public function getSickLeaveCycleStart(
        Staff $staff,
        ?CarbonInterface $date = null
    ): Carbon {

        $date =
            $date
                ? $this->parseDate(
                    $date
                )
                : Carbon::today();


        if (
            !$staff->start_date
        ) {

            return
                $date
                    ->copy()
                    ->startOfDay();
        }


        $employmentStart =
            $this
                ->parseDate(
                    $staff->start_date
                )
                ->startOfDay();


        if (
            $date->lt(
                $employmentStart
            )
        ) {

            return
                $employmentStart;
        }


        $months =
            $employmentStart
                ->diffInMonths(
                    $date
                );


        $cycleNumber =
            intdiv(
                $months,
                $this->sickLeaveCycleMonths
            );


        return
            $employmentStart
                ->copy()
                ->addMonthsNoOverflow(
                    $cycleNumber
                    *
                    $this->sickLeaveCycleMonths
                )
                ->startOfDay();
    }


    public function getSickLeaveCycleEnd(
        Staff $staff,
        ?CarbonInterface $date = null
    ): Carbon {

        return
            $this
                ->getSickLeaveCycleStart(
                    $staff,
                    $date
                )
                ->copy()
                ->addMonths(
                    $this->sickLeaveCycleMonths
                )
                ->subDay()
                ->endOfDay();
    }


    /*
    |--------------------------------------------------------------------------
    | SICK LEAVE ENTITLEMENT
    |--------------------------------------------------------------------------
    */

    public function calculateSickLeaveEntitlement(
        Staff $staff
    ): float {

        $dailySchedule =
            $this->getWorkScheduleFractions(
                $staff
            );


        /*
        |--------------------------------------------------------------------------
        | WEEKLY DAY FRACTIONS
        |--------------------------------------------------------------------------
        |
        | Store schedule:
        |
        | 1 + 1 + 1 + 1 + 1 + 0.5 = 5.5
        |
        | 5.5 × 6 weeks = 33
        |
        */

        $weeklyDays =
            array_sum(
                $dailySchedule
            );


        return round(
            $weeklyDays
            *
            $this->sickLeaveWeeks,
            2
        );
    }


    /*
    |--------------------------------------------------------------------------
    | FIRST SIX MONTHS SICK LEAVE
    |--------------------------------------------------------------------------
    */

    public function calculateFirstSixMonthsSickLeave(
        Staff $staff,
        ?CarbonInterface $asOfDate = null
    ): float {

        if (
            !$staff->start_date
        ) {

            return 0.00;
        }


        $asOfDate =
            $asOfDate
                ? $this->parseDate(
                    $asOfDate
                )
                : Carbon::today();


        $startDate =
            $this
                ->parseDate(
                    $staff->start_date
                )
                ->startOfDay();


        if (
            $asOfDate->lt(
                $startDate
            )
        ) {

            return 0.00;
        }


        $sixMonthsEnd =
            $startDate
                ->copy()
                ->addMonthsNoOverflow(
                    6
                )
                ->subDay()
                ->endOfDay();


        $calculationEnd =
            $asOfDate->lt(
                $sixMonthsEnd
            )
                ? $asOfDate
                : $sixMonthsEnd;


        $workedDays =
            $this->calculateScheduledWorkingDays(
                $staff,
                $startDate,
                $calculationEnd
            );


        return round(
            $workedDays
            /
            $this->sickLeaveDaysWorkedRatio,
            2
        );
    }


    /*
    |--------------------------------------------------------------------------
    | SICK LEAVE AVAILABLE
    |--------------------------------------------------------------------------
    */

    public function calculateSickLeaveAccrued(
        Staff $staff,
        ?CarbonInterface $asOfDate = null
    ): float {

        if (
            !$staff->start_date
        ) {

            return 0.00;
        }


        $asOfDate =
            $asOfDate
                ? $this->parseDate(
                    $asOfDate
                )
                : Carbon::today();


        $startDate =
            $this
                ->parseDate(
                    $staff->start_date
                )
                ->startOfDay();


        if (
            $asOfDate->lt(
                $startDate
            )
        ) {

            return 0.00;
        }


        /*
        |--------------------------------------------------------------------------
        | FIRST SIX MONTHS
        |--------------------------------------------------------------------------
        */

        $sixMonthsDate =
            $startDate
                ->copy()
                ->addMonthsNoOverflow(
                    6
                );


        if (
            $asOfDate->lt(
                $sixMonthsDate
            )
        ) {

            return
                $this
                    ->calculateFirstSixMonthsSickLeave(
                        $staff,
                        $asOfDate
                    );
        }


        /*
        |--------------------------------------------------------------------------
        | AFTER SIX MONTHS
        |--------------------------------------------------------------------------
        */

        return
            $this
                ->calculateSickLeaveEntitlement(
                    $staff
                );
    }


    /*
    |--------------------------------------------------------------------------
    | FAMILY RESPONSIBILITY LEAVE
    |--------------------------------------------------------------------------
    */

    public function calculateFamilyResponsibilityLeave(
        Staff $staff,
        ?CarbonInterface $asOfDate = null
    ): float {

        if (
            !$staff->start_date
        ) {

            return 0.00;
        }


        $asOfDate =
            $asOfDate
                ? $this->parseDate(
                    $asOfDate
                )
                : Carbon::today();


        $startDate =
            $this->parseDate(
                $staff->start_date
            );


        $qualifyingDate =
            $startDate
                ->copy()
                ->addMonthsNoOverflow(
                    4
                );


        if (
            $asOfDate->lt(
                $qualifyingDate
            )
        ) {

            return 0.00;
        }


        return
            $this
                ->familyResponsibilityDays;
    }


    /*
    |--------------------------------------------------------------------------
    | COMPLETE LEAVE BALANCE
    |--------------------------------------------------------------------------
    */

    public function getLeaveBalance(
        Staff $staff,
        LeaveType $leaveType,
        CarbonInterface|string|null $asOfDate = null
    ): array {

        $asOfDate =
            $asOfDate
                ? $this->parseDate(
                    $asOfDate
                )
                : Carbon::today();


        $method =
            $this->getCalculationMethod(
                $leaveType
            );


        /*
        |--------------------------------------------------------------------------
        | DEFAULT CYCLE
        |--------------------------------------------------------------------------
        */

        $leaveYearStart =
            $this->getLeaveYearStart(
                $asOfDate
            );


        $leaveYearEnd =
            $this->getLeaveYearEnd(
                $asOfDate
            );


        $yearsOfService =
            $this->getYearsOfService(
                $staff,
                $asOfDate
            );


        $annualEntitlement =
            0.00;


        $monthlyAccrual =
            0.00;


        $accrued =
            0.00;


        $cycleStart =
            $leaveYearStart;


        $cycleEnd =
            $leaveYearEnd;


        /*
        |--------------------------------------------------------------------------
        | ANNUAL LEAVE
        |--------------------------------------------------------------------------
        */

        if (
            $method === 'MONTHLY'
        ) {

            $annualEntitlement =
                $this->getAnnualEntitlement(
                    $staff,
                    $leaveType,
                    $asOfDate
                );


            $monthlyAccrual =
                $this->getMonthlyAccrual(
                    $staff,
                    $leaveType,
                    $asOfDate
                );


            $accrued =
                $this->calculateAccruedLeave(
                    $staff,
                    $leaveType,
                    $asOfDate
                );
        }


        /*
        |--------------------------------------------------------------------------
        | SICK LEAVE
        |--------------------------------------------------------------------------
        */

        elseif (
            $method === 'SICK_36_MONTH'
        ) {

            $cycleStart =
                $this->getSickLeaveCycleStart(
                    $staff,
                    $asOfDate
                );


            $cycleEnd =
                $this->getSickLeaveCycleEnd(
                    $staff,
                    $asOfDate
                );


            $annualEntitlement =
                $this->calculateSickLeaveEntitlement(
                    $staff
                );


            $accrued =
                $this->calculateSickLeaveAccrued(
                    $staff,
                    $asOfDate
                );
        }


        /*
        |--------------------------------------------------------------------------
        | FAMILY RESPONSIBILITY
        |--------------------------------------------------------------------------
        */

        elseif (
            $method === 'FIXED_ANNUAL'
        ) {

            $annualEntitlement =
                $this->calculateFamilyResponsibilityLeave(
                    $staff,
                    $asOfDate
                );


            $accrued =
                $annualEntitlement;
        }


        /*
        |--------------------------------------------------------------------------
        | UNPAID
        |--------------------------------------------------------------------------
        */

        elseif (
            $method === 'NONE'
        ) {

            $annualEntitlement =
                0.00;


            $monthlyAccrual =
                0.00;


            $accrued =
                0.00;
        }


        /*
        |--------------------------------------------------------------------------
        | LEAVE TAKEN
        |--------------------------------------------------------------------------
        */

        $taken =
            $this->getTakenLeave(
                $staff,
                $leaveType,
                $cycleStart,
                $cycleEnd,
                $asOfDate
            );


        /*
        |--------------------------------------------------------------------------
        | REMAINING
        |--------------------------------------------------------------------------
        */

        $remaining =
            max(
                0,
                $accrued
                -
                $taken
            );


        return [

            'leave_type_id' =>
                $leaveType->id,

            'leave_type' =>
                $leaveType->name,

            'leave_code' =>
                $leaveType->code,

            'calculation_method' =>
                $method,

            'leave_year_start' =>
                $cycleStart
                    ->format(
                        'Y-m-d'
                    ),

            'leave_year_end' =>
                $cycleEnd
                    ->format(
                        'Y-m-d'
                    ),

            'as_of_date' =>
                $asOfDate
                    ->format(
                        'Y-m-d'
                    ),

            'years_of_service' =>
                $yearsOfService,

            'annual_entitlement' =>
                round(
                    $annualEntitlement,
                    2
                ),

            'monthly_accrual' =>
                round(
                    $monthlyAccrual,
                    4
                ),

            'accrued' =>
                round(
                    $accrued,
                    2
                ),

            'taken' =>
                round(
                    $taken,
                    2
                ),

            'remaining' =>
                round(
                    $remaining,
                    2
                ),
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | TAKEN LEAVE
    |--------------------------------------------------------------------------
    */

    public function getTakenLeave(
        Staff $staff,
        LeaveType $leaveType,
        CarbonInterface $leaveYearStart,
        CarbonInterface $leaveYearEnd,
        ?CarbonInterface $asOfDate = null
    ): float {

        $leaveYearStart =
            $this
                ->parseDate(
                    $leaveYearStart
                )
                ->startOfDay();


        $leaveYearEnd =
            $this
                ->parseDate(
                    $leaveYearEnd
                )
                ->endOfDay();


        /*
        |--------------------------------------------------------------------------
        | DO NOT COUNT FUTURE LEAVE AFTER AS-OF DATE
        |--------------------------------------------------------------------------
        */

        if (
            $asOfDate
        ) {

            $asOfDate =
                $this
                    ->parseDate(
                        $asOfDate
                    )
                    ->endOfDay();


            if (
                $asOfDate->lt(
                    $leaveYearEnd
                )
            ) {

                $leaveYearEnd =
                    $asOfDate;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | APPROVED LEAVE RECORDS
        |--------------------------------------------------------------------------
        */

        $leaves =
            StaffLeave::query()

                ->where(
                    'staff_id',
                    $staff->id
                )

                ->where(
                    'leave_type_id',
                    $leaveType->id
                )

                ->where(
                    'status',
                    StaffLeave::STATUS_APPROVED
                )

                ->whereDate(
                    'start_date',
                    '<=',
                    $leaveYearEnd
                )

                ->whereDate(
                    'end_date',
                    '>=',
                    $leaveYearStart
                )

                ->get();


        $taken =
            0.00;


        foreach (
            $leaves
            as $leave
        ) {

            $leaveStart =
                $this
                    ->parseDate(
                        $leave->start_date
                    )
                    ->startOfDay();


            $leaveEnd =
                $this
                    ->parseDate(
                        $leave->end_date
                    )
                    ->endOfDay();


            /*
            |--------------------------------------------------------------------------
            | OVERLAP START
            |--------------------------------------------------------------------------
            */

            $overlapStart =
                $leaveStart->greaterThan(
                    $leaveYearStart
                )
                    ? $leaveStart
                    : $leaveYearStart;


            /*
            |--------------------------------------------------------------------------
            | OVERLAP END
            |--------------------------------------------------------------------------
            */

            $overlapEnd =
                $leaveEnd->lessThan(
                    $leaveYearEnd
                )
                    ? $leaveEnd
                    : $leaveYearEnd;


            if (
                $overlapStart->gt(
                    $overlapEnd
                )
            ) {

                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | ENTIRE RECORD INSIDE CYCLE
            |--------------------------------------------------------------------------
            */

            if (
                $leaveStart->gte(
                    $leaveYearStart
                )
                &&
                $leaveEnd->lte(
                    $leaveYearEnd
                )
            ) {

                $taken +=
                    (float)
                    $leave->days_taken;


                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | PARTIAL OVERLAP
            |--------------------------------------------------------------------------
            */

            $partialDays =
                $this->calculateLeaveDays(
                    $staff,
                    $overlapStart,
                    $overlapEnd
                );


            /*
            |--------------------------------------------------------------------------
            | HALF DAY LEAVE
            |--------------------------------------------------------------------------
            */

            if (
                isset(
                    $leave->duration_type
                )
                &&
                $leave->duration_type
                ===
                'half'
            ) {

                $partialDays *=
                    0.5;
            }


            $taken +=
                $partialDays;
        }


        return round(
            $taken,
            2
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CALCULATE LEAVE DAYS
    |--------------------------------------------------------------------------
    */

    public function calculateLeaveDays(
        Staff $staff,
        CarbonInterface|string $startDate,
        CarbonInterface|string $endDate
    ): float {

        $startDate =
            $this
                ->parseDate(
                    $startDate
                )
                ->startOfDay();


        $endDate =
            $this
                ->parseDate(
                    $endDate
                )
                ->startOfDay();


        if (
            $endDate->lt(
                $startDate
            )
        ) {

            return 0.00;
        }


        /*
        |--------------------------------------------------------------------------
        | WORK SCHEDULE
        |--------------------------------------------------------------------------
        */

        $schedule =
            $this->getWorkScheduleFractions(
                $staff
            );


        $days =
            0.00;


        $current =
            $startDate
                ->copy();


        while (
            $current->lte(
                $endDate
            )
        ) {

            $dayOfWeek =
                $current
                    ->dayOfWeekIso;


            $days +=
                $schedule[
                    $dayOfWeek
                ]
                ??
                0.00;


            $current
                ->addDay();
        }


        return round(
            $days,
            2
        );
    }


    /*
    |--------------------------------------------------------------------------
    | SCHEDULED WORKING DAYS
    |--------------------------------------------------------------------------
    */

    public function calculateScheduledWorkingDays(
        Staff $staff,
        CarbonInterface|string $startDate,
        CarbonInterface|string $endDate
    ): float {

        return
            $this->calculateLeaveDays(
                $staff,
                $startDate,
                $endDate
            );
    }


    /*
    |--------------------------------------------------------------------------
    | WORK SCHEDULE
    |--------------------------------------------------------------------------
    */

    protected function getWorkScheduleFractions(
        Staff $staff
    ): array {

        /*
        |--------------------------------------------------------------------------
        | DEFAULT MONDAY - FRIDAY
        |--------------------------------------------------------------------------
        */

        $default = [

            1 => 1.00,

            2 => 1.00,

            3 => 1.00,

            4 => 1.00,

            5 => 1.00,

            6 => 0.00,

            7 => 0.00,

        ];


        try {

            /*
            |--------------------------------------------------------------------------
            | STAFF RELATIONSHIP
            |--------------------------------------------------------------------------
            */

            if (
                !method_exists(
                    $staff,
                    'workSchedule'
                )
            ) {

                return
                    $default;
            }


            /*
            |--------------------------------------------------------------------------
            | LOAD SCHEDULE IF NOT ALREADY LOADED
            |--------------------------------------------------------------------------
            */

            if (
                !$staff->relationLoaded(
                    'workSchedule'
                )
            ) {

                $staff->load(
                    'workSchedule.days'
                );
            }


            $schedule =
                $staff
                    ->workSchedule;


            if (
                !$schedule
            ) {

                return
                    $default;
            }


            /*
            |--------------------------------------------------------------------------
            | MAKE SURE DAYS ARE LOADED
            |--------------------------------------------------------------------------
            */

            if (
                !$schedule->relationLoaded(
                    'days'
                )
            ) {

                $schedule->load(
                    'days'
                );
            }


            $scheduleDays =
                $schedule
                    ->days;


            if (
                !$scheduleDays
                ||
                $scheduleDays->isEmpty()
            ) {

                return
                    $default;
            }


            /*
            |--------------------------------------------------------------------------
            | BUILD FRACTIONS
            |--------------------------------------------------------------------------
            */

            $result =
                [];


            foreach (
                $scheduleDays
                as $scheduleDay
            ) {

                $day =
                    (int)
                    $scheduleDay
                        ->day_of_week;


                $fraction =
                    (float)
                    $scheduleDay
                        ->day_fraction;


                if (
                    $day >= 1
                    &&
                    $day <= 7
                ) {

                    $result[
                        $day
                    ] =
                        $fraction;
                }
            }


            /*
            |--------------------------------------------------------------------------
            | ENSURE ALL 7 DAYS EXIST
            |--------------------------------------------------------------------------
            */

            for (
                $day = 1;
                $day <= 7;
                $day++
            ) {

                if (
                    !array_key_exists(
                        $day,
                        $result
                    )
                ) {

                    $result[
                        $day
                    ] =
                        0.00;
                }
            }


            /*
            |--------------------------------------------------------------------------
            | SORT BY DAY NUMBER
            |--------------------------------------------------------------------------
            */

            ksort(
                $result
            );


            return
                $result;

        } catch (
            \Throwable $e
        ) {

            return
                $default;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | DATE NORMALIZATION
    |--------------------------------------------------------------------------
    */

    protected function parseDate(
        CarbonInterface|string|\DateTimeInterface $date
    ): Carbon {

        if (
            $date instanceof Carbon
        ) {

            return
                $date
                    ->copy();
        }


        if (
            $date instanceof CarbonInterface
        ) {

            return
                Carbon::instance(
                    $date
                );
        }


        if (
            $date instanceof \DateTimeInterface
        ) {

            return
                Carbon::instance(
                    $date
                );
        }


        return
            Carbon::parse(
                $date
            );
    }
}
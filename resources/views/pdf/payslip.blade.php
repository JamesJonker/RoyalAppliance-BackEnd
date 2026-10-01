<!DOCTYPE html>
<html>

<head>

    <meta charset="utf-8">

    <title>
        Payslip
    </title>

    <style>

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #000;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        th,
        td {
            border: 1px solid #444;
            padding: 5px;
            text-align: left;
        }

        th {
            background-color: #ddd;
        }

        .no-border {
            border: none !important;
        }

        .center {
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .loan-paid {
            font-weight: bold;
        }

        .loan-pending {
            font-weight: bold;
        }

        .section-title {
            margin-top: 18px;
            margin-bottom: 6px;
            font-size: 14px;
        }

        .leave-section-title {
            margin-top: 18px;
            margin-bottom: 6px;
            font-size: 14px;
        }

        .leave-half-day {
            font-weight: bold;
        }

        .leave-full-day {
            font-weight: normal;
        }

        .leave-summary-note {
            font-size: 10px;
            margin-top: 4px;
            margin-bottom: 10px;
        }

        .small-text {
            font-size: 10px;
        }

        .final-pay-header {
            background-color: #eee;
            font-weight: bold;
        }

    </style>

</head>


<body>


<!-- ============================================================
     COMPANY HEADER
============================================================ -->

<table
    style="
        width: 100%;
        margin-bottom: 20px;
        border: none;
        border-collapse: collapse;
    "
>

    <tr>

        <td
            style="
                width: 70%;
                border: none;
                vertical-align: top;
            "
        >

            <strong>
                ROYAL APPLIANCES
            </strong>

            <br>

            SHOP 36

            <br>

            CHINAGATE CENTRE

            <br>

            CHLOORKOP

            <br>

            KEMPTONPARK

            <br>

            Tel No: 0796934583

            <br>

            <strong>
                REG: 2009/060427/23
            </strong>

        </td>


        <td
            style="
                width: 30%;
                text-align: right;
                border: none;
                vertical-align: top;
            "
        >

            <img
                src="{{
                    public_path(
                        'img/protec_logo.png'
                    )
                }}"
                alt="Logo"
                style="height: 100px;"
            >

        </td>

    </tr>

</table>


<!-- ============================================================
     EMPLOYEE DETAILS
============================================================ -->

<table>

    <tr>

        <th colspan="2">
            Employee Details
        </th>

        <th colspan="2">
            Pay Info
        </th>

    </tr>


    <tr>

        <td>
            <strong>
                Name
            </strong>
        </td>

        <td>

            {{ $staff->first_name }}
            {{ $staff->last_name }}

        </td>


        <td>
            <strong>
                Pay Date
            </strong>
        </td>

        <td>

            {{
                $pay_date->format(
                    'd.m.Y'
                )
            }}

        </td>

    </tr>


    <tr>

        <td>
            <strong>
                Position
            </strong>
        </td>

        <td>
            {{ $staff->position }}
        </td>


        <td>
            <strong>
                Month No
            </strong>
        </td>

        <td>

            {{
                str_pad(
                    $payslip->month,
                    2,
                    '0',
                    STR_PAD_LEFT
                )
            }}

        </td>

    </tr>


    <tr>

        <td>
            <strong>
                Employee ID
            </strong>
        </td>

        <td>
            {{ $staff->employee_number }}
        </td>


        <td>
            <strong>
                Period Ending
            </strong>
        </td>

        <td>

            {{
                $period_ending->format(
                    'd.m.Y'
                )
            }}

        </td>

    </tr>


    <tr>

        <td>
            <strong>
                Employee ID Number
            </strong>
        </td>

        <td>
            {{ $staff->Id_number }}
        </td>


        <td>
            <strong>
                Payslip Type
            </strong>
        </td>

        <td>

            @if($payslip->is_prorated)

                Final / Prorated

            @else

                Normal Monthly

            @endif

        </td>

    </tr>


    @if(
        $payslip->is_prorated
        &&
        $payslip->termination_date
    )

        <tr>

            <td>
                <strong>
                    Termination Date
                </strong>
            </td>

            <td>

                {{
                    \Carbon\Carbon::parse(
                        $payslip->termination_date
                    )->format(
                        'd.m.Y'
                    )
                }}

            </td>

            <td class="no-border"></td>
            <td class="no-border"></td>

        </tr>

    @endif


    <tr>

        <td>
            <strong>
                Address
            </strong>
        </td>

        <td>

            {{ $staff->street }}
            {{ $staff->complex }}
            {{ $staff->suburb }}
            {{ $staff->postal_code }}

        </td>

        <td class="no-border"></td>
        <td class="no-border"></td>

    </tr>

</table>


<!-- ============================================================
     FINAL PAY CALCULATION
============================================================ -->

@if($payslip->is_prorated)

    @php

        $totalUif =
            (float) (
                $payslip->uif
                ?? 0
            );


        $leaveUif =
            (float) (
                $payslip->leave_uif
                ?? 0
            );


        $workedUif =
            max(
                0,
                $totalUif
                -
                $leaveUif
            );


        $grossLeavePay =
            (float) (
                $payslip->gross_leave_pay
                ?? 0
            );


        $leavePaye =
            (float) (
                $payslip->leave_paye
                ?? 0
            );


        $netLeavePay =
            (float) (
                $payslip->net_leave_pay
                ?? 0
            );

    @endphp


    <h4 class="section-title">
        Final Pay Calculation
    </h4>


    <table>

        <tr class="final-pay-header">

            <th>
                Description
            </th>

            <th>
                Quantity
            </th>

            <th>
                Rate
            </th>

            <th>
                Gross
            </th>

        </tr>


        <tr>

            <td>
                Days Worked
            </td>

            <td>

                {{
                    number_format(
                        (float)
                        $payslip->days_worked,
                        2
                    )
                }}

            </td>

            <td>

                R
                {{
                    number_format(
                        (float)
                        $payslip->daily_rate,
                        2
                    )
                }}

            </td>

            <td>

                R
                {{
                    number_format(
                        (float)
                        $payslip->worked_gross,
                        2
                    )
                }}

            </td>

        </tr>


        <tr>

            <td>
                Leave Days Due
            </td>

            <td>

                {{
                    number_format(
                        (float)
                        $payslip->leave_days_due,
                        2
                    )
                }}

            </td>

            <td>

                R
                {{
                    number_format(
                        (float)
                        $payslip->leave_daily_rate,
                        2
                    )
                }}

            </td>

            <td>

                R
                {{
                    number_format(
                        $grossLeavePay,
                        2
                    )
                }}

            </td>

        </tr>

    </table>


    <!-- ========================================================
         LEAVE PAY BREAKDOWN
    ========================================================= -->

    <table>

        <tr>

            <th>
                Gross Leave
            </th>

            <th>
                PAYE on Leave Allocation
            </th>

            <th>
                UIF on Leave Allocation
            </th>

            <th>
                Net Leave
            </th>

        </tr>


        <tr>

            <td>

                R
                {{
                    number_format(
                        $grossLeavePay,
                        2
                    )
                }}

            </td>

            <td>

                R
                {{
                    number_format(
                        $leavePaye,
                        2
                    )
                }}

            </td>

            <td>

                R
                {{
                    number_format(
                        $leaveUif,
                        2
                    )
                }}

            </td>

            <td>

                <strong>

                    R
                    {{
                        number_format(
                            $netLeavePay,
                            2
                        )
                    }}

                </strong>

            </td>

        </tr>

    </table>

@endif


<!-- ============================================================
     EARNINGS / DEDUCTIONS
============================================================ -->

<h4 class="section-title">
    Earnings & Deductions
</h4>


@if($payslip->is_prorated)

    @php

        /*
        |--------------------------------------------------------------------------
        | FINAL PAY UIF BREAKDOWN
        |--------------------------------------------------------------------------
        |
        | Total UIF is stored in:
        |
        | $payslip->uif
        |
        | Leave UIF is stored separately in:
        |
        | $payslip->leave_uif
        |
        | Worked UIF =
        |
        | total UIF - leave UIF
        |
        */

        $totalUif =
            (float) (
                $payslip->uif
                ?? 0
            );


        $leaveUif =
            (float) (
                $payslip->leave_uif
                ?? 0
            );


        $workedUif =
            max(
                0,
                $totalUif
                -
                $leaveUif
            );

    @endphp


    <table>

        <tr>

            <th
                colspan="2"
                style="width: 50%;"
            >
                Earnings
            </th>

            <th
                colspan="2"
                style="width: 50%;"
            >
                Deductions
            </th>

        </tr>


        <!-- ====================================================
             WORKED PAY / WORKED UIF
        ===================================================== -->

        <tr>

            <td>
                Worked Pay
            </td>

            <td>

                R
                {{
                    number_format(
                        (float)
                        $payslip->worked_gross,
                        2
                    )
                }}

            </td>


            <td>
                Worked UIF
            </td>

            <td>

                R
                {{
                    number_format(
                        $workedUif,
                        2
                    )
                }}

            </td>

        </tr>


        <!-- ====================================================
             LEAVE PAY / LEAVE UIF
        ===================================================== -->

        <tr>

            <td>
                Leave Pay
            </td>

            <td>

                R
                {{
                    number_format(
                        (float)
                        $payslip->gross_leave_pay,
                        2
                    )
                }}

            </td>


            <td>
                Leave UIF
            </td>

            <td>

                R
                {{
                    number_format(
                        $leaveUif,
                        2
                    )
                }}

            </td>

        </tr>


        <!-- ====================================================
             GROSS / TOTAL UIF
        ===================================================== -->

        <tr>

            <td>

                <strong>
                    Gross Pay
                </strong>

            </td>

            <td>

                <strong>

                    R
                    {{
                        number_format(
                            (float)
                            $payslip->gross_salary,
                            2
                        )
                    }}

                </strong>

            </td>


            <td>

                <strong>
                    Total UIF
                </strong>

            </td>

            <td>

                <strong>

                    R
                    {{
                        number_format(
                            $totalUif,
                            2
                        )
                    }}

                </strong>

            </td>

        </tr>


        <!-- ====================================================
             PAYE
        ===================================================== -->

        <tr>

            <td
                colspan="2"
                class="no-border"
            >
            </td>

            <td>
                PAYE
            </td>

            <td>

                R
                {{
                    number_format(
                        (float)
                        $payslip->paye,
                        2
                    )
                }}

            </td>

        </tr>


        <!-- ====================================================
             LOAN DEDUCTIONS
        ===================================================== -->

        <tr>

            <td
                colspan="2"
                class="no-border"
            >
            </td>

            <td>
                Loan Deductions
            </td>

            <td>

                R
                {{
                    number_format(
                        (float)
                        $loanDeductions,
                        2
                    )
                }}

            </td>

        </tr>


        <!-- ====================================================
             TOTAL DEDUCTIONS
        ===================================================== -->

        <tr>

            <td
                colspan="2"
                class="no-border"
            >
            </td>

            <td>

                <strong>
                    Total Deductions
                </strong>

            </td>

            <td>

                <strong>

                    R
                    {{
                        number_format(
                            (float)
                            $payslip->deductions,
                            2
                        )
                    }}

                </strong>

            </td>

        </tr>


        <!-- ====================================================
             NET FINAL PAY
        ===================================================== -->

        <tr>

            <td
                colspan="2"
                class="no-border"
            >
            </td>

            <td>

                <strong>
                    Net Final Pay
                </strong>

            </td>

            <td>

                <strong>

                    R
                    {{
                        number_format(
                            (float)
                            $payslip->net_salary,
                            2
                        )
                    }}

                </strong>

            </td>

        </tr>

    </table>


@else


    <!-- ========================================================
         NORMAL MONTHLY PAYSLIP
    ========================================================= -->

    <table>

        <tr>

            <th
                colspan="2"
                style="width: 50%;"
            >
                Earnings
            </th>

            <th
                colspan="2"
                style="width: 50%;"
            >
                Deductions
            </th>

        </tr>


        <tr>

            <td>
                Basic Salary
            </td>

            <td>

                R
                {{
                    number_format(
                        (float)
                        $payslip->gross_salary,
                        2
                    )
                }}

            </td>


            <td>
                PAYE
            </td>

            <td>

                R
                {{
                    number_format(
                        (float)
                        $payslip->paye,
                        2
                    )
                }}

            </td>

        </tr>


        <tr>

            <td>
                Overtime
            </td>

            <td>
                R 0.00
            </td>


            <td>
                UIF
            </td>

            <td>

                R
                {{
                    number_format(
                        (float)
                        $payslip->uif,
                        2
                    )
                }}

            </td>

        </tr>


        <tr>

            <td
                colspan="2"
                class="right"
            >

                <strong>
                    Gross Pay:
                </strong>

                R
                {{
                    number_format(
                        (float)
                        $payslip->gross_salary,
                        2
                    )
                }}

            </td>


            <td>
                Loan Deductions
            </td>

            <td>

                R
                {{
                    number_format(
                        (float)
                        $loanDeductions,
                        2
                    )
                }}

            </td>

        </tr>


        <tr>

            <td
                colspan="2"
                class="no-border"
            >
            </td>

            <td>

                <strong>
                    Total Deductions:
                </strong>

            </td>

            <td>

                <strong>

                    R
                    {{
                        number_format(
                            (float)
                            $payslip->deductions,
                            2
                        )
                    }}

                </strong>

            </td>

        </tr>


        <tr>

            <td
                colspan="2"
                class="no-border"
            >
            </td>

            <td>

                <strong>
                    Net Pay:
                </strong>

            </td>

            <td>

                <strong>

                    R
                    {{
                        number_format(
                            (float)
                            $payslip->net_salary,
                            2
                        )
                    }}

                </strong>

            </td>

        </tr>

    </table>

@endif


<!-- ============================================================
     YEAR TO DATE
============================================================ -->

<h4 class="section-title">
    Year To Date
</h4>


<table>

    <tr>

        <th>
            YTD Gross
        </th>

        <th>
            YTD Deductions
        </th>

        <th>
            YTD Net Pay
        </th>

    </tr>


    <tr>

        <td>

            R
            {{
                number_format(
                    (float)
                    $payslip->ytd_gross,
                    2
                )
            }}

        </td>


        <td>

            R
            {{
                number_format(
                    (float)
                    $payslip->ytd_deductions,
                    2
                )
            }}

        </td>


        <td>

            R
            {{
                number_format(
                    (float)
                    $payslip->ytd_net,
                    2
                )
            }}

        </td>

    </tr>

</table>


<!-- ============================================================
     LEAVE BALANCES
============================================================ -->

@if(
    isset(
        $leaveBalances
    )
    &&
    count(
        $leaveBalances
    )
)

    <h4 class="leave-section-title">
        Leave Balances
    </h4>


    <table>

        <thead>

            <tr>

                <th>
                    Leave Type
                </th>

                <th>
                    Entitlement
                </th>

                <th>
                    Accrued
                </th>

                <th>
                    Taken
                </th>

                <th>
                    Remaining
                </th>

            </tr>

        </thead>


        <tbody>

            @foreach(
                $leaveBalances
                as $balance
            )

                <tr>

                    <td>

                        {{
                            $balance['leave_type']
                            ??
                            'Unknown'
                        }}


                        @if(
                            !empty(
                                $balance['leave_code']
                            )
                        )

                            <span class="small-text">

                                (
                                    {{
                                        $balance['leave_code']
                                    }}
                                )

                            </span>

                        @endif

                    </td>


                    <td>

                        {{
                            number_format(
                                (float)
                                (
                                    $balance['annual_entitlement']
                                    ??
                                    0
                                ),
                                2
                            )
                        }}

                    </td>


                    <td>

                        {{
                            number_format(
                                (float)
                                (
                                    $balance['accrued']
                                    ??
                                    0
                                ),
                                2
                            )
                        }}

                    </td>


                    <td>

                        {{
                            number_format(
                                (float)
                                (
                                    $balance['taken']
                                    ??
                                    0
                                ),
                                2
                            )
                        }}

                    </td>


                    <td>

                        {{
                            number_format(
                                (float)
                                (
                                    $balance['remaining']
                                    ??
                                    0
                                ),
                                2
                            )
                        }}

                    </td>

                </tr>

            @endforeach

        </tbody>

    </table>


    <div class="leave-summary-note">

        Leave balance calculated as at

        {{
            $period_ending->format(
                'd.m.Y'
            )
        }}.

    </div>

@endif


<!-- ============================================================
     LEAVE TAKEN DURING PAY PERIOD
============================================================ -->

<h4 class="leave-section-title">
    Leave Taken During Pay Period
</h4>


@if(
    isset(
        $leaveTakenForPeriod
    )
    &&
    count(
        $leaveTakenForPeriod
    )
)

    <table>

        <thead>

            <tr>

                <th>
                    Leave Type
                </th>

                <th>
                    Start Date
                </th>

                <th>
                    End Date
                </th>

                <th>
                    Duration
                </th>

                <th>
                    Days
                </th>

                <th>
                    Remaining
                </th>

                <th>
                    Reason
                </th>

            </tr>

        </thead>


        <tbody>

            @foreach(
                $leaveTakenForPeriod
                as $leave
            )

                @php

                    $durationType =
                        $leave['duration_type']
                        ??
                        'full';


                    $halfDayPeriod =
                        $leave['half_day_period']
                        ??
                        null;


                    $leaveRemaining =
                        0;


                    if (
                        isset(
                            $leaveBalances
                        )
                    ) {

                        foreach (
                            $leaveBalances
                            as $balance
                        ) {

                            if (
                                isset(
                                    $balance['leave_type_id']
                                )
                                &&
                                (int)
                                $balance['leave_type_id']
                                ===
                                (int)
                                $leave['leave_type_id']
                            ) {

                                $leaveRemaining =
                                    (float) (
                                        $balance['remaining']
                                        ??
                                        0
                                    );

                                break;
                            }
                        }
                    }

                @endphp


                <tr>

                    <td>

                        {{
                            $leave['leave_type']
                            ??
                            'Unknown'
                        }}


                        @if(
                            !empty(
                                $leave['leave_code']
                            )
                        )

                            <span class="small-text">

                                (
                                    {{
                                        $leave['leave_code']
                                    }}
                                )

                            </span>

                        @endif

                    </td>


                    <td>

                        {{
                            \Carbon\Carbon::parse(
                                $leave['start_date']
                            )->format(
                                'd.m.Y'
                            )
                        }}

                    </td>


                    <td>

                        {{
                            \Carbon\Carbon::parse(
                                $leave['end_date']
                            )->format(
                                'd.m.Y'
                            )
                        }}

                    </td>


                    <td>

                        @if(
                            $durationType
                            ===
                            'half'
                        )

                            <span class="leave-half-day">

                                Half Day

                                @if(
                                    $halfDayPeriod
                                )

                                    -
                                    {{
                                        ucfirst(
                                            $halfDayPeriod
                                        )
                                    }}

                                @endif

                            </span>

                        @else

                            <span class="leave-full-day">
                                Full Day
                            </span>

                        @endif

                    </td>


                    <td>

                        {{
                            number_format(
                                (float)
                                (
                                    $leave['days_taken']
                                    ??
                                    0
                                ),
                                2
                            )
                        }}

                    </td>


                    <td>

                        {{
                            number_format(
                                $leaveRemaining,
                                2
                            )
                        }}

                    </td>


                    <td>

                        {{
                            $leave['reason']
                            ??
                            '—'
                        }}

                    </td>

                </tr>

            @endforeach

        </tbody>


        <tfoot>

            <tr>

                <td
                    colspan="4"
                    class="right"
                >

                    <strong>
                        Total Leave Taken:
                    </strong>

                </td>


                <td>

                    <strong>

                        {{
                            number_format(
                                (float) (
                                    $totalLeaveTakenForPeriod
                                    ??
                                    0
                                ),
                                2
                            )
                        }}

                    </strong>

                </td>


                <td></td>
                <td></td>

            </tr>

        </tfoot>

    </table>

@else

    <table>

        <tr>

            <td
                colspan="7"
                class="center"
            >

                No approved leave taken during this pay period.

            </td>

        </tr>

    </table>

@endif


<!-- ============================================================
     LOAN DETAILS
============================================================ -->

@if(
    $loans->count()
)

    <h4 class="section-title">
        Loan Details
    </h4>


    @foreach(
        $loans
        as $loan
    )

        @php

            /*
            |--------------------------------------------------------------------------
            | LOAN START MONTH
            |--------------------------------------------------------------------------
            */

            $startMonth =
                \Carbon\Carbon::parse(
                    $loan->start_month
                )->startOfMonth();


            /*
            |--------------------------------------------------------------------------
            | CURRENT PAYSLIP MONTH
            |--------------------------------------------------------------------------
            */

            $currentMonth =
                $pay_date
                    ->copy()
                    ->startOfMonth();


            /*
            |--------------------------------------------------------------------------
            | LOAN MONTH NUMBER
            |--------------------------------------------------------------------------
            */

            $loanMonthNumber =
                null;


            if (
                $currentMonth->gte(
                    $startMonth
                )
            ) {

                $loanMonthNumber =
                    $startMonth
                        ->diffInMonths(
                            $currentMonth
                        )
                    +
                    1;
            }


            /*
            |--------------------------------------------------------------------------
            | CURRENT REPAYMENT
            |--------------------------------------------------------------------------
            */

            $currentRepayment =
                null;


            if (
                $loanMonthNumber
                !==
                null
            ) {

                $currentRepayment =
                    $loan
                        ->repayments
                        ->where(
                            'month_number',
                            $loanMonthNumber
                        )
                        ->first();
            }


            /*
            |--------------------------------------------------------------------------
            | CURRENT DEDUCTION
            |--------------------------------------------------------------------------
            */

            $currentDeduction =
                0;


            if (
                $currentRepayment
            ) {

                $currentDeduction =
                    (float)
                    $currentRepayment
                        ->deduction_amount;
            }


            /*
            |--------------------------------------------------------------------------
            | LOAN TOTAL
            |--------------------------------------------------------------------------
            */

            $loanTotal =
                (float)
                $loan->amount;


            /*
            |--------------------------------------------------------------------------
            | PAID TO DATE
            |--------------------------------------------------------------------------
            */

            $paidBeforeThisMonth =
                0;


            if (
                $currentRepayment
            ) {

                $paidBeforeThisMonth =
                    $loan
                        ->repayments
                        ->filter(
                            function (
                                $repayment
                            ) use (
                                $loanMonthNumber
                            ) {

                                return
                                    $repayment
                                        ->month_number
                                    <=
                                    $loanMonthNumber
                                    &&
                                    $repayment
                                        ->status
                                    ===
                                    'paid';

                            }
                        )
                        ->sum(
                            'deduction_amount'
                        );
            }


            /*
            |--------------------------------------------------------------------------
            | REMAINING AFTER CURRENT
            |--------------------------------------------------------------------------
            */

            $remainingAfterCurrent =
                max(
                    0,
                    $loanTotal
                    -
                    $paidBeforeThisMonth
                );


            $loanStatus =
                $loan->status;

        @endphp


        <!-- ====================================================
             LOAN SUMMARY
        ===================================================== -->

        <table>

            <thead>

                <tr>

                    <th>
                        Loan ID
                    </th>

                    <th>
                        Status
                    </th>

                    <th>
                        Loan Amount
                    </th>

                    <th>
                        Start Month
                    </th>

                    <th>
                        Current Month
                    </th>

                    <th>
                        Deduction This Month
                    </th>

                    <th>
                        Remaining After Deduction
                    </th>

                </tr>

            </thead>


            <tbody>

                <tr>

                    <td>
                        {{ $loan->id }}
                    </td>


                    <td>
                        {{
                            ucfirst(
                                $loanStatus
                            )
                        }}
                    </td>


                    <td>

                        R
                        {{
                            number_format(
                                $loanTotal,
                                2
                            )
                        }}

                    </td>


                    <td>

                        {{
                            $startMonth->format(
                                'F Y'
                            )
                        }}

                    </td>


                    <td>

                        @if(
                            $loanMonthNumber
                        )

                            Month
                            {{ $loanMonthNumber }}

                        @else

                            Not Started

                        @endif

                    </td>


                    <td>

                        @if(
                            $currentRepayment
                        )

                            R
                            {{
                                number_format(
                                    $currentDeduction,
                                    2
                                )
                            }}

                        @else

                            @if(
                                $currentMonth->lt(
                                    $startMonth
                                )
                            )

                                Starts
                                {{
                                    $startMonth->format(
                                        'F Y'
                                    )
                                }}

                            @else

                                No repayment

                            @endif

                        @endif

                    </td>


                    <td>

                        R
                        {{
                            number_format(
                                $remainingAfterCurrent,
                                2
                            )
                        }}

                    </td>

                </tr>

            </tbody>

        </table>


        <!-- ====================================================
             REPAYMENT SCHEDULE
        ===================================================== -->

        <h4>
            Repayment Schedule
        </h4>


        <table>

            <thead>

                <tr>

                    <th>
                        Month
                    </th>

                    <th>
                        Date
                    </th>

                    <th>
                        Deduction
                    </th>

                    <th>
                        Status
                    </th>

                    <th>
                        Remaining
                    </th>

                </tr>

            </thead>


            <tbody>

                @php

                    $runningRemaining =
                        (float)
                        $loan->amount;

                @endphp


                @foreach(
                    $loan->repayments
                        ->sortBy(
                            'month_number'
                        )
                    as $repayment
                )

                    @php

                        $repaymentDate =
                            $startMonth
                                ->copy()
                                ->addMonths(
                                    $repayment
                                        ->month_number
                                    -
                                    1
                                );


                        $runningRemaining =
                            max(
                                0,
                                $runningRemaining
                                -
                                (float)
                                $repayment
                                    ->deduction_amount
                            );

                    @endphp


                    <tr>

                        <td>
                            {{ $repayment->month_number }}
                        </td>


                        <td>

                            {{
                                $repaymentDate->format(
                                    'F Y'
                                )
                            }}

                        </td>


                        <td>

                            R
                            {{
                                number_format(
                                    $repayment
                                        ->deduction_amount,
                                    2
                                )
                            }}

                        </td>


                        <td>

                            @if(
                                $repayment->status
                                ===
                                'paid'
                            )

                                <span class="loan-paid">
                                    Paid
                                </span>

                            @elseif(
                                $repayment->status
                                ===
                                'partial'
                            )

                                <span class="loan-pending">
                                    Partial
                                </span>

                            @else

                                <span class="loan-pending">
                                    Pending
                                </span>

                            @endif

                        </td>


                        <td>

                            R
                            {{
                                number_format(
                                    $runningRemaining,
                                    2
                                )
                            }}

                        </td>

                    </tr>

                @endforeach

            </tbody>

        </table>

    @endforeach

@endif


<!-- ============================================================
     FOOTER
============================================================ -->

<p class="center">

    DOCUMENT ELECTRONICALLY GENERATED
    • NO SIGNATURE REQUIRED

</p>


</body>

</html>
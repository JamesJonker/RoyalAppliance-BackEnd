<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\PayrollTaxBracket;
use App\Models\PayrollTaxRebate;
use App\Models\PayrollTaxYear;
use App\Models\Payslip;
use App\Models\Staff;
use App\Models\StatutorySetting;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class PayrollService
{
    /**
     * Get the payroll tax year applicable to a date.
     */
    public function getTaxYear(?Carbon $date = null): PayrollTaxYear
    {
        $date = $date ?? Carbon::today();

        $taxYear = PayrollTaxYear::forDate($date);

        if (!$taxYear) {
            throw new InvalidArgumentException(
                'No active payroll tax year was found for ' . $date->format('Y-m-d')
            );
        }

        return $taxYear;
    }

    /**
     * Validate that an employee has the minimum information
     * required to calculate payroll.
     */
    public function validateEmployeeForPayroll(
        Staff $staff,
        ?Carbon $date = null
    ): array {
        $date = $date ?? Carbon::today();

        $errors = [];

        /*
         * Date of birth is mandatory because PAYE rebates
         * depend on the employee's age.
         */
        if (!$staff->date_of_birth) {
            $errors[] = 'Date of birth is required.';
        }

        /*
         * Salary must exist and cannot be negative.
         */
        if ($staff->salary === null || $staff->salary === '') {
            $errors[] = 'Salary is required.';
        } elseif ((float) $staff->salary < 0) {
            $errors[] = 'Salary cannot be negative.';
        }

        /*
         * Verify that a tax year exists for the payroll date.
         */
        $taxYear = PayrollTaxYear::forDate($date);

        if (!$taxYear) {
            $errors[] = 'No active payroll tax year exists for '
                . $date->format('Y-m-d') . '.';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Ensure that an employee is valid for payroll.
     */
    public function ensureEmployeeIsValidForPayroll(
        Staff $staff,
        ?Carbon $date = null
    ): void {
        $validation = $this->validateEmployeeForPayroll(
            $staff,
            $date
        );

        if (!$validation['valid']) {
            throw new InvalidArgumentException(
                'Employee '
                . ($staff->employee_number ?: $staff->id)
                . ' cannot be processed for payroll: '
                . implode(' ', $validation['errors'])
            );
        }
    }

    /**
     * Calculate the employee's age on a specific date.
     */
    public function getEmployeeAge(
        Staff $staff,
        ?Carbon $date = null
    ): int {
        if (!$staff->date_of_birth) {
            throw new InvalidArgumentException(
                "Employee {$staff->id} does not have a date of birth."
            );
        }

        $date = $date ?? Carbon::today();

        return Carbon::parse($staff->date_of_birth)->age;
    }

    /**
     * Calculate employee UIF.
     */
    public function calculateUIFEmployee(
        float $gross,
        ?Carbon $date = null
    ): float {
        $rate = StatutorySetting::getDecimal(
            'uif_employee_rate',
            $date
        );

        $ceiling = StatutorySetting::getDecimal(
            'uif_monthly_ceiling',
            $date
        );

        $uifableAmount = min(
            max($gross, 0),
            $ceiling
        );

        return round($uifableAmount * $rate, 2);
    }

    /**
     * Calculate employer UIF.
     */
    public function calculateUIFEmployer(
        float $gross,
        ?Carbon $date = null
    ): float {
        $rate = StatutorySetting::getDecimal(
            'uif_employer_rate',
            $date
        );

        $ceiling = StatutorySetting::getDecimal(
            'uif_monthly_ceiling',
            $date
        );

        $uifableAmount = min(
            max($gross, 0),
            $ceiling
        );

        return round($uifableAmount * $rate, 2);
    }

    /**
     * Calculate employer SDL.
     */
    public function calculateSDL(
        float $gross,
        bool $employerExempt = false,
        ?Carbon $date = null
    ): float {
        if ($employerExempt) {
            return 0.00;
        }

        $rate = StatutorySetting::getDecimal(
            'sdl_employer_rate',
            $date
        );

        return round(max($gross, 0) * $rate, 2);
    }

    /**
     * Calculate annual tax before rebates.
     */
    public function calculateAnnualTaxBeforeRebate(
        float $annualTaxableIncome,
        PayrollTaxYear $taxYear
    ): float {
        $income = max($annualTaxableIncome, 0);

        $bracket = PayrollTaxBracket::query()
            ->where('payroll_tax_year_id', $taxYear->id)
            ->where('lower_limit', '<=', $income)
            ->where(function ($query) use ($income) {
                $query->whereNull('upper_limit')
                    ->orWhere('upper_limit', '>=', $income);
            })
            ->orderBy('bracket_order')
            ->first();

        if (!$bracket) {
            return 0.00;
        }

        $tax = (float) $bracket->base_tax;

        $tax += (
            $income - (float) $bracket->threshold
        ) * (float) $bracket->marginal_rate;

        return round(max($tax, 0), 2);
    }

    /**
     * Get applicable PAYE rebate based on age.
     */
    public function getApplicableRebate(
        int $age,
        PayrollTaxYear $taxYear
    ): float {
        $rebates = PayrollTaxRebate::query()
            ->where('payroll_tax_year_id', $taxYear->id)
            ->orderBy('minimum_age')
            ->get();

        $rebate = 0.00;

        foreach ($rebates as $item) {
            if ($age >= (int) $item->minimum_age) {
                $rebate += (float) $item->rebate_amount;
            }
        }

        return round($rebate, 2);
    }

    /**
     * Calculate annual PAYE after rebate.
     */
    public function calculateAnnualPAYE(
        float $annualTaxableIncome,
        int $age,
        PayrollTaxYear $taxYear
    ): float {
        $taxBeforeRebate = $this->calculateAnnualTaxBeforeRebate(
            $annualTaxableIncome,
            $taxYear
        );

        $rebate = $this->getApplicableRebate(
            $age,
            $taxYear
        );

        return round(
            max($taxBeforeRebate - $rebate, 0),
            2
        );
    }

    /**
     * Calculate monthly PAYE.
     */
    public function calculateMonthlyPAYE(
        float $monthlyTaxableIncome,
        int $age,
        ?Carbon $date = null
    ): float {
        $date = $date ?? Carbon::today();

        $taxYear = $this->getTaxYear($date);

        $annualTaxableIncome = max(
            $monthlyTaxableIncome,
            0
        ) * 12;

        $annualPAYE = $this->calculateAnnualPAYE(
            $annualTaxableIncome,
            $age,
            $taxYear
        );

        return round($annualPAYE / 12, 2);
    }

    /**
     * Determine loan repayment month number.
     */
    public function getLoanMonthNumber(
        Loan $loan,
        Carbon $payrollDate
    ): int {
        if (!$loan->start_month) {
            return 0;
        }

        $startMonth = Carbon::parse($loan->start_month)
            ->startOfMonth();

        $payrollMonth = $payrollDate
            ->copy()
            ->startOfMonth();

        if ($payrollMonth->lt($startMonth)) {
            return 0;
        }

        return $startMonth->diffInMonths($payrollMonth) + 1;
    }

    /**
     * Get pending loan repayments for an employee.
     */
    public function getEmployeeLoanRepayments(
        Staff $staff,
        ?Carbon $payrollDate = null
    ): Collection {
        $payrollDate = $payrollDate ?? Carbon::today();

        $loans = Loan::query()
            ->where('staff_id', $staff->id)
            ->where('status', 'active')
            ->get();

        $repayments = collect();

        foreach ($loans as $loan) {
            $monthNumber = $this->getLoanMonthNumber(
                $loan,
                $payrollDate
            );

            if ($monthNumber <= 0) {
                continue;
            }

            $repayment = LoanRepayment::query()
                ->where('loan_id', $loan->id)
                ->where('month_number', $monthNumber)
                ->where('status', 'pending')
                ->first();

            if (!$repayment) {
                continue;
            }

            $repayments->push($repayment);
        }

        return $repayments;
    }

    /**
     * Calculate total pending loan deduction.
     */
    public function calculateLoanDeduction(
        Staff $staff,
        ?Carbon $payrollDate = null
    ): float {
        $repayments = $this->getEmployeeLoanRepayments(
            $staff,
            $payrollDate
        );

        return round(
            (float) $repayments->sum(
                fn ($repayment) => (float) $repayment->deduction_amount
            ),
            2
        );
    }

    /**
     * Get the beginning of the applicable tax year.
     *
     * Example:
     * 2026-09-30 -> 2026-03-01
     */
    public function getTaxYearStart(
        ?Carbon $date = null
    ): Carbon {
        $date = $date ?? Carbon::today();

        $taxYear = $this->getTaxYear($date);

        return Carbon::parse($taxYear->starts_on)
            ->startOfDay();
    }

    /**
     * Get the end of the applicable tax year.
     *
     * Example:
     * 2026-09-30 -> 2027-02-28
     */
    public function getTaxYearEnd(
        ?Carbon $date = null
    ): Carbon {
        $date = $date ?? Carbon::today();

        $taxYear = $this->getTaxYear($date);

        return Carbon::parse($taxYear->ends_on)
            ->endOfDay();
    }

    /**
     * Get previous payslips for an employee within
     * the current tax year.
     *
     * The current month can optionally be excluded.
     */
    public function getYearToDatePayslips(
        Staff $staff,
        ?Carbon $date = null,
        bool $excludeCurrentMonth = true
    ): Collection {
        $date = $date ?? Carbon::today();

        $taxYearStart = $this->getTaxYearStart($date);

        $query = Payslip::query()
            ->where('staff_id', $staff->id)
            ->where(function ($query) use ($taxYearStart) {
                /*
                 * Payslips have pay_date, so we use that
                 * where available.
                 */
                $query->whereDate(
                    'pay_date',
                    '>=',
                    $taxYearStart->toDateString()
                )
                ->orWhere(function ($query) use ($taxYearStart) {
                    /*
                     * Older payslips may have a null pay_date.
                     * Fall back to year/month.
                     */
                    $query->whereNull('pay_date')
                        ->where(function ($query) use ($taxYearStart) {
                            $query->where('year', '>', $taxYearStart->year)
                                ->orWhere(function ($query) use ($taxYearStart) {
                                    $query->where(
                                        'year',
                                        $taxYearStart->year
                                    )->where(
                                        'month',
                                        '>=',
                                        $taxYearStart->month
                                    );
                                });
                        });
                });
            });

        if ($excludeCurrentMonth) {
            $query->where(function ($query) use ($date) {
                $query->whereDate(
                    'pay_date',
                    '<',
                    $date->copy()->startOfMonth()->toDateString()
                )
                ->orWhere(function ($query) use ($date) {
                    $query->whereNull('pay_date')
                        ->where(function ($query) use ($date) {
                            $query->where('year', '<', $date->year)
                                ->orWhere(function ($query) use ($date) {
                                    $query->where(
                                        'year',
                                        $date->year
                                    )->where(
                                        'month',
                                        '<',
                                        $date->month
                                    );
                                });
                        });
                });
            });
        }

        return $query
            ->orderBy('pay_date')
            ->orderBy('year')
            ->orderBy('month')
            ->get();
    }

    /**
     * Calculate YTD gross from existing payslips.
     */
    public function calculateYTDGross(
        Staff $staff,
        ?Carbon $date = null
    ): float {
        $payslips = $this->getYearToDatePayslips(
            $staff,
            $date,
            true
        );

        return round(
            (float) $payslips->sum(
                fn ($payslip) => (float) $payslip->gross_salary
            ),
            2
        );
    }

    /**
     * Calculate YTD PAYE.
     */
    public function calculateYTDPayE(
        Staff $staff,
        ?Carbon $date = null
    ): float {
        $payslips = $this->getYearToDatePayslips(
            $staff,
            $date,
            true
        );

        return round(
            (float) $payslips->sum(
                fn ($payslip) => (float) $payslip->paye
            ),
            2
        );
    }

    /**
     * Calculate YTD UIF employee deduction.
     */
    public function calculateYTDUIF(
        Staff $staff,
        ?Carbon $date = null
    ): float {
        $payslips = $this->getYearToDatePayslips(
            $staff,
            $date,
            true
        );

        return round(
            (float) $payslips->sum(
                fn ($payslip) => (float) $payslip->uif
            ),
            2
        );
    }

    /**
     * Calculate YTD total deductions.
     */
    public function calculateYTDDeductions(
        Staff $staff,
        ?Carbon $date = null
    ): float {
        $payslips = $this->getYearToDatePayslips(
            $staff,
            $date,
            true
        );

        return round(
            (float) $payslips->sum(
                fn ($payslip) => (float) $payslip->deductions
            ),
            2
        );
    }

    /**
     * Calculate YTD net salary.
     */
    public function calculateYTDNet(
        Staff $staff,
        ?Carbon $date = null
    ): float {
        $payslips = $this->getYearToDatePayslips(
            $staff,
            $date,
            true
        );

        return round(
            (float) $payslips->sum(
                fn ($payslip) => (float) $payslip->net_salary
            ),
            2
        );
    }

    /**
     * Return all YTD figures together.
     */
    public function calculateYTD(
        Staff $staff,
        ?Carbon $date = null
    ): array {
        $date = $date ?? Carbon::today();

        $payslips = $this->getYearToDatePayslips(
            $staff,
            $date,
            true
        );

        $gross = round(
            (float) $payslips->sum(
                fn ($payslip) => (float) $payslip->gross_salary
            ),
            2
        );

        $paye = round(
            (float) $payslips->sum(
                fn ($payslip) => (float) $payslip->paye
            ),
            2
        );

        $uif = round(
            (float) $payslips->sum(
                fn ($payslip) => (float) $payslip->uif
            ),
            2
        );

        $deductions = round(
            (float) $payslips->sum(
                fn ($payslip) => (float) $payslip->deductions
            ),
            2
        );

        $net = round(
            (float) $payslips->sum(
                fn ($payslip) => (float) $payslip->net_salary
            ),
            2
        );

        return [
            'tax_year' => $this->getTaxYear($date)->tax_year,

            'tax_year_start' => $this->getTaxYearStart($date)
                ->toDateString(),

            'tax_year_end' => $this->getTaxYearEnd($date)
                ->toDateString(),

            'months_included' => $payslips->count(),

            'gross' => $gross,

            'paye' => $paye,

            'uif' => $uif,

            'deductions' => $deductions,

            'net' => $net,
        ];
    }

    /**
     * Calculate employee payroll.
     */
    public function calculateEmployeePayroll(
        Staff $staff,
        float $allowances = 0,
        float $overtime = 0,
        float $bonuses = 0,
        ?Carbon $date = null,
        bool $employerExemptFromSDL = false
    ): array {
        $date = $date ?? Carbon::today();

        /*
         * Do not allow incomplete employees into payroll.
         */
        $this->ensureEmployeeIsValidForPayroll(
            $staff,
            $date
        );

        $basicSalary = (float) ($staff->salary ?? 0);

        $age = $this->getEmployeeAge(
            $staff,
            $date
        );

        $payroll = $this->calculatePayroll(
            $basicSalary,
            $age,
            $allowances,
            $overtime,
            $bonuses,
            $date,
            $employerExemptFromSDL,
            $staff
        );

        /*
         * Add YTD figures from previous payslips.
         *
         * The current payroll is deliberately not included here.
         * This prevents double counting when the payslip is created.
         */
        $ytd = $this->calculateYTD(
            $staff,
            $date
        );

        $payroll['ytd_gross'] = round(
            $ytd['gross'] + $payroll['gross'],
            2
        );

        $payroll['ytd_paye'] = round(
            $ytd['paye'] + $payroll['paye'],
            2
        );

        $payroll['ytd_uif'] = round(
            $ytd['uif'] + $payroll['uif_employee'],
            2
        );

        $payroll['ytd_deductions'] = round(
            $ytd['deductions'] + $payroll['total_deductions'],
            2
        );

        $payroll['ytd_net'] = round(
            $ytd['net'] + $payroll['net_salary'],
            2
        );

        return $payroll;
    }

    /**
     * Calculate payroll using supplied salary and age.
     */
    public function calculatePayroll(
        float $basicSalary,
        int $age,
        float $allowances = 0,
        float $overtime = 0,
        float $bonuses = 0,
        ?Carbon $date = null,
        bool $employerExemptFromSDL = false,
        ?Staff $staff = null
    ): array {
        $date = $date ?? Carbon::today();

        $basicSalary = max($basicSalary, 0);
        $allowances = max($allowances, 0);
        $overtime = max($overtime, 0);
        $bonuses = max($bonuses, 0);

        $gross = round(
            $basicSalary
            + $allowances
            + $overtime
            + $bonuses,
            2
        );

        /*
         * All supplied gross remuneration is currently
         * treated as taxable income.
         */
        $paye = $this->calculateMonthlyPAYE(
            $gross,
            $age,
            $date
        );

        $uifEmployee = $this->calculateUIFEmployee(
            $gross,
            $date
        );

        $uifEmployer = $this->calculateUIFEmployer(
            $gross,
            $date
        );

        $sdl = $this->calculateSDL(
            $gross,
            $employerExemptFromSDL,
            $date
        );

        $loanDeductions = 0.00;

        if ($staff) {
            $loanDeductions = $this->calculateLoanDeduction(
                $staff,
                $date
            );
        }

        $otherDeductions = 0.00;

        $totalDeductions = round(
            $paye
            + $uifEmployee
            + $loanDeductions
            + $otherDeductions,
            2
        );

        $netSalary = round(
            $gross - $totalDeductions,
            2
        );

        $employerCost = round(
            $gross
            + $uifEmployer
            + $sdl,
            2
        );

        return [
            'basic_salary' => round($basicSalary, 2),

            'allowances' => round($allowances, 2),

            'overtime' => round($overtime, 2),

            'bonuses' => round($bonuses, 2),

            'gross' => $gross,

            'paye' => $paye,

            'uif_employee' => $uifEmployee,

            'uif_employer' => $uifEmployer,

            'sdl' => $sdl,

            'other_deductions' => $otherDeductions,

            'loan_deductions' => $loanDeductions,

            'total_deductions' => $totalDeductions,

            'net_salary' => $netSalary,

            'employer_cost' => $employerCost,

            'tax_year' => $this->getTaxYear($date)->tax_year,

            'age' => $age,
        ];
    }
}

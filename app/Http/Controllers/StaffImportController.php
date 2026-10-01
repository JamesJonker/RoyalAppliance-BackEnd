<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\WorkSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class StaffImportController extends Controller
{
    /**
     * Import historical/existing staff from Excel.
     *
     * Expected worksheet:
     * Import Ready
     *
     * Expected header row:
     * Row 4
     *
     * Data begins:
     * Row 5
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls',
                'max:10240',
            ],
        ]);

        $file = $request->file('file');

        try {

            $spreadsheet = IOFactory::load($file->getRealPath());

            /*
            |--------------------------------------------------------------------------
            | Get Import Ready Sheet
            |--------------------------------------------------------------------------
            */

            $sheet = $spreadsheet->getSheetByName('Import Ready');

            if (!$sheet) {
                return response()->json([
                    'message' => 'The workbook does not contain an "Import Ready" sheet.',
                ], 422);
            }

            $highestRow = $sheet->getHighestDataRow();

            if ($highestRow < 5) {
                return response()->json([
                    'message' => 'No staff records were found in the Import Ready sheet.',
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | Import Results
            |--------------------------------------------------------------------------
            */

            $imported = [];
            $skipped = [];
            $errors = [];

            DB::beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | Start Reading At Row 5
            |--------------------------------------------------------------------------
            */

            for ($row = 5; $row <= $highestRow; $row++) {

                /*
                |--------------------------------------------------------------------------
                | Excel Columns
                |--------------------------------------------------------------------------
                |
                | A  Employee Number
                | B  First Name
                | C  Last Name
                | D  ID Number
                | E  Tax Number
                | F  Start Date
                | G  Branch
                | H  Monthly Salary
                | I  Employment Status
                | J  Existing Notes
                | K  Bank Name
                | L  Account Number
                | M  Position
                | N  Employment Type
                | O  Contact Number
                | P  Email
                | Q  Street Address
                | R  Complex / Building
                | S  Suburb
                | T  Postal Code
                | U  Work Schedule
                | V  Account Holder Name
                | W  Account Type
                | X  Bank Branch Code
                | Y  Payment Method
                | Z  Passport Number
                | AA Passport Country
                | AB Import Review
                | AC Missing Fields
                |
                */

                $employeeNumber = $this->clean(
                    $sheet->getCell("A{$row}")->getValue()
                );

                $firstName = $this->clean(
                    $sheet->getCell("B{$row}")->getValue()
                );

                $lastName = $this->clean(
                    $sheet->getCell("C{$row}")->getValue()
                );

                /*
                |--------------------------------------------------------------------------
                | Ignore Completely Empty Rows
                |--------------------------------------------------------------------------
                */

                if (!$employeeNumber && !$firstName && !$lastName) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Read Remaining Values
                |--------------------------------------------------------------------------
                */

                $idNumber = $this->clean(
                    $sheet->getCell("D{$row}")->getValue()
                );

                $taxNumber = $this->clean(
                    $sheet->getCell("E{$row}")->getValue()
                );

                $startDateValue = $sheet->getCell("F{$row}")->getValue();

                $branch = $this->clean(
                    $sheet->getCell("G{$row}")->getValue()
                );

                $salary = $this->cleanNumber(
                    $sheet->getCell("H{$row}")->getValue()
                );

                $employmentStatus = $this->clean(
                    $sheet->getCell("I{$row}")->getValue()
                );

                $existingNotes = $this->clean(
                    $sheet->getCell("J{$row}")->getValue()
                );

                $bankName = $this->clean(
                    $sheet->getCell("K{$row}")->getValue()
                );

                $accountNumber = $this->clean(
                    $sheet->getCell("L{$row}")->getValue()
                );

                $position = $this->clean(
                    $sheet->getCell("M{$row}")->getValue()
                );

                $employmentType = $this->clean(
                    $sheet->getCell("N{$row}")->getValue()
                );

                $contactNumber = $this->clean(
                    $sheet->getCell("O{$row}")->getValue()
                );

                $email = $this->clean(
                    $sheet->getCell("P{$row}")->getValue()
                );

                $street = $this->clean(
                    $sheet->getCell("Q{$row}")->getValue()
                );

                $complex = $this->clean(
                    $sheet->getCell("R{$row}")->getValue()
                );

                $suburb = $this->clean(
                    $sheet->getCell("S{$row}")->getValue()
                );

                $postalCode = $this->clean(
                    $sheet->getCell("T{$row}")->getValue()
                );

                $workScheduleName = $this->clean(
                    $sheet->getCell("U{$row}")->getValue()
                );

                $accountHolderName = $this->clean(
                    $sheet->getCell("V{$row}")->getValue()
                );

                $accountType = $this->clean(
                    $sheet->getCell("W{$row}")->getValue()
                );

                $branchCode = $this->clean(
                    $sheet->getCell("X{$row}")->getValue()
                );

                $paymentMethod = $this->clean(
                    $sheet->getCell("Y{$row}")->getValue()
                );

                $passportNumber = $this->clean(
                    $sheet->getCell("Z{$row}")->getValue()
                );

                $passportCountry = $this->clean(
                    $sheet->getCell("AA{$row}")->getValue()
                );

                $importReview = $this->clean(
                    $sheet->getCell("AB{$row}")->getValue()
                );

                /*
                |--------------------------------------------------------------------------
                | Normalise Employee Number
                |--------------------------------------------------------------------------
                */

                $employeeNumber = strtoupper(trim($employeeNumber));

                /*
                |--------------------------------------------------------------------------
                | Safety Check - Review Flag
                |--------------------------------------------------------------------------
                |
                | The Import Ready sheet should not contain review issues.
                | But if somebody later manually copies a bad row into this sheet,
                | we still refuse to import it.
                |
                */

                if ($importReview) {

                    $skipped[] = [
                        'row' => $row,
                        'employee_number' => $employeeNumber,
                        'name' => trim($firstName . ' ' . $lastName),
                        'reason' => 'Import Review contains: ' . $importReview,
                    ];

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Required Import Fields
                |--------------------------------------------------------------------------
                |
                | Historical import only requires the fields that identify the
                | employee and place them into the correct branch.
                |
                */

                $validator = Validator::make([
                    'employee_number' => $employeeNumber,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'branch' => $branch,
                    'salary' => $salary,
                ], [
                    'employee_number' => 'required|string|max:255',
                    'first_name' => 'required|string|max:255',
                    'last_name' => 'required|string|max:255',
                    'branch' => 'required|string|max:255',
                    'salary' => 'nullable|numeric|min:0',
                ]);

                if ($validator->fails()) {

                    $errors[] = [
                        'row' => $row,
                        'employee_number' => $employeeNumber,
                        'name' => trim($firstName . ' ' . $lastName),
                        'errors' => $validator->errors()->all(),
                    ];

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Prevent Duplicate Employee Numbers
                |--------------------------------------------------------------------------
                */

                $existingStaff = Staff::where(
                    'employee_number',
                    $employeeNumber
                )->first();

                if ($existingStaff) {

                    $skipped[] = [
                        'row' => $row,
                        'employee_number' => $employeeNumber,
                        'name' => trim($firstName . ' ' . $lastName),
                        'reason' => 'Employee number already exists.',
                    ];

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Prevent Duplicate Email
                |--------------------------------------------------------------------------
                */

                if ($email) {

                    $existingEmail = Staff::where('email', $email)->exists();

                    if ($existingEmail) {

                        $skipped[] = [
                            'row' => $row,
                            'employee_number' => $employeeNumber,
                            'name' => trim($firstName . ' ' . $lastName),
                            'reason' => 'Email address already exists.',
                        ];

                        continue;
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Convert Start Date
                |--------------------------------------------------------------------------
                */

                $startDate = $this->convertExcelDate($startDateValue);

                /*
                |--------------------------------------------------------------------------
                | Work Schedule
                |--------------------------------------------------------------------------
                */

                $workScheduleId = null;

                if ($workScheduleName) {

                    $workSchedule = WorkSchedule::where(
                        'name',
                        $workScheduleName
                    )->first();

                    if ($workSchedule) {
                        $workScheduleId = $workSchedule->id;
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Create Staff
                |--------------------------------------------------------------------------
                */

                $staff = Staff::create([

                    'employee_number' => $employeeNumber,

                    'first_name' => $firstName,
                    'last_name' => $lastName,

                    'Id_number' => $idNumber,
                    'tax_number' => $taxNumber,

                    'passport_number' => $passportNumber,
                    'passport_country' => $passportCountry,

                    'contact_no' => $contactNumber,
                    'email' => $email,

                    'position' => $position,
                    'employment_type' => $employmentType,

                    'branch' => $branch,

                    'salary' => $salary,

                    'start_date' => $startDate,

                    'work_schedule_id' => $workScheduleId,

                    'street' => $street,
                    'complex' => $complex,
                    'suburb' => $suburb,
                    'postal_code' => $postalCode,
                ]);

              /*
|--------------------------------------------------------------------------
| Bank Details
|--------------------------------------------------------------------------
|
| Historical staff may only have partial banking information.
|
| We still save whatever valid information we have so it can be completed
| later from the Edit Staff screen.
|
*/

$hasBankDetails =
    $bankName ||
    $accountNumber ||
    $accountHolderName ||
    $accountType ||
    $branchCode ||
    $paymentMethod;

if ($hasBankDetails) {

    $staff->bankDetails()->create([

        'bank_name' =>
            $bankName,

        'account_holder_name' =>
            $accountHolderName,

        'account_number' =>
            $accountNumber,

        'account_type' =>
            $accountType,

        'branch_code' =>
            $branchCode,

        'payment_method' =>
            $paymentMethod ?: 'EFT',
    ]);
}

                /*
                |--------------------------------------------------------------------------
                | Successful Import
                |--------------------------------------------------------------------------
                */

                $imported[] = [
                    'row' => $row,
                    'id' => $staff->id,
                    'employee_number' => $staff->employee_number,
                    'name' => trim(
                        $staff->first_name . ' ' . $staff->last_name
                    ),
                    'branch' => $staff->branch,
                    'salary' => $staff->salary,
                    'employment_status_source' => $employmentStatus,
                    'notes_source' => $existingNotes,
                ];
            }

            DB::commit();

            return response()->json([
                'message' => 'Staff import completed.',
                'summary' => [
                    'imported' => count($imported),
                    'skipped' => count($skipped),
                    'errors' => count($errors),
                ],
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $errors,
            ]);

        } catch (Throwable $e) {

            DB::rollBack();

            return response()->json([
                'message' => 'Staff import failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clean normal Excel cell values.
     */
    private function clean($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return $value;
    }

    /**
     * Convert salary/value fields to number.
     */
    private function cleanNumber($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace([
                'R',
                ' ',
                ',',
            ], '', $value);
        }

        if (!is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }

    /**
     * Convert either:
     *
     * Excel date serial:
     * 44774
     *
     * or date string:
     * 2022-08-01
     *
     * into:
     * YYYY-MM-DD
     */
    private function convertExcelDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {

            /*
            |--------------------------------------------------------------------------
            | Excel Numeric Date
            |--------------------------------------------------------------------------
            */

            if (is_numeric($value)) {

                return ExcelDate::excelToDateTimeObject(
                    $value
                )->format('Y-m-d');
            }

            /*
            |--------------------------------------------------------------------------
            | Normal Date String
            |--------------------------------------------------------------------------
            */

            $timestamp = strtotime((string) $value);

            if ($timestamp === false) {
                return null;
            }

            return date('Y-m-d', $timestamp);

        } catch (Throwable $e) {

            return null;
        }
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | GET ALL STAFF
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        return response()->json(
            Staff::with([
                'workSchedule',
                'bankDetails',
            ])
                ->orderBy('branch')
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(),
            200
        );
    }


    /*
    |--------------------------------------------------------------------------
    | GET ONE STAFF MEMBER
    |--------------------------------------------------------------------------
    */

    public function show(
        Staff $staff
    ) {
        $staff->load([
            'workSchedule.days',
            'bankDetails',
        ]);

        return response()->json(
            $staff,
            200
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CREATE STAFF
    |--------------------------------------------------------------------------
    */

    public function store(
        Request $request
    ) {
        $validated =
            $request->validate(
                $this->validationRules()
            );


        DB::beginTransaction();


        try {

            /*
            |--------------------------------------------------------------------------
            | CREATE STAFF
            |--------------------------------------------------------------------------
            */

            $staff =
                Staff::create(
                    $this->staffData(
                        $validated
                    )
                );


            /*
            |--------------------------------------------------------------------------
            | CREATE BANK DETAILS
            |--------------------------------------------------------------------------
            */

            $staff
                ->bankDetails()
                ->create(
                    $this->bankData(
                        $validated
                    )
                );


            DB::commit();


            /*
            |--------------------------------------------------------------------------
            | RETURN COMPLETE STAFF RECORD
            |--------------------------------------------------------------------------
            */

            $staff->load([
                'workSchedule.days',
                'bankDetails',
            ]);


            return response()->json(
                $staff,
                201
            );

        } catch (
            \Throwable $e
        ) {

            DB::rollBack();


            return response()->json([

                'message' =>
                    'Unable to create staff member.',

                'error' =>
                    $e->getMessage(),

            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE STAFF
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        Staff $staff
    ) {

        /*
        |--------------------------------------------------------------------------
        | VALIDATE
        |--------------------------------------------------------------------------
        |
        | The email uniqueness rule must ignore the staff member currently
        | being edited, otherwise Laravel would reject their existing email.
        |
        */

        $validated =
            $request->validate(
                $this->validationRules(
                    $staff
                )
            );


        DB::beginTransaction();


        try {

            /*
            |--------------------------------------------------------------------------
            | UPDATE STAFF
            |--------------------------------------------------------------------------
            |
            | staffData() also recalculates date_of_birth from the ID number.
            |
            */

            $staff->update(
                $this->staffData(
                    $validated
                )
            );


            /*
            |--------------------------------------------------------------------------
            | UPDATE / CREATE BANK DETAILS
            |--------------------------------------------------------------------------
            */

            $staff
                ->bankDetails()
                ->updateOrCreate(
                    [
                        'staff_id' =>
                            $staff->id,
                    ],
                    $this->bankData(
                        $validated
                    )
                );


            DB::commit();


            /*
            |--------------------------------------------------------------------------
            | RETURN UPDATED STAFF
            |--------------------------------------------------------------------------
            */

            $staff->refresh();


            $staff->load([
                'workSchedule.days',
                'bankDetails',
            ]);


            return response()->json([

                'message' =>
                    'Staff member updated successfully.',

                'staff' =>
                    $staff,

            ], 200);

        } catch (
            \Throwable $e
        ) {

            DB::rollBack();


            return response()->json([

                'message' =>
                    'Unable to update staff member.',

                'error' =>
                    $e->getMessage(),

            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDATION RULES
    |--------------------------------------------------------------------------
    */

    private function validationRules(
        ?Staff $staff = null
    ): array {

        /*
        |--------------------------------------------------------------------------
        | EMAIL RULE
        |--------------------------------------------------------------------------
        */

        $emailRule =
            Rule::unique(
                'staff',
                'email'
            );


        if (
            $staff
        ) {

            $emailRule =
                $emailRule->ignore(
                    $staff->id
                );

        }


        return [

            /*
            |--------------------------------------------------------------------------
            | EMPLOYEE
            |--------------------------------------------------------------------------
            */

            'employee_number' => [
                'required',
                'string',
                'max:50',
            ],


            /*
            |--------------------------------------------------------------------------
            | PERSONAL
            |--------------------------------------------------------------------------
            */

            'first_name' => [
                'required',
                'string',
                'max:255',
            ],

            'last_name' => [
                'required',
                'string',
                'max:255',
            ],

            'Id_number' => [
                'required',
                'string',
                'max:255',
            ],


            /*
            |--------------------------------------------------------------------------
            | CONTACT
            |--------------------------------------------------------------------------
            */

            'contact_no' => [
                'required',
                'string',
                'max:20',
            ],

            'email' => [
                'required',
                'email',
                $emailRule,
            ],


            /*
            |--------------------------------------------------------------------------
            | EMPLOYMENT
            |--------------------------------------------------------------------------
            */

            'position' => [
                'required',
                'string',
                'max:255',
            ],

            'employment_type' => [
                'required',
                'string',
                'max:100',
            ],

            'branch' => [
                'required',
                'string',
                'max:255',
            ],

            'salary' => [
                'required',
                'numeric',
                'min:0',
            ],

            'start_date' => [
                'required',
                'date',
            ],


            /*
            |--------------------------------------------------------------------------
            | WORK SCHEDULE
            |--------------------------------------------------------------------------
            */

            'work_schedule_id' => [
                'nullable',
                'integer',
                'exists:work_schedules,id',
            ],


            /*
            |--------------------------------------------------------------------------
            | SARS / TAX
            |--------------------------------------------------------------------------
            */

            'tax_number' => [
                'nullable',
                'string',
                'max:50',
            ],

            'passport_number' => [
                'nullable',
                'string',
                'max:100',
            ],

            'passport_country' => [
                'nullable',
                'string',
                'max:100',
            ],


            /*
            |--------------------------------------------------------------------------
            | ADDRESS
            |--------------------------------------------------------------------------
            */

            'street' => [
                'required',
                'string',
                'max:255',
            ],

            'complex' => [
                'required',
                'string',
                'max:255',
            ],

            'suburb' => [
                'required',
                'string',
                'max:255',
            ],

            'postal_code' => [
                'required',
                'string',
                'max:255',
            ],


            /*
            |--------------------------------------------------------------------------
            | BANK DETAILS
            |--------------------------------------------------------------------------
            */

            'bank_details.bank_name' => [
                'required',
                'string',
                'max:255',
            ],

            'bank_details.account_holder_name' => [
                'required',
                'string',
                'max:255',
            ],

            'bank_details.account_number' => [
                'required',
                'string',
                'max:50',
            ],

            'bank_details.account_type' => [
                'required',
                'string',
                'max:50',
            ],

            'bank_details.branch_code' => [
                'required',
                'string',
                'max:20',
            ],

            'bank_details.payment_method' => [
                'required',

                Rule::in([
                    'EFT',
                    'CASH',
                    'CHEQUE',
                ]),
            ],

        ];
    }


    /*
    |--------------------------------------------------------------------------
    | STAFF DATA
    |--------------------------------------------------------------------------
    |
    | Both CREATE and UPDATE use the same field mapping.
    |
    | date_of_birth is automatically calculated from a valid South African
    | ID number.
    |
    */

    private function staffData(
        array $validated
    ): array {

        /*
        |--------------------------------------------------------------------------
        | CALCULATE DATE OF BIRTH FROM ID
        |--------------------------------------------------------------------------
        */

        $dateOfBirth =
            $this->dateOfBirthFromSouthAfricanId(
                $validated['Id_number']
                ?? null
            );


        return [

            'employee_number' =>
                $validated['employee_number'],

            'first_name' =>
                $validated['first_name'],

            'last_name' =>
                $validated['last_name'],

            'Id_number' =>
                $validated['Id_number'],

            /*
            |--------------------------------------------------------------------------
            | Automatically Derived DOB
            |--------------------------------------------------------------------------
            */

            'date_of_birth' =>
                $dateOfBirth,

            'contact_no' =>
                $validated['contact_no'],

            'email' =>
                $validated['email'],

            'position' =>
                $validated['position'],

            'employment_type' =>
                $validated['employment_type'],

            'branch' =>
                $validated['branch'],

            'salary' =>
                $validated['salary'],

            'start_date' =>
                $validated['start_date'],

            'work_schedule_id' =>
                $validated['work_schedule_id']
                ?? null,


            /*
            |--------------------------------------------------------------------------
            | SARS / TAX
            |--------------------------------------------------------------------------
            */

            'tax_number' =>
                $validated['tax_number']
                ?? null,

            'passport_number' =>
                $validated['passport_number']
                ?? null,

            'passport_country' =>
                $validated['passport_country']
                ?? null,


            /*
            |--------------------------------------------------------------------------
            | ADDRESS
            |--------------------------------------------------------------------------
            */

            'street' =>
                $validated['street'],

            'complex' =>
                $validated['complex'],

            'suburb' =>
                $validated['suburb'],

            'postal_code' =>
                $validated['postal_code'],

        ];
    }


    /*
    |--------------------------------------------------------------------------
    | DATE OF BIRTH FROM SOUTH AFRICAN ID
    |--------------------------------------------------------------------------
    |
    | South African ID format:
    |
    | YYMMDD SSSS C A Z
    |
    | The first six digits contain the date of birth.
    |
    | Example:
    |
    | 8005060152080
    |
    | YY = 80
    | MM = 05
    | DD = 06
    |
    | DOB = 1980-05-06
    |
    |--------------------------------------------------------------------------
    */

    private function dateOfBirthFromSouthAfricanId(
        ?string $idNumber
    ): ?string {

        /*
        |--------------------------------------------------------------------------
        | No ID Number
        |--------------------------------------------------------------------------
        */

        if (!$idNumber) {

            return null;

        }


        /*
        |--------------------------------------------------------------------------
        | Keep Digits Only
        |--------------------------------------------------------------------------
        */

        $idNumber =
            preg_replace(
                '/\D/',
                '',
                $idNumber
            );


        /*
        |--------------------------------------------------------------------------
        | Must Be 13 Digits
        |--------------------------------------------------------------------------
        */

        if (
            strlen($idNumber) !== 13
        ) {

            return null;

        }


        /*
        |--------------------------------------------------------------------------
        | Extract YYMMDD
        |--------------------------------------------------------------------------
        */

        $yy =
            (int) substr(
                $idNumber,
                0,
                2
            );

        $mm =
            (int) substr(
                $idNumber,
                2,
                2
            );

        $dd =
            (int) substr(
                $idNumber,
                4,
                2
            );


        /*
        |--------------------------------------------------------------------------
        | Check Month / Day
        |--------------------------------------------------------------------------
        */

        if (
            $mm < 1
            ||
            $mm > 12
            ||
            $dd < 1
            ||
            $dd > 31
        ) {

            return null;

        }


        try {

            /*
            |--------------------------------------------------------------------------
            | Try 2000s First
            |--------------------------------------------------------------------------
            */

            $date =
                Carbon::create(
                    2000 + $yy,
                    $mm,
                    $dd
                )
                    ->startOfDay();


            /*
            |--------------------------------------------------------------------------
            | Determine Correct Century
            |--------------------------------------------------------------------------
            |
            | If the 2000s date would make the employee younger than 15,
            | interpret the year as 19YY instead.
            |
            */

            if (
                $date->age < 15
            ) {

                $date =
                    Carbon::create(
                        1900 + $yy,
                        $mm,
                        $dd
                    )
                        ->startOfDay();

            }


            /*
            |--------------------------------------------------------------------------
            | Sanity Check
            |--------------------------------------------------------------------------
            */

            if (
                $date->isFuture()
                ||
                $date->age > 100
            ) {

                return null;

            }


            /*
            |--------------------------------------------------------------------------
            | Make Sure YYMMDD Really Represents the Calculated Date
            |--------------------------------------------------------------------------
            */

            if (
                $date->format('md')
                !==
                sprintf(
                    '%02d%02d',
                    $mm,
                    $dd
                )
            ) {

                return null;

            }


            return $date->format(
                'Y-m-d'
            );

        } catch (
            \Throwable $e
        ) {

            return null;

        }
    }


    /*
    |--------------------------------------------------------------------------
    | BANK DATA
    |--------------------------------------------------------------------------
    */

    private function bankData(
        array $validated
    ): array {

        return [

            'bank_name' =>
                $validated[
                    'bank_details'
                ][
                    'bank_name'
                ],

            'account_holder_name' =>
                $validated[
                    'bank_details'
                ][
                    'account_holder_name'
                ],

            'account_number' =>
                $validated[
                    'bank_details'
                ][
                    'account_number'
                ],

            'account_type' =>
                $validated[
                    'bank_details'
                ][
                    'account_type'
                ],

            'branch_code' =>
                $validated[
                    'bank_details'
                ][
                    'branch_code'
                ],

            'payment_method' =>
                $validated[
                    'bank_details'
                ][
                    'payment_method'
                ],

        ];
    }


    /*
    |--------------------------------------------------------------------------
    | DELETE STAFF
    |--------------------------------------------------------------------------
    */

    public function delete(
        Staff $staff
    ) {

        $staff->delete();


        return response()->json([

            'message' =>
                'Deleted successfully',

        ], 200);
    }
}
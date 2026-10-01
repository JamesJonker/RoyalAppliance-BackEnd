<?php

namespace App\Http\Controllers;

use App\Models\EmployerTaxSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class EmployerTaxSettingController extends Controller
{
    /**
     * =========================================================
     * GET CURRENT SETTINGS
     * =========================================================
     */
    public function show(): JsonResponse
    {
        try {

            $settings =
                EmployerTaxSetting::query()
                    ->where(
                        'is_active',
                        true
                    )
                    ->latest('id')
                    ->first();


            return response()->json([

                'settings' =>
                    $settings,

            ]);

        } catch (Throwable $exception) {

            return response()->json([

                'message' =>
                    'Unable to load employer tax settings.',

                'error' =>
                    $exception->getMessage(),

            ], 500);

        }
    }


    /**
     * =========================================================
     * SAVE SETTINGS
     * =========================================================
     */
    public function save(
        Request $request
    ): JsonResponse {

        try {

            $validated =
                $request->validate([

                    'company_name' => [
                        'required',
                        'string',
                        'max:255',
                    ],

                    'trading_name' => [
                        'nullable',
                        'string',
                        'max:255',
                    ],

                    'company_registration_number' => [
                        'nullable',
                        'string',
                        'max:100',
                    ],

                    'paye_reference' => [
                        'nullable',
                        'string',
                        'max:100',
                    ],

                    'sdl_reference' => [
                        'nullable',
                        'string',
                        'max:100',
                    ],

                    'uif_reference' => [
                        'nullable',
                        'string',
                        'max:100',
                    ],

                    'vat_number' => [
                        'nullable',
                        'string',
                        'max:100',
                    ],

                    'contact_person' => [
                        'nullable',
                        'string',
                        'max:255',
                    ],

                    'contact_email' => [
                        'nullable',
                        'email',
                        'max:255',
                    ],

                    'contact_number' => [
                        'nullable',
                        'string',
                        'max:50',
                    ],

                    'physical_unit' => [
                        'nullable',
                        'string',
                        'max:100',
                    ],

                    'physical_complex' => [
                        'nullable',
                        'string',
                        'max:255',
                    ],

                    'physical_street' => [
                        'nullable',
                        'string',
                        'max:255',
                    ],

                    'physical_suburb' => [
                        'nullable',
                        'string',
                        'max:255',
                    ],

                    'physical_city' => [
                        'nullable',
                        'string',
                        'max:255',
                    ],

                    'physical_province' => [
                        'nullable',
                        'string',
                        'max:255',
                    ],

                    'physical_postal_code' => [
                        'nullable',
                        'string',
                        'max:20',
                    ],

                    'postal_same_as_physical' => [
                        'required',
                        'boolean',
                    ],

                    'postal_line_1' => [
                        'nullable',
                        'string',
                        'max:255',
                    ],

                    'postal_line_2' => [
                        'nullable',
                        'string',
                        'max:255',
                    ],

                    'postal_suburb' => [
                        'nullable',
                        'string',
                        'max:255',
                    ],

                    'postal_city' => [
                        'nullable',
                        'string',
                        'max:255',
                    ],

                    'postal_province' => [
                        'nullable',
                        'string',
                        'max:255',
                    ],

                    'postal_code' => [
                        'nullable',
                        'string',
                        'max:20',
                    ],

                ]);


            $settings =
                EmployerTaxSetting::query()
                    ->where(
                        'is_active',
                        true
                    )
                    ->latest('id')
                    ->first();


            if ($settings) {

                $settings->update(
                    $validated
                );

            } else {

                $validated[
                    'is_active'
                ] =
                    true;


                $settings =
                    EmployerTaxSetting::create(
                        $validated
                    );

            }


            return response()->json([

                'message' =>
                    'Employer tax settings saved successfully.',

                'settings' =>
                    $settings->fresh(),

            ]);

        } catch (
            ValidationException $exception
        ) {

            throw $exception;

        } catch (
            Throwable $exception
        ) {

            return response()->json([

                'message' =>
                    'Unable to save employer tax settings.',

                'error' =>
                    $exception->getMessage(),

            ], 500);

        }
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * ==========================================================
     * LIST USERS
     * ==========================================================
     */
    public function index(): JsonResponse
    {
        $users = User::with([
            'roles.permissions'
        ])
        ->orderBy('name')
        ->get();

        return response()->json([
            'data' => $users
        ]);
    }


    /**
     * ==========================================================
     * SHOW USER
     * ==========================================================
     */
    public function show(int $id): JsonResponse
    {
        $user = User::with([
            'roles.permissions'
        ])
        ->findOrFail($id);

        return response()->json([
            'data' => $user
        ]);
    }


    /**
     * ==========================================================
     * CREATE USER
     * ==========================================================
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([

            'name' => [
                'required',
                'string',
                'max:255'
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email'
            ],

            'password' => [
                'required',
                'string',
                'min:8'
            ],

            'is_active' => [
                'sometimes',
                'boolean'
            ],

            'roles' => [
                'required',
                'array',
                'min:1'
            ],

            'roles.*' => [
                'integer',
                'exists:roles,id'
            ],

        ]);


        // ------------------------------------------------------
        // CREATE USER
        // ------------------------------------------------------

        $user = User::create([

            'name' => $validated['name'],

            'email' => $validated['email'],

            'password' => $validated['password'],

            'is_active' =>
                $validated['is_active'] ?? true,

        ]);


        // ------------------------------------------------------
        // ASSIGN ROLES
        // ------------------------------------------------------

        $user->roles()->sync(
            $validated['roles']
        );


        // ------------------------------------------------------
        // LOAD RELATIONSHIPS
        // ------------------------------------------------------

        $user->load(
            'roles.permissions'
        );


        return response()->json([

            'message' =>
                'User created successfully.',

            'data' =>
                $user

        ], 201);
    }


    /**
     * ==========================================================
     * UPDATE USER
     * ==========================================================
     */
    public function update(
        Request $request,
        int $id
    ): JsonResponse {

        $user = User::findOrFail($id);


        $validated = $request->validate([

            'name' => [
                'required',
                'string',
                'max:255'
            ],

            'email' => [
                'required',
                'email',
                'max:255',

                Rule::unique(
                    'users',
                    'email'
                )->ignore($user->id)

            ],

            'password' => [
                'nullable',
                'string',
                'min:8'
            ],

            'is_active' => [
                'sometimes',
                'boolean'
            ],

            'roles' => [
                'required',
                'array',
                'min:1'
            ],

            'roles.*' => [
                'integer',
                'exists:roles,id'
            ],

        ]);


        // ------------------------------------------------------
        // UPDATE BASIC DETAILS
        // ------------------------------------------------------

        $user->name =
            $validated['name'];

        $user->email =
            $validated['email'];


        // ------------------------------------------------------
        // ACTIVE STATUS
        // ------------------------------------------------------

        if (
            array_key_exists(
                'is_active',
                $validated
            )
        ) {

            $user->is_active =
                $validated['is_active'];

        }


        // ------------------------------------------------------
        // PASSWORD
        // ------------------------------------------------------

        if (
            !empty(
                $validated['password']
            )
        ) {

            $user->password =
                $validated['password'];

        }


        $user->save();


        // ------------------------------------------------------
        // UPDATE ROLES
        // ------------------------------------------------------

        $user->roles()->sync(
            $validated['roles']
        );


        // ------------------------------------------------------
        // LOAD RELATIONSHIPS
        // ------------------------------------------------------

        $user->load(
            'roles.permissions'
        );


        return response()->json([

            'message' =>
                'User updated successfully.',

            'data' =>
                $user

        ]);
    }


    /**
     * ==========================================================
     * DELETE USER
     * ==========================================================
     */
    public function destroy(
        int $id
    ): JsonResponse {

        $user =
            User::findOrFail($id);


        // ------------------------------------------------------
        // PREVENT SELF DELETE
        // ------------------------------------------------------

        if (
            auth()->check() &&
            auth()->id() === $user->id
        ) {

            return response()->json([

                'message' =>
                    'You cannot delete your own user account.'

            ], 422);

        }


        // ------------------------------------------------------
        // REMOVE ROLE ASSIGNMENTS
        // ------------------------------------------------------

        $user->roles()->detach();


        // ------------------------------------------------------
        // DELETE USER
        // ------------------------------------------------------

        $user->delete();


        return response()->json([

            'message' =>
                'User deleted successfully.'

        ]);
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | LIST ROLES
    |--------------------------------------------------------------------------
    */

    public function index(): JsonResponse
    {
        $roles = Role::query()
            ->with('permissions')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $roles,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | SHOW ROLE
    |--------------------------------------------------------------------------
    */

    public function show(int $id): JsonResponse
    {
        $role = Role::query()
            ->with('permissions')
            ->findOrFail($id);

        return response()->json([
            'data' => $role,
        ]);
    }
}
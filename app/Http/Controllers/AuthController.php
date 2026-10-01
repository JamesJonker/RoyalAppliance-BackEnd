<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Login
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);


        $user = User::with(
            'roles.permissions'
        )
            ->where(
                'email',
                $credentials['email']
            )
            ->first();


        if (!$user) {

            return response()->json([
                'message' => 'Invalid email or password.'
            ], 401);

        }


        if (!$user->is_active) {

            return response()->json([
                'message' =>
                    'Your account has been disabled. Please contact an administrator.'
            ], 403);

        }


        if (!Hash::check(
            $credentials['password'],
            $user->password
        )) {

            return response()->json([
                'message' => 'Invalid email or password.'
            ], 401);

        }


        // Remove old tokens
        $user->tokens()->delete();


        // Create token
        $token = $user
            ->createToken('staff-management')
            ->plainTextToken;


        return response()->json([

            'message' => 'Login successful.',

            'token' => $token,

            'user' => $user,

        ]);

    }


    /**
     * Logout
     */
    public function logout(Request $request)
    {
        $request
            ->user()
            ->currentAccessToken()
            ->delete();


        return response()->json([
            'message' => 'Logged out successfully.'
        ]);
    }


    /**
     * Authenticated user
     */
    public function user(Request $request)
    {
        return response()->json(
            $request
                ->user()
                ->load('roles.permissions')
        );
    }
}
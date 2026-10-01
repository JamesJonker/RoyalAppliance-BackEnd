<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to
     * when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        /*
        |--------------------------------------------------------------------------
        | API Requests
        |--------------------------------------------------------------------------
        |
        | This application uses Angular as the frontend and Laravel as an API.
        |
        | API requests must NOT be redirected to a Laravel login page.
        | Laravel should instead return a 401 Unauthorized response.
        |
        */

        if ($request->expectsJson()) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | No Laravel Web Login Page
        |--------------------------------------------------------------------------
        |
        | Authentication is handled by Angular using /api/login.
        |
        */

        return null;
    }
}
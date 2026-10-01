<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;


    /*
    |--------------------------------------------------------------------------
    | MASS ASSIGNMENT
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
    ];


    /*
    |--------------------------------------------------------------------------
    | HIDDEN
    |--------------------------------------------------------------------------
    */

    protected $hidden = [
        'password',
        'remember_token',
    ];


    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    */

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];


    /*
    |--------------------------------------------------------------------------
    | ROLES
    |--------------------------------------------------------------------------
    |
    | A user can have MULTIPLE roles.
    |
    | Example:
    |
    | James
    |   ├── Administrator
    |   ├── Payroll
    |   └── HR Manager
    |
    */

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'user_roles'
        )->withTimestamps();
    }


    /*
    |--------------------------------------------------------------------------
    | CHECK PERMISSION
    |--------------------------------------------------------------------------
    */

    public function hasPermission(
        string $permission
    ): bool {

        /*
        |--------------------------------------------------------------
        | Inactive users have no permissions.
        |--------------------------------------------------------------
        */

        if (!$this->is_active) {
            return false;
        }


        /*
        |--------------------------------------------------------------
        | Administrator has everything.
        |--------------------------------------------------------------
        */

        if ($this->hasRole('Administrator')) {
            return true;
        }


        /*
        |--------------------------------------------------------------
        | Check permissions through all assigned roles.
        |--------------------------------------------------------------
        */

        return $this->roles()
            ->whereHas(
                'permissions',
                function ($query) use ($permission) {

                    $query->where(
                        'permissions.name',
                        $permission
                    );

                }
            )
            ->exists();
    }


    /*
    |--------------------------------------------------------------------------
    | CHECK ROLE
    |--------------------------------------------------------------------------
    */

    public function hasRole(
        string $role
    ): bool {

        return $this->roles()
            ->where(
                'roles.name',
                $role
            )
            ->exists();
    }


    /*
    |--------------------------------------------------------------------------
    | GET USER PERMISSIONS
    |--------------------------------------------------------------------------
    */

    public function permissions()
    {
        return Permission::whereHas(
            'roles.users',
            function ($query) {

                $query->where(
                    'users.id',
                    $this->id
                );

            }
        );
    }
}
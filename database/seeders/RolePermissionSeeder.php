<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Permissions
        |--------------------------------------------------------------------------
        */

        $permissions = [

            // Staff
            [
                'name' => 'staff.view',
                'description' => 'View staff members',
            ],
            [
                'name' => 'staff.create',
                'description' => 'Create staff members',
            ],
            [
                'name' => 'staff.edit',
                'description' => 'Edit staff members',
            ],
            [
                'name' => 'staff.delete',
                'description' => 'Delete staff members',
            ],

            // Leave
            [
                'name' => 'leave.view',
                'description' => 'View leave',
            ],
            [
                'name' => 'leave.create',
                'description' => 'Create leave',
            ],
            [
                'name' => 'leave.edit',
                'description' => 'Edit leave',
            ],
            [
                'name' => 'leave.delete',
                'description' => 'Delete leave',
            ],
            [
                'name' => 'leave.approve',
                'description' => 'Approve leave',
            ],
            [
                'name' => 'leave.reject',
                'description' => 'Reject leave',
            ],

            // Payslips
            [
                'name' => 'payslip.view',
                'description' => 'View payslips',
            ],
            [
                'name' => 'payslip.generate',
                'description' => 'Generate payslips',
            ],
            [
                'name' => 'payslip.download',
                'description' => 'Download payslips',
            ],

            // Loans
            [
                'name' => 'loan.view',
                'description' => 'View loans',
            ],
            [
                'name' => 'loan.create',
                'description' => 'Create loans',
            ],
            [
                'name' => 'loan.edit',
                'description' => 'Edit loans',
            ],

            // Payment Runs
            [
                'name' => 'payment_run.view',
                'description' => 'View payment runs',
            ],
            [
                'name' => 'payment_run.create',
                'description' => 'Create payment runs',
            ],
            [
                'name' => 'payment_run.approve',
                'description' => 'Approve payment runs',
            ],
            [
                'name' => 'payment_run.export',
                'description' => 'Export bank payment file',
            ],

            // Stock
            [
                'name' => 'stock.view',
                'description' => 'View stock',
            ],
            [
                'name' => 'stock.create',
                'description' => 'Create stock items',
            ],
            [
                'name' => 'stock.edit',
                'description' => 'Edit stock items',
            ],
            [
                'name' => 'stock.delete',
                'description' => 'Delete stock items',
            ],

            // Users
            [
                'name' => 'users.view',
                'description' => 'View system users',
            ],
            [
                'name' => 'users.create',
                'description' => 'Create system users',
            ],
            [
                'name' => 'users.edit',
                'description' => 'Edit system users',
            ],
            [
                'name' => 'users.delete',
                'description' => 'Delete system users',
            ],

        ];


        foreach ($permissions as $permission) {

            Permission::updateOrCreate(
                [
                    'name' => $permission['name']
                ],
                $permission
            );

        }


        /*
        |--------------------------------------------------------------------------
        | Roles
        |--------------------------------------------------------------------------
        */

        $roles = [

            [
                'name' => 'Administrator',
                'description' => 'Full access to the system',
            ],

            [
                'name' => 'HR',
                'description' => 'Human resources management',
            ],

            [
                'name' => 'Payroll',
                'description' => 'Payroll and payment processing',
            ],

            [
                'name' => 'Manager',
                'description' => 'Management functions',
            ],

            [
                'name' => 'Warehouse',
                'description' => 'Warehouse and stock management',
            ],

            [
                'name' => 'Employee',
                'description' => 'Basic employee access',
            ],

        ];


        foreach ($roles as $role) {

            Role::updateOrCreate(
                [
                    'name' => $role['name']
                ],
                $role
            );

        }


        /*
        |--------------------------------------------------------------------------
        | Assign permissions to roles
        |--------------------------------------------------------------------------
        */


        // Administrator gets every permission

        $administrator = Role::where(
            'name',
            'Administrator'
        )->first();

        $administrator->permissions()->sync(
            Permission::pluck('id')
        );


        // HR

        $hr = Role::where(
            'name',
            'HR'
        )->first();

        $hrPermissions = Permission::whereIn(
            'name',
            [

                'staff.view',
                'staff.create',
                'staff.edit',

                'leave.view',
                'leave.create',
                'leave.edit',
                'leave.delete',
                'leave.approve',
                'leave.reject',

                'loan.view',
                'loan.create',

            ]
        )->pluck('id');

        $hr->permissions()->sync(
            $hrPermissions
        );


        // Payroll

        $payroll = Role::where(
            'name',
            'Payroll'
        )->first();

        $payrollPermissions = Permission::whereIn(
            'name',
            [

                'staff.view',

                'payslip.view',
                'payslip.generate',
                'payslip.download',

                'loan.view',

                'payment_run.view',
                'payment_run.create',
                'payment_run.approve',
                'payment_run.export',

            ]
        )->pluck('id');

        $payroll->permissions()->sync(
            $payrollPermissions
        );


        // Manager

        $manager = Role::where(
            'name',
            'Manager'
        )->first();

        $managerPermissions = Permission::whereIn(
            'name',
            [

                'staff.view',

                'leave.view',
                'leave.approve',
                'leave.reject',

                'payslip.view',

            ]
        )->pluck('id');

        $manager->permissions()->sync(
            $managerPermissions
        );


        // Warehouse

        $warehouse = Role::where(
            'name',
            'Warehouse'
        )->first();

        $warehousePermissions = Permission::whereIn(
            'name',
            [

                'stock.view',
                'stock.create',
                'stock.edit',

            ]
        )->pluck('id');

        $warehouse->permissions()->sync(
            $warehousePermissions
        );


        // Employee

        $employee = Role::where(
            'name',
            'Employee'
        )->first();

        $employeePermissions = Permission::whereIn(
            'name',
            [

                'leave.view',
                'leave.create',

                'payslip.view',
                'payslip.download',

            ]
        )->pluck('id');

        $employee->permissions()->sync(
            $employeePermissions
        );
    }
}
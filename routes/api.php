<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\PayslipController;
use App\Http\Controllers\StockItemController;
use App\Http\Controllers\ItemMasterController;
use App\Http\Controllers\ProductImportController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PayrollRunController;
use App\Http\Controllers\PaymentRunController;
use App\Http\Controllers\NewRangeProductController;
use App\Http\Controllers\NewRangeHistoricalImportController;
use App\Http\Controllers\NewRangeReportController;
use App\Http\Controllers\NewRangeSalesImportController;
use App\Http\Controllers\NewRangeHistoricalStockController;
use App\Http\Controllers\NewRangeStockOnHandController;
use App\Http\Controllers\NewRangeMinimumStockLevelController;
use App\Http\Controllers\NewRangeGrvController;
use App\Http\Controllers\NewRangeIbtController;
use App\Http\Controllers\EmployerTaxSettingController;
use App\Http\Controllers\StaffImportController;
use App\Http\Controllers\NewRangeStockTakeController;
use App\Http\Controllers\NewRangeOrderController;
/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Login
|--------------------------------------------------------------------------
|
| This route is PUBLIC because the user needs to log in before they
| can receive an authentication token.
|
*/

Route::post(
    '/login',
    [AuthController::class, 'login']
);


/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
|
| Everything inside this group requires a valid Sanctum token.
|
*/

Route::middleware('auth:sanctum')->group(function () {


    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    // Logout
    Route::post(
        '/logout',
        [AuthController::class, 'logout']
    );


    // Get currently logged-in user
    Route::get(
        '/user',
        [AuthController::class, 'user']
    );

/*
|--------------------------------------------------------------------------
| Staff
|--------------------------------------------------------------------------
*/

Route::get(
    '/staff',
    [
        StaffController::class,
        'index'
    ]
);


/*
|--------------------------------------------------------------------------
| Create Staff
|--------------------------------------------------------------------------
*/

Route::post(
    '/staff/import-existing',
    [StaffImportController::class, 'import']
);

Route::post(
    '/staff',
    [
        StaffController::class,
        'store'
    ]
);


/*
|--------------------------------------------------------------------------
| Get One Staff Member
|--------------------------------------------------------------------------
*/

Route::get(
    '/staff/{staff}',
    [
        StaffController::class,
        'show'
    ]
);


/*
|--------------------------------------------------------------------------
| Update Staff Member
|--------------------------------------------------------------------------
*/

Route::put(
    '/staff/{staff}',
    [
        StaffController::class,
        'update'
    ]
);


/*
|--------------------------------------------------------------------------
| Delete Staff Member
|--------------------------------------------------------------------------
*/

Route::delete(
    '/staff/{staff}',
    [
        StaffController::class,
        'delete'
    ]
);

    /*
    |--------------------------------------------------------------------------
    | Leave Management
    |--------------------------------------------------------------------------
    */


    /*
    |--------------------------------------------------------------------------
    | Leave Types
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/leave-types',
        [LeaveController::class, 'types']
    );


    /*
    |--------------------------------------------------------------------------
    | Leave Records
    |--------------------------------------------------------------------------
    */

    // List all leave
    //
    // Optional filters:
    //
    // ?staff_id=2
    // ?leave_type_id=1
    // ?status=approved
    // ?from_date=2026-01-01
    // ?to_date=2026-12-31
    //

    Route::get(
        '/leaves',
        [LeaveController::class, 'index']
    );


    // Create leave
    Route::post(
        '/leaves',
        [LeaveController::class, 'store']
    );


    // View a single leave record
    Route::get(
        '/leaves/{id}',
        [LeaveController::class, 'show']
    );


    // Update pending leave
    Route::put(
        '/leaves/{id}',
        [LeaveController::class, 'update']
    );


    // Delete pending/rejected leave
    Route::delete(
        '/leaves/{id}',
        [LeaveController::class, 'destroy']
    );


    /*
    |--------------------------------------------------------------------------
    | Leave Approval
    |--------------------------------------------------------------------------
    */

    // Approve leave
    Route::post(
        '/leaves/{id}/approve',
        [LeaveController::class, 'approve']
    );


    // Reject leave
    Route::post(
        '/leaves/{id}/reject',
        [LeaveController::class, 'reject']
    );


    // Cancel leave
    Route::post(
        '/leaves/{id}/cancel',
        [LeaveController::class, 'cancel']
    );


    /*
    |--------------------------------------------------------------------------
    | Staff Leave Balance
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/staff/{staff}/leave-balance',
        [LeaveController::class, 'balance']
    );


    /*
    |--------------------------------------------------------------------------
    | Staff Leave Summary
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/staff/{staff}/leave-summary',
        [LeaveController::class, 'summary']
    );


    /*
    |--------------------------------------------------------------------------
    | Payslips
    |--------------------------------------------------------------------------
    */


    // Generate one payslip
    Route::post(
        '/payslips/generate',
        [PayslipController::class, 'generate']
    );


    // Generate payslips for a range
    Route::post(
        '/payslips/generate-range',
        [PayslipController::class, 'generateRange']
    );


    // Generate all current month payslips
    Route::post(
        '/payslips/generate-all-current',
        [PayslipController::class, 'generateAllCurrentMonth']
    );


    // Get payslips for staff member
    Route::get(
        '/payslips/staff/{staffId}',
        [PayslipController::class, 'index']
    );


    // Download payslip
    Route::get(
        '/payslips/{id}/download',
        [PayslipController::class, 'download']
    );


    /*
    |--------------------------------------------------------------------------
    | Loans
    |--------------------------------------------------------------------------
    */

    // Apply for loan
    Route::post(
        '/loans/apply',
        [LoanController::class, 'apply']
    );


    // Get loans for staff member
    Route::get(
        '/loans/{staffId}',
        [LoanController::class, 'index']
    );


    /*
    |--------------------------------------------------------------------------
    | Stock Items
    |--------------------------------------------------------------------------
    */

    Route::apiResource(
        'stock-items',
        StockItemController::class
    );


    /*
    |--------------------------------------------------------------------------
    | Stock Item Metadata
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/metadata',
        [StockItemController::class, 'metadata']
    );


    /*
    |--------------------------------------------------------------------------
    | Item Master
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/items/master',
        [ItemMasterController::class, 'index']
    );

    Route::post(
        '/items/master',
        [ItemMasterController::class, 'store']
    );


    /*
    |--------------------------------------------------------------------------
    | Stock Conditions
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/brands/{brandId}/conditions',
        [StockItemController::class, 'getConditionsForBrand']
    );


    /*
    |--------------------------------------------------------------------------
    | Stock Item Custom Routes
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/stock-items/bulk',
        [StockItemController::class, 'storeBulk']
    );


    /*
    |--------------------------------------------------------------------------
    | Product Import
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/import-products',
        [ProductImportController::class, 'import']
    );

   Route::get('/payroll-periods', [PayrollRunController::class, 'periods']);
Route::post('/payroll-periods', [PayrollRunController::class, 'storePeriod']);
Route::get('/payroll-periods/{payrollPeriod}/eligible-staff', [PayrollRunController::class, 'eligibleStaff']);

Route::get('/payroll-runs', [PayrollRunController::class, 'index']);
Route::post('/payroll-runs', [PayrollRunController::class, 'store']);
Route::get('/payroll-runs/{payrollRun}', [PayrollRunController::class, 'show']);
Route::put('/payroll-runs/{payrollRun}', [PayrollRunController::class, 'update']);
Route::post('/payroll-runs/{payrollRun}/calculate', [PayrollRunController::class, 'calculate']);
Route::delete('/payroll-runs/{payrollRun}', [PayrollRunController::class, 'destroy']);
Route::post('/payroll-runs/{payrollRun}/approve', [PayrollRunController::class, 'approve']);
Route::post('/payroll-runs/{payrollRun}/paid', [PayrollRunController::class, 'markPaid']);

/*
|--------------------------------------------------------------------------
| Payment Runs
|--------------------------------------------------------------------------
*/

Route::get(
    '/payment-runs',
    [
        PaymentRunController::class,
        'index'
    ]
);

Route::post(
    '/payment-runs',
    [
        PaymentRunController::class,
        'store'
    ]
);

Route::get(
    '/payment-runs/{paymentRun}',
    [
        PaymentRunController::class,
        'show'
    ]
);

Route::post(
    '/payment-runs/{paymentRun}/validate',
    [
        PaymentRunController::class,
        'validateRun'
    ]
);

Route::post(
    '/payment-runs/{paymentRun}/approve',
    [
        PaymentRunController::class,
        'approve'
    ]
);

Route::get(
    '/payment-runs/{paymentRun}/export',
    [
        PaymentRunController::class,
        'export'
    ]
);

Route::post(
    '/payment-runs/{paymentRun}/mark-paid',
    [
        PaymentRunController::class,
        'markPaid'
    ]
);

Route::post(
    '/payment-runs/{paymentRun}/cancel',
    [
        PaymentRunController::class,
        'cancel'
    ]
);
/*
|--------------------------------------------------------------------------
| User Management
|--------------------------------------------------------------------------
*/

// Get all users
Route::get(
    '/users',
    [UserController::class, 'index']
);


// Get one user
Route::get(
    '/users/{id}',
    [UserController::class, 'show']
);


// Create user
Route::post(
    '/users',
    [UserController::class, 'store']
);


// Update user
Route::put(
    '/users/{id}',
    [UserController::class, 'update']
);


// Delete user
Route::delete(
    '/users/{id}',
    [UserController::class, 'destroy']
);


/*
|--------------------------------------------------------------------------
| Roles
|--------------------------------------------------------------------------
*/

// Get all roles
Route::get(
    '/roles',
    [RoleController::class, 'index']
);


// Get one role
Route::get(
    '/roles/{id}',
    [RoleController::class, 'show']
);
// ==========================================================
// NEW RANGE PRODUCTS
// ==========================================================

Route::get(
    '/new-range/products',
    [NewRangeProductController::class, 'index']
);

Route::post(
    '/new-range/products',
    [NewRangeProductController::class, 'store']
);

Route::post(
    '/new-range/products/import',
    [NewRangeProductController::class, 'import']
);

Route::get(
    '/new-range/products/{newRangeProduct}',
    [NewRangeProductController::class, 'show']
);

Route::put(
    '/new-range/products/{newRangeProduct}',
    [NewRangeProductController::class, 'update']
);
// ==========================================================
// NEW RANGE HISTORICAL IMPORT
// ==========================================================

Route::post(
    '/new-range/historical-import/preview',
    [
        NewRangeHistoricalImportController::class,
        'preview'
    ]
);
// ==========================================================
// NEW RANGE HISTORICAL IMPORT
// ==========================================================

Route::post(
    '/new-range/historical-import/preview',
    [
        NewRangeHistoricalImportController::class,
        'preview'
    ]
);


Route::post(
    '/new-range/historical-import',
    [
        NewRangeHistoricalImportController::class,
        'import'
    ]
);
Route::post(
    '/new-range/historical-stock/preview',
    [
        NewRangeHistoricalStockController::class,
        'preview'
    ]
);


Route::post(
    '/new-range/historical-stock/import',
    [
        NewRangeHistoricalStockController::class,
        'import'
    ]
);
Route::get(
    '/new-range/stock-takes',
    [
        NewRangeStockTakeController::class,
        'index'
    ]
);


Route::post(
    '/new-range/stock-takes',
    [
        NewRangeStockTakeController::class,
        'store'
    ]
);


Route::get(
    '/new-range/stock-takes/{id}',
    [
        NewRangeStockTakeController::class,
        'show'
    ]
);


Route::put(
    '/new-range/stock-takes/{id}',
    [
        NewRangeStockTakeController::class,
        'update'
    ]
);


Route::post(
    '/new-range/stock-takes/{id}/complete',
    [
        NewRangeStockTakeController::class,
        'complete'
    ]
);

Route::post(
    '/new-range/stock-takes/{id}/cancel',
    [
        NewRangeStockTakeController::class,
        'cancel'
    ]
);
// ==========================================================
// NEW RANGE REPORTS
// ==========================================================

Route::get(
    '/new-range/reports/sales-gp',
    [
        NewRangeReportController::class,
        'salesGpReport'
    ]
);
Route::get(
    '/new-range/stock-on-hand',
    [
        NewRangeStockOnHandController::class,
        'index'
    ]
);
Route::get(
    '/new-range/minimum-stock-levels',
    [
        NewRangeMinimumStockLevelController::class,
        'index'
    ]
);


Route::put(
    '/new-range/minimum-stock-levels',
    [
        NewRangeMinimumStockLevelController::class,
        'update'
    ]
);
// ==========================================================
// NEW RANGE DAILY SALES IMPORT
// ==========================================================

Route::post(
    '/new-range/sales-import/preview',
    [
        NewRangeSalesImportController::class,
        'preview'
    ]
);


Route::post(
    '/new-range/sales-import',
    [
        NewRangeSalesImportController::class,
        'import'
    ]
);
Route::post(
    '/new-range/grv',
    [
        NewRangeGrvController::class,
        'store'
    ]
);


Route::post(
    '/new-range/ibt/out',
    [
        NewRangeIbtController::class,
        'storeOut'
    ]
);


Route::get(
    '/new-range/ibt/pending',
    [
        NewRangeIbtController::class,
        'pending'
    ]
);
Route::post(
    '/new-range/orders/generate',
    [
        NewRangeOrderController::class,
        'generate'
    ]
);


Route::get(
    '/new-range/orders/{id}',
    [
        NewRangeOrderController::class,
        'show'
    ]
);


Route::put(
    '/new-range/orders/{id}',
    [
        NewRangeOrderController::class,
        'update'
    ]
);


Route::get(
    '/new-range/orders/{id}/export',
    [
        NewRangeOrderController::class,
        'export'
    ]
);


Route::post(
    '/new-range/ibt/{ibt}/in',
    [
        NewRangeIbtController::class,
        'receive'
    ]
);
//---------------------------------------
// EMPLOYER TAX SETTINGS
//--------------------------------------
Route::get(
    '/payroll/employer-tax-settings',
    [
        EmployerTaxSettingController::class,
        'show'
    ]
);


Route::put(
    '/payroll/employer-tax-settings',
    [
        EmployerTaxSettingController::class,
        'save'
    ]
);
});
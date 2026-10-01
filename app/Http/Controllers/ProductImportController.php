<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ProductsMasterImport;

class ProductImportController extends Controller
{
    public function import()
    {
        Excel::import(new ProductsMasterImport, storage_path('app/new.xlsx'));

        return response()->json(['message' => 'Import completed successfully']);
    }
}

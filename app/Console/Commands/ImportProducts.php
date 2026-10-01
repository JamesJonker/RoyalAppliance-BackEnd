<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ProductsImport;

class ImportProducts extends Command
{
    protected $signature = 'import:products {file}';
    protected $description = 'Import products from an Excel file';

    public function handle()
    {
        $file = $this->argument('file');

        if (!file_exists($file)) {
            $this->error("File not found: $file");
            return;
        }

        Excel::import(new ProductsImport, $file);
        $this->info('Product import completed successfully.');
    }
}

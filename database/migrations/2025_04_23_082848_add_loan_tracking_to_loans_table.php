<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('remaining_amount', 10, 2)->after('amount')->default(0);
            $table->enum('status', ['active', 'completed'])->after('remaining_amount')->default('active');
        });
    }
    
    public function down()
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['remaining_amount', 'status']);
        });
    }
    
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specials', function (Blueprint $table) {
            $table->id();
            $table->string('title');                // Special title
            $table->text('description')->nullable(); // Optional description
            $table->string('image_path');           // Path or URL to image
            $table->date('from_date');              // Start date
            $table->date('to_date');                // End date
            $table->timestamps();                   // created_at & updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('specials');
    }
};


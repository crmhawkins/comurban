<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('language', 10);
            $table->enum('category', ['MARKETING', 'UTILITY', 'AUTHENTICATION']);
            $table->enum('status', ['APPROVED', 'PENDING', 'REJECTED'])->default('PENDING');
            $table->json('components');
            $table->string('meta_template_id', 100)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};

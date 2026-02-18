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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('wa_id', 50)->unique()->comment('WhatsApp ID del contacto');
            $table->string('phone_number', 20);
            $table->string('name')->nullable();
            $table->string('profile_name')->nullable();
            $table->string('profile_picture_url', 500)->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};

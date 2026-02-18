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
        Schema::create('calls', function (Blueprint $table) {
            $table->id();
            $table->string('elevenlabs_call_id')->unique()->nullable();
            $table->string('phone_number')->nullable();
            $table->string('status')->default('pending'); // pending, in_progress, completed, failed
            $table->text('transcript')->nullable();
            $table->json('metadata')->nullable(); // Para almacenar datos adicionales de ElevenLabs
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration')->nullable(); // Duración en segundos
            $table->string('recording_url')->nullable();
            $table->text('summary')->nullable();
            $table->timestamps();
            
            $table->index('phone_number');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calls');
    }
};

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
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('source_type'); // 'whatsapp' o 'call'
            $table->unsignedBigInteger('source_id')->nullable(); // ID de conversation o call
            $table->unsignedBigInteger('conversation_id')->nullable(); // Para WhatsApp
            $table->unsignedBigInteger('call_id')->nullable(); // Para llamadas
            $table->unsignedBigInteger('contact_id')->nullable(); // Contacto relacionado
            $table->string('phone_number')->nullable(); // Número de teléfono
            $table->string('incident_summary'); // Resumen corto (ej: "Gotera en apartamento")
            $table->text('conversation_summary')->nullable(); // Resumen general de la conversación
            $table->string('incident_type')->nullable(); // Tipo de incidencia (gotera, rotura, etc.)
            $table->decimal('confidence', 3, 2)->default(0.5); // Confianza de la detección (0-1)
            $table->string('status')->default('open'); // open, in_progress, resolved, closed
            $table->text('detection_context')->nullable(); // Contexto usado para la detección (para evitar duplicados)
            $table->timestamps();
            
            $table->index('source_type');
            $table->index('conversation_id');
            $table->index('call_id');
            $table->index('contact_id');
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
        Schema::dropIfExists('incidents');
    }
};

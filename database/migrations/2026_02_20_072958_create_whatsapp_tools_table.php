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
        Schema::create('whatsapp_tools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->enum('type', ['custom', 'predefined'])->default('custom')->comment('Tipo de tool: custom o predefined');
            $table->string('predefined_type', 50)->nullable()->comment('Tipo de tool predefinida: email, whatsapp, etc.');
            $table->enum('method', ['GET', 'POST'])->nullable()->default('GET');
            $table->string('endpoint', 500)->nullable()->comment('Endpoint personalizado (solo para custom)');
            $table->text('json_format')->nullable()->comment('Formato JSON para POST');
            $table->integer('timeout')->default(30)->comment('Timeout en segundos');
            $table->json('headers')->nullable()->comment('Headers opcionales en formato JSON');
            $table->json('config')->nullable()->comment('Configuración adicional para tools predefinidas');
            $table->boolean('active')->default(true);
            $table->integer('order')->default(0)->comment('Orden de prioridad');
            $table->timestamps();
            
            $table->index('active');
            $table->index('order');
            $table->index('type');
            $table->index('predefined_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_tools');
    }
};

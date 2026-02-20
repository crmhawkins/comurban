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
        Schema::table('whatsapp_tools', function (Blueprint $table) {
            // Añadir type si no existe
            if (!Schema::hasColumn('whatsapp_tools', 'type')) {
                $table->enum('type', ['custom', 'predefined'])->default('custom')->after('description')->comment('Tipo de tool: custom o predefined');
                // Solo crear índice si acabamos de crear la columna
                $table->index('type');
            }
            
            // Añadir predefined_type si no existe
            if (!Schema::hasColumn('whatsapp_tools', 'predefined_type')) {
                $table->string('predefined_type', 50)->nullable()->after('type')->comment('Tipo de tool predefinida: email, whatsapp, etc.');
                // Solo crear índice si acabamos de crear la columna
                $table->index('predefined_type');
            }
            
            // Añadir config si no existe
            if (!Schema::hasColumn('whatsapp_tools', 'config')) {
                $table->json('config')->nullable()->after('headers')->comment('Configuración adicional para tools predefinidas');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_tools', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_tools', 'type')) {
                $table->dropIndex(['type']);
                $table->dropColumn('type');
            }
            if (Schema::hasColumn('whatsapp_tools', 'predefined_type')) {
                $table->dropIndex(['predefined_type']);
                $table->dropColumn('predefined_type');
            }
            if (Schema::hasColumn('whatsapp_tools', 'config')) {
                $table->dropColumn('config');
            }
        });
    }
};

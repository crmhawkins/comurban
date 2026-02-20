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
            // Verificar si la columna config existe
            if (Schema::hasColumn('whatsapp_tools', 'config')) {
                $table->foreignId('email_account_id')->nullable()->after('config')->constrained('email_accounts')->onDelete('set null');
            } else {
                // Si no existe config, añadir después de headers o timeout
                if (Schema::hasColumn('whatsapp_tools', 'headers')) {
                    $table->foreignId('email_account_id')->nullable()->after('headers')->constrained('email_accounts')->onDelete('set null');
                } else {
                    $table->foreignId('email_account_id')->nullable()->after('timeout')->constrained('email_accounts')->onDelete('set null');
                }
            }
            $table->index('email_account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_tools', function (Blueprint $table) {
            $table->dropForeign(['email_account_id']);
            $table->dropIndex(['email_account_id']);
            $table->dropColumn('email_account_id');
        });
    }
};

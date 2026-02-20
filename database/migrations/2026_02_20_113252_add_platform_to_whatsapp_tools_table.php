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
            $table->enum('platform', ['whatsapp', 'elevenlabs', 'both'])->default('whatsapp')->after('active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_tools', function (Blueprint $table) {
            $table->dropColumn('platform');
        });
    }
};

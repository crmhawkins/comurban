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
        Schema::table('messages', function (Blueprint $table) {
            $table->timestamp('ai_responded_at')->nullable()->after('read_at')->comment('Timestamp cuando la IA respondió a este mensaje');
            $table->index('ai_responded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['ai_responded_at']);
            $table->dropColumn('ai_responded_at');
        });
    }
};

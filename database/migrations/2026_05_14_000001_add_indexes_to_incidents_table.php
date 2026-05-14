<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->index('conversation_id');
            $table->index('call_id');
            $table->index('status');
        });
    }
    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropIndex(['conversation_id']);
            $table->dropIndex(['call_id']);
            $table->dropIndex(['status']);
        });
    }
};

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
        Schema::table('calls', function (Blueprint $table) {
            $table->boolean('is_transferred')->default(false)->after('status');
            $table->string('transferred_to')->nullable()->after('is_transferred');
            $table->string('transfer_type')->nullable()->after('transferred_to'); // 'agent' o 'phone'
            $table->timestamp('transfer_detected_at')->nullable()->after('transfer_type');
            
            $table->index('is_transferred');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->dropIndex(['is_transferred']);
            $table->dropColumn(['is_transferred', 'transferred_to', 'transfer_type', 'transfer_detected_at']);
        });
    }
};

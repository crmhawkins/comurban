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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->onDelete('cascade');
            $table->string('wa_message_id', 100)->unique()->comment('ID de WhatsApp API');
            $table->enum('direction', ['inbound', 'outbound']);
            $table->enum('type', ['text', 'image', 'video', 'audio', 'document', 'location', 'contact', 'template', 'sticker']);
            $table->text('body')->nullable();
            $table->string('media_url', 500)->nullable();
            $table->string('media_id', 100)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->string('file_name')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->text('caption')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('template_name', 100)->nullable();
            $table->enum('status', ['sending', 'sent', 'delivered', 'read', 'failed'])->default('sending');
            $table->string('error_code', 50)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->bigInteger('wa_timestamp');
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index('conversation_id');
            $table->index('wa_message_id');
            $table->index('status');
            $table->index('direction');
            $table->index('created_at');
            $table->index(['conversation_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};

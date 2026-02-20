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
        Schema::create('email_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Nombre descriptivo de la cuenta');
            $table->string('email')->comment('Dirección de correo');
            $table->string('mailer')->default('smtp')->comment('Tipo de mailer: smtp, sendmail, etc.');
            $table->string('host')->nullable()->comment('Servidor SMTP');
            $table->integer('port')->default(587)->comment('Puerto SMTP');
            $table->string('encryption')->default('tls')->comment('Encriptación: tls, ssl, null');
            $table->string('username')->nullable()->comment('Usuario SMTP');
            $table->text('password')->nullable()->comment('Contraseña SMTP (encriptada)');
            $table->string('from_address')->nullable()->comment('Dirección de envío (from)');
            $table->string('from_name')->nullable()->comment('Nombre del remitente');
            $table->boolean('active')->default(true);
            $table->integer('order')->default(0)->comment('Orden de prioridad');
            $table->timestamps();
            
            $table->index('active');
            $table->index('order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_accounts');
    }
};

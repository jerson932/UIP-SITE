<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bandeja de enviados (SMTP)
        Schema::create('correos_enviados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->nullable()->constrained('solicitudes')->nullOnDelete();
            $table->foreignId('plantilla_id')->nullable()->constrained('plantillas_correo')->nullOnDelete();
            $table->foreignId('enviado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('destinatario', 255);
            $table->string('asunto', 255);
            $table->text('cuerpo');
            $table->enum('estado_entrega', ['pendiente', 'enviado', 'fallido'])->default('pendiente');
            $table->timestamp('enviado_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correos_enviados');
    }
};

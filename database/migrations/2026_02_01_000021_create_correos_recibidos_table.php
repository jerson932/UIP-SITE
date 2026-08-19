<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bandeja de recibidos (IMAP)
        Schema::create('correos_recibidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->nullable()->constrained('solicitudes')->nullOnDelete();
            $table->string('remitente', 255);
            $table->string('asunto', 255);
            $table->text('cuerpo');
            $table->timestamp('recibido_en');
            $table->enum('estado', ['asociado', 'pendiente'])->default('pendiente');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correos_recibidos');
    }
};

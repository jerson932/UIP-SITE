<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Solicitudes de ampliacion (se registran aunque no sean procedentes, para auditoria)
        Schema::create('ampliaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('solicitudes')->cascadeOnDelete();
            $table->date('fecha_solicitud');
            $table->text('descripcion');
            $table->enum('estado', ['recibida', 'rechazada_no_regulada'])->default('recibida');
            $table->boolean('respuesta_enviada')->default(false);
            $table->date('fecha_respuesta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ampliaciones');
    }
};

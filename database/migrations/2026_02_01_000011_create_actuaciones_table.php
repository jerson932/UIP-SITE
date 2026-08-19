<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Registro formal de cada actuacion (spec tabla 7)
        Schema::create('actuaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('solicitudes')->cascadeOnDelete();
            $table->enum('tipo', ['aclaracion', 'respuesta_aclaracion', 'prorroga', 'ampliacion', 'recurso_revision', 'notificacion_resolucion', 'finalizacion', 'otra']);
            $table->enum('iniciado_por', ['ciudadano', 'uip'])->default('uip');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('fecha');
            $table->text('descripcion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actuaciones');
    }
};

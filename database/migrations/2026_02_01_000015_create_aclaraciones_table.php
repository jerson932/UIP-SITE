<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Aclaraciones solicitadas al ciudadano (plazo real: 2 dias habiles)
        Schema::create('aclaraciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('solicitudes')->cascadeOnDelete();
            $table->foreignId('actuacion_id')->nullable()->constrained('actuaciones')->nullOnDelete();
            $table->foreignId('documento_id')->nullable()->constrained('documentos')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->date('fecha_solicitud');
            $table->integer('plazo_dias_habiles')->default(2);
            $table->date('fecha_limite_respuesta');
            $table->date('fecha_respuesta')->nullable();
            $table->text('respuesta')->nullable();
            $table->enum('estado', ['pendiente', 'respondida', 'vencida'])->default('pendiente');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aclaraciones');
    }
};

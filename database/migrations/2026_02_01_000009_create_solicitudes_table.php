<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Expedientes / solicitudes de informacion publica
        Schema::create('solicitudes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_ns', 255)->unique();
            $table->string('contrasena', 255)->nullable()->unique();
            $table->string('codigo_acceso', 255)->unique();
            $table->foreignId('solicitante_id')->constrained('solicitantes')->restrictOnDelete();
            $table->text('asunto');
            $table->enum('medio_recepcion', ['fisica', 'electronica', 'correo'])->default('electronica');
            $table->enum('es_informacion_publica', ['si', 'no', 'pendiente'])->default('pendiente');
            $table->enum('es_competencia', ['si', 'no', 'pendiente'])->default('pendiente');
            $table->boolean('requiere_aclaracion')->default(false);
            $table->text('observaciones')->nullable();
            $table->foreignId('estado_id')->constrained('solicitud_estados')->restrictOnDelete();
            $table->foreignId('dependencia_id')->nullable()->constrained('dependencias')->nullOnDelete();
            $table->foreignId('enlace_id')->nullable()->constrained('enlaces')->nullOnDelete();
            $table->date('fecha_ingreso');
            $table->date('fecha_vencimiento')->nullable();
            $table->date('fecha_respuesta')->nullable();
            $table->date('fecha_finalizacion')->nullable();
            $table->foreignId('creado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Recursos de revision (correlativo independiente de la solicitud)
        Schema::create('recursos_revision', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('solicitudes')->cascadeOnDelete();
            $table->foreignId('documento_id')->nullable()->constrained('documentos')->nullOnDelete();
            $table->string('correlativo', 255)->unique();
            $table->date('fecha_presentacion');
            $table->date('fecha_vencimiento')->nullable();
            $table->text('motivo');
            $table->enum('estado', ['recibido', 'en_tramite', 'resuelto'])->default('recibido');
            $table->date('fecha_resolucion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recursos_revision');
    }
};

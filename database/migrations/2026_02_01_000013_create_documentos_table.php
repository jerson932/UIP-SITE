<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Documentos internos y publicados al ciudadano
        Schema::create('documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('solicitudes')->cascadeOnDelete();
            $table->foreignId('actuacion_id')->nullable()->constrained('actuaciones')->nullOnDelete();
            $table->foreignId('plantilla_id')->nullable()->constrained('plantillas_documentos')->nullOnDelete();
            $table->string('nombre', 255);
            $table->string('ruta_archivo', 255);
            $table->enum('tipo', ['docx', 'pdf', 'otro'])->default('pdf');
            $table->boolean('visible_ciudadano')->default(false);
            $table->foreignId('subido_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('subido_por_ciudadano')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos');
    }
};

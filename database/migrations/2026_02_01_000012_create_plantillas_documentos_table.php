<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plantillas de documentos generables (spec tabla 3)
        Schema::create('plantillas_documentos', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 255)->unique();
            $table->string('nombre', 255);
            $table->enum('tipo', ['docx', 'pdf'])->default('docx');
            $table->text('contenido');
            $table->boolean('visible_ciudadano_default')->default(false);
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantillas_documentos');
    }
};

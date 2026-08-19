<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catalogo configurable de estados (spec seccion 25)
        Schema::create('solicitud_estados', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 255)->unique();
            $table->string('etiqueta', 255);
            $table->string('color', 255)->nullable();
            $table->integer('orden')->default(0);
            $table->boolean('es_final')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_estados');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dependencias/unidades a las que se asignan solicitudes (spec tabla 10)
        Schema::create('dependencias', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 255)->nullable()->unique();
            $table->string('nombre', 255);
            $table->text('descripcion')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dependencias');
    }
};

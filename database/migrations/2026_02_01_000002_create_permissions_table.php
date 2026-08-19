<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catalogo de permisos granulares
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 255)->unique();
            $table->string('nombre', 255);
            $table->text('descripcion')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Parametros generales del sistema
        Schema::create('configuracion', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 255)->unique();
            $table->text('valor')->nullable();
            $table->text('descripcion')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion');
    }
};

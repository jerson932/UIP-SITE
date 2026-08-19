<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plantillas de correo con el tono/formato real de la UIP
        Schema::create('plantillas_correo', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 255)->unique();
            $table->string('evento', 255);
            $table->string('asunto_template', 255);
            $table->text('cuerpo_template');
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantillas_correo');
    }
};

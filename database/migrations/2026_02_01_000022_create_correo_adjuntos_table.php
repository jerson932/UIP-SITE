<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Adjuntos de correos enviados/recibidos
        Schema::create('correo_adjuntos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('correo_enviado_id')->nullable()->constrained('correos_enviados')->cascadeOnDelete();
            $table->foreignId('correo_recibido_id')->nullable()->constrained('correos_recibidos')->cascadeOnDelete();
            $table->string('nombre_archivo', 255);
            $table->string('ruta_archivo', 255);
            $table->integer('tamano_bytes')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correo_adjuntos');
    }
};

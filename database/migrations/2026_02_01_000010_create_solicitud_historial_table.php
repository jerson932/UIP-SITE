<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Linea de tiempo / bitacora de cada expediente (solo lectura)
        Schema::create('solicitud_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('solicitudes')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('tipo_actor', ['sistema', 'administrador', 'ciudadano'])->default('sistema');
            $table->text('descripcion');
            $table->foreignId('estado_anterior_id')->nullable()->constrained('solicitud_estados')->nullOnDelete();
            $table->foreignId('estado_nuevo_id')->nullable()->constrained('solicitud_estados')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_historial');
    }
};

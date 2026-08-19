<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Notificaciones internas para el personal UIP
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('solicitud_id')->nullable()->constrained('solicitudes')->cascadeOnDelete();
            $table->enum('tipo', ['vencimiento_proximo', 'vencida', 'aclaracion_pendiente', 'recurso_pendiente', 'otra']);
            $table->string('mensaje', 255);
            $table->boolean('leida')->default(false);
            $table->timestamp('leida_en')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};

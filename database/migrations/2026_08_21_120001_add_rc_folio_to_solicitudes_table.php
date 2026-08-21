<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RC y FOLIO del expediente físico en la UIP — se usan al generar el
        // Oficio/Providencia de traslado a la dependencia (Fase 19). Son
        // manuales, igual que "contrasena" (el número oficial de la
        // solicitud): la especificación es explícita en que estos números
        // los asigna el personal, el sistema no debe inventarlos.
        Schema::table('solicitudes', function (Blueprint $table) {
            $table->string('rc', 50)->nullable()->after('contrasena');
            $table->string('folio', 50)->nullable()->after('rc');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes', function (Blueprint $table) {
            $table->dropColumn(['rc', 'folio']);
        });
    }
};

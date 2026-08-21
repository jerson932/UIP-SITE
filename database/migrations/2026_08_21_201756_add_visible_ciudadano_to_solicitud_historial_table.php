<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fase 22b: separa qué entradas del historial son seguras de mostrar en el
// portal público del ciudadano de las que son deliberación interna (a qué
// dependencia se asignó el expediente, correos ad-hoc que mandó un
// administrador, observaciones internas del enlace) — el usuario reportó
// que el seguimiento del ciudadano estaba mostrando a qué dependencia/
// enlace se había asignado el expediente, algo que nunca se pensó exponer.
// Por defecto true (la mayoría de entradas ya existentes son hitos que sí
// le interesan al ciudadano: aceptación, contraseña, prórroga, aclaración,
// recurso, finalización); los controladores marcan false explícitamente
// solo en las entradas que revelan detalle interno.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitud_historial', function (Blueprint $table) {
            $table->boolean('visible_ciudadano')->default(true)->after('metadata');
        });
    }

    public function down(): void
    {
        Schema::table('solicitud_historial', function (Blueprint $table) {
            $table->dropColumn('visible_ciudadano');
        });
    }
};

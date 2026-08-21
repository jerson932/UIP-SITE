<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Qué plantilla de oficio/providencia le corresponde a esta
        // dependencia (clave de plantillas_documentos). Nula = usa la
        // Providencia genérica. Guardarlo en la dependencia (en vez de
        // codificarlo en PHP con ids fijos) permite que un administrador
        // ajuste el mapeo después sin tocar código.
        Schema::table('dependencias', function (Blueprint $table) {
            $table->string('plantilla_clave', 100)->nullable()->after('activa');
        });
    }

    public function down(): void
    {
        Schema::table('dependencias', function (Blueprint $table) {
            $table->dropColumn('plantilla_clave');
        });
    }
};

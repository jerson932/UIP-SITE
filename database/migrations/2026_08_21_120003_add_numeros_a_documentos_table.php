<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Número de oficio o de providencia que llevó ESTE documento
        // generado en particular (uno de los dos, según el tipo de
        // plantilla usada) — se guarda por documento y no en la solicitud
        // porque un expediente puede generar más de un oficio/providencia
        // a lo largo del tiempo (reasignación a otra dependencia, reenvío).
        Schema::table('documentos', function (Blueprint $table) {
            $table->string('no_oficio', 50)->nullable()->after('tipo');
            $table->string('no_providencia', 50)->nullable()->after('no_oficio');
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropColumn(['no_oficio', 'no_providencia']);
        });
    }
};

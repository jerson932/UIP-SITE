<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A qué dependencia fue dirigido ESTE oficio/providencia en
        // particular. Es independiente de solicitudes.dependencia_id: un
        // mismo expediente puede generar varios oficios/providencias hacia
        // distintas dependencias a lo largo del tiempo (el "dependencia_id"
        // de la solicitud es solo la asignación "actual"/principal).
        Schema::table('documentos', function (Blueprint $table) {
            $table->foreignId('dependencia_id')->nullable()->after('plantilla_id')->constrained('dependencias')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dependencia_id');
        });
    }
};

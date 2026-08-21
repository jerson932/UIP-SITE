<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fase 22b:
// - recursos_revision.correlativo pasa a ser opcional: cuando el propio
//   ciudadano pide un recurso de revisión desde su portal de seguimiento
//   (nuevo, self-service), todavía no tiene número de correlativo — ese
//   número oficial lo asigna la UIP manualmente, igual que el resto de
//   números "oficiales" del sistema (RC, folio, no. de oficio/providencia).
//   Se usa Blueprint::change() (no SQL crudo): a pesar de que una nota
//   anterior en este proyecto decía que doctrine/dbal no estaba instalado
//   y por eso había que usar DB::statement(), se comprobó directamente que
//   Laravel 13 YA NO depende de doctrine/dbal para esto (tiene su propio
//   compileChange() nativo por driver) — y el SQL crudo original
//   ("ALTER TABLE ... ALTER COLUMN ... DROP NOT NULL") es sintaxis
//   exclusiva de PostgreSQL, así que rompía toda la suite de tests, que
//   corre sobre SQLite en memoria (ver phpunit.xml). change() sí funciona
//   igual en ambos motores.
// - ampliaciones gana documento_id, al igual que prorrogas/aclaraciones/
//   recursos_revision desde la Fase 21 — para poder adjuntar un PDF a la
//   respuesta de una ampliación, tal como ya se podía en las otras
//   actuaciones (Fase 22).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recursos_revision', function (Blueprint $table) {
            $table->string('correlativo')->nullable()->change();
        });

        Schema::table('ampliaciones', function (Blueprint $table) {
            $table->foreignId('documento_id')->nullable()->after('solicitud_id')->constrained('documentos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ampliaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('documento_id');
        });

        Schema::table('recursos_revision', function (Blueprint $table) {
            $table->string('correlativo')->nullable(false)->change();
        });
    }
};

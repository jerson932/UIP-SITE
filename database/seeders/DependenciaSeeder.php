<?php

namespace Database\Seeders;

use App\Models\Dependencia;
use App\Models\Enlace;
use Illuminate\Database\Seeder;

class DependenciaSeeder extends Seeder
{
    public function run(): void
    {
        // Las 5 dependencias de muestra de las fases iniciales (Fase 7):
        // se crean primero tal como siempre (SolicitudDemoSeeder depende
        // de que estos 5 "codigo" existan, incluso en una instalación
        // nueva con "migrate:fresh --seed"). 3 de ellas coinciden con
        // nombres reales de la lista de 32 (Fase 19) y se renombran justo
        // después, en vez de crear una fila duplicada — conservan su
        // "codigo" y su enlace de ejemplo.
        $demo = [
            ['codigo' => 'PLANIF', 'nombre' => 'Dirección de Planificación', 'enlace' => 'Lic. Marta Solís'],
            ['codigo' => 'FIN', 'nombre' => 'Dirección de Finanzas', 'enlace' => 'Ing. Pedro Ramírez'],
            ['codigo' => 'JURID', 'nombre' => 'Dirección de Asuntos Jurídicos', 'enlace' => 'Licda. Rosa Ixchop'],
            ['codigo' => 'RRHH', 'nombre' => 'Dirección de Recursos Humanos', 'enlace' => 'Lic. Jorge Tzul'],
            ['codigo' => 'ADMIN', 'nombre' => 'Dirección Administrativa', 'enlace' => 'Ing. Sofía Reyes'],
        ];

        foreach ($demo as $d) {
            $dep = Dependencia::updateOrCreate(['codigo' => $d['codigo']], [
                'nombre' => $d['nombre'],
                'activa' => true,
            ]);

            Enlace::updateOrCreate(
                ['dependencia_id' => $dep->id, 'nombre' => $d['enlace']],
                [
                    'correo' => strtolower(str_replace(' ', '.', $d['codigo'])).'.enlace@mingob.gob.gt',
                    'telefono' => null,
                    'activo' => true,
                ]
            );
        }

        $muestraActualizada = [
            'PLANIF' => 'Dirección de Planificación de este Ministerio',
            'RRHH' => 'Dirección de Recursos Humanos de este Ministerio',
            'JURID' => 'Dirección de Asuntos Jurídicos de este Ministerio',
        ];

        foreach ($muestraActualizada as $codigo => $nombreReal) {
            Dependencia::where('codigo', $codigo)->update(['nombre' => $nombreReal]);
        }

        // "Dirección de Finanzas" y "Dirección Administrativa" (muestra de
        // las fases iniciales) no tienen equivalente exacto en la lista
        // real de 32 — se desactivan para que no aparezcan duplicadas en
        // el selector, sin borrarlas (evita romper expedientes de muestra
        // que ya las referencian).
        Dependencia::whereIn('codigo', ['FIN', 'ADMIN'])->update(['activa' => false]);

        // Lista real de dependencias/despachos de la UIP-MINGOB a las que
        // se puede trasladar una solicitud (proporcionada por el usuario,
        // orden tal como la entregó). "plantilla_clave" solo se llena en
        // las que tienen una plantilla de Oficio/Providencia especial
        // (resources/plantillas_oficiales/*.docx); el resto usa
        // "providencia_generica" por defecto (ver DocumentoOficialService).
        $dependencias = [
            ['codigo' => 'UAF', 'nombre' => 'Unidad de Administración Financiera de este Ministerio', 'plantilla_clave' => null],
            ['codigo' => 'DIGESSP', 'nombre' => 'Dirección General de Servicios de Seguridad Privada', 'plantilla_clave' => 'providencia_digessp'],
            ['codigo' => 'OTRO', 'nombre' => 'Otro', 'plantilla_clave' => null],
            ['codigo' => 'VICE3', 'nombre' => 'Tercer Viceministerio de Gobernación', 'plantilla_clave' => 'oficio_tercer_vice'],
            ['codigo' => 'DGDCA', 'nombre' => 'Dirección General del Diario de Centro América y Tipografía Nacional', 'plantilla_clave' => null],
            ['codigo' => 'INSPECTORIA', 'nombre' => 'Inspectoría General de este Ministerio', 'plantilla_clave' => null],
            ['codigo' => 'SESC', 'nombre' => 'Secretaría Ejecutiva del Servicio Cívico', 'plantilla_clave' => null],
            ['codigo' => 'VICE1', 'nombre' => 'Primer Viceministerio', 'plantilla_clave' => 'oficio_primer_vice'],
            ['codigo' => 'VICE2', 'nombre' => 'Segundo Viceministerio', 'plantilla_clave' => 'oficio_segundo_vice'],
            ['codigo' => 'VICE4', 'nombre' => 'Cuarto Viceministerio', 'plantilla_clave' => 'oficio_cuarto_vice'],
            ['codigo' => 'VICE5', 'nombre' => 'Quinto Viceministerio', 'plantilla_clave' => 'oficio_quinto_vice'],
            ['codigo' => 'DESPACHO', 'nombre' => 'Despacho Superior', 'plantilla_clave' => 'oficio_despacho'],
            ['codigo' => 'PNC', 'nombre' => 'Dirección General de la Policía Nacional Civil', 'plantilla_clave' => 'providencia_pnc'],
            ['codigo' => 'DGSP', 'nombre' => 'Dirección General del Sistema Penitenciario', 'plantilla_clave' => null],
            ['codigo' => 'UPCV', 'nombre' => 'Unidad para la Prevención Comunitaria de la Violencia de este Ministerio', 'plantilla_clave' => null],
            ['codigo' => 'DSAF', 'nombre' => 'Dirección de Servicios Administrativos y Financieros de este Ministerio', 'plantilla_clave' => null],
            ['codigo' => 'SUBADMIN', 'nombre' => 'Subdirección Administrativa de este Ministerio', 'plantilla_clave' => null],
            ['codigo' => 'ESCRIBANIA', 'nombre' => 'Escribanía de Cámara y de Gobierno y Sección de Tierras de este Ministerio', 'plantilla_clave' => null],
            ['codigo' => 'INFORMATICA', 'nombre' => 'Dirección de Informática de este Ministerio', 'plantilla_clave' => null],
            ['codigo' => 'COMSOCIAL', 'nombre' => 'Asesoría Específica de Comunicación Social de este Ministerio', 'plantilla_clave' => null],
            ['codigo' => 'TELEMATICO', 'nombre' => 'Unidad de Control Telemático de este Ministerio', 'plantilla_clave' => null],
            ['codigo' => 'UEPP', 'nombre' => 'Unidad Especial de Ejecución del Programa de Préstamo de este Ministerio', 'plantilla_clave' => null],
            ['codigo' => 'UNMGP', 'nombre' => 'Unidad del Nuevo Modelo de Gestión Penitenciaria de este Ministerio', 'plantilla_clave' => null],
            ['codigo' => 'AUDITORIA', 'nombre' => 'Unidad de Auditoría Interna de este Ministerio', 'plantilla_clave' => null],
            ['codigo' => 'GOBDEPTO', 'nombre' => 'Asesoría Específica de Gobernaciones Departamentales de este Ministerio', 'plantilla_clave' => null],
            ['codigo' => 'ANTINARCOTICOS', 'nombre' => 'Unidad Especial Antinarcóticos de este Ministerio', 'plantilla_clave' => null],
            ['codigo' => 'DIGICI', 'nombre' => 'Dirección General de Inteligencia Civil', 'plantilla_clave' => null],
            ['codigo' => 'DIGICRI', 'nombre' => 'Dirección General de Investigación Criminal', 'plantilla_clave' => null],
            ['codigo' => 'REPEJU', 'nombre' => 'Registro de Personas Jurídicas', 'plantilla_clave' => 'providencia_repeju'],
        ];

        foreach ($dependencias as $d) {
            Dependencia::updateOrCreate(['codigo' => $d['codigo']], [
                'nombre' => $d['nombre'],
                'activa' => true,
                'plantilla_clave' => $d['plantilla_clave'],
            ]);
        }
    }
}

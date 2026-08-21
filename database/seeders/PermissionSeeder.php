<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Rol;
use Illuminate\Database\Seeder;

// Permisos granulares + asignacion a roles.
// El Administrador recibe todos los permisos; los demas roles reciben un
// subconjunto razonable segun el "alcance" descrito en la spec (tabla 10).
// Esto es un punto de partida para la Fase 3 (autenticacion/roles/permisos),
// se puede ajustar sin tocar el esquema.
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permisos = [
            'solicitudes.ver' => 'Ver listado y detalle de solicitudes',
            'solicitudes.crear' => 'Registrar una solicitud nueva (recepcion fisica, correo o electronica)',
            'solicitudes.validar' => 'Aceptar/rechazar una solicitud (es informacion publica / es competencia)',
            'solicitudes.asignar_contrasena' => 'Asignar la Contraseña No. una vez aceptada la solicitud',
            'solicitudes.asignar_dependencia' => 'Asignar dependencia/enlace responsable',
            'solicitudes.generar_documento' => 'Generar el Oficio o Providencia de traslado a la dependencia asignada',
            'solicitudes.finalizar' => 'Finalizar un expediente',
            'solicitudes.ajustar_vencimiento' => 'Ajustar manualmente la fecha de vencimiento (recurso de revision, casos especiales)',
            'actuaciones.prorroga' => 'Registrar prorroga',
            'actuaciones.aclaracion' => 'Solicitar aclaracion al ciudadano',
            'actuaciones.ampliacion' => 'Registrar ampliacion de informacion solicitada',
            'actuaciones.recurso' => 'Gestionar recursos de revision',
            'documentos.subir' => 'Cargar documentos al expediente',
            'documentos.publicar' => 'Publicar documentos visibles al ciudadano',
            'correos.enviar' => 'Enviar correos desde el sistema (SMTP)',
            'correos.ver' => 'Ver bandejas de correo (enviados/recibidos)',
            'reportes.exportar' => 'Exportar reportes/estadisticas a Excel',
            'usuarios.gestionar' => 'Crear/editar usuarios, roles y permisos',
            'configuracion.gestionar' => 'Editar configuracion general, plantillas y feriados',
            'enlace.ver_asignadas' => 'Ver (solo lectura) las solicitudes asignadas a la propia dependencia, dejar observaciones y adjuntar documentos',
        ];

        foreach ($permisos as $clave => $nombre) {
            Permission::updateOrCreate(['clave' => $clave], ['nombre' => $nombre]);
        }

        $porRol = [
            'Administrador' => array_keys($permisos),
            'Recepción UIP' => ['solicitudes.ver', 'solicitudes.crear', 'solicitudes.validar', 'documentos.subir', 'correos.ver'],
            'Usuario UIP' => [
                'solicitudes.ver', 'solicitudes.crear', 'solicitudes.validar', 'solicitudes.asignar_contrasena',
                'solicitudes.finalizar', 'solicitudes.ajustar_vencimiento', 'actuaciones.prorroga',
                'actuaciones.aclaracion', 'actuaciones.ampliacion', 'actuaciones.recurso',
                'documentos.subir', 'documentos.publicar', 'correos.enviar', 'correos.ver',
            ],
            'Coordinador' => array_keys($permisos),
            // El rol "Enlace" es el contacto de una dependencia (Fase 20):
            // NO recibe 'solicitudes.ver' (eso le mostraría TODOS los
            // expedientes del sistema, de cualquier dependencia) — en vez
            // de eso 'enlace.ver_asignadas' lo lleva a un panel propio
            // (EnlaceController) que solo lista lo asignado a su propia
            // dependencia. 'documentos.subir' sí se mantiene para que
            // pueda adjuntar la respuesta desde ese mismo panel.
            'Enlace' => ['enlace.ver_asignadas', 'documentos.subir', 'correos.ver'],
            'Consulta' => ['solicitudes.ver', 'correos.ver', 'reportes.exportar'],
        ];

        foreach ($porRol as $rolNombre => $claves) {
            $rol = Rol::where('nombre', $rolNombre)->first();
            if (! $rol) {
                continue;
            }
            $ids = Permission::whereIn('clave', $claves)->pluck('id');
            $rol->permissions()->sync($ids);
        }
    }
}

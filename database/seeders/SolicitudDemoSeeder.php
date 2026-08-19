<?php

namespace Database\Seeders;

use App\Models\Aclaracion;
use App\Models\Ampliacion;
use App\Models\Asignacion;
use App\Models\Dependencia;
use App\Models\Documento;
use App\Models\Enlace;
use App\Models\Prorroga;
use App\Models\RecursoRevision;
use App\Models\Solicitante;
use App\Models\Solicitud;
use App\Models\SolicitudEstado;
use App\Models\SolicitudHistorial;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

// Muestra representativa (6 de los 10 expedientes de demo que se usaron para
// validar el prototipo HTML): cubre cada estado/actuación principal
// (pendiente de validación, en seguimiento con prórroga, recurso de revisión,
// aclaración pendiente, y dos finalizadas -una con la regla real de
// ampliación no procedente-). Sirve como dataset de prueba para Fase 3+.
class SolicitudDemoSeeder extends Seeder
{
    private array $estados = [];

    private array $dependencias = [];

    private ?User $admin = null;

    public function run(): void
    {
        $this->estados = SolicitudEstado::pluck('id', 'clave')->toArray();
        $this->dependencias = Dependencia::pluck('id', 'codigo')->toArray();
        $this->admin = User::where('email', 'jersonmelendez123@gmail.com')->first();

        $this->crearJuanPerez();
        $this->crearMariaGonzalez();
        $this->crearCarlosRamirez();
        $this->crearAnaLopez();
        $this->crearLuisMendoza();
        $this->crearRosaHernandez();
    }

    private function solicitante(array $datos): Solicitante
    {
        return Solicitante::updateOrCreate(['correo' => $datos['correo']], $datos);
    }

    private function historial(Solicitud $s, string $texto, string $actor, ?string $fecha = null): void
    {
        SolicitudHistorial::create([
            'solicitud_id' => $s->id,
            'user_id' => $actor === 'sistema' ? null : $this->admin?->id,
            'tipo_actor' => $actor,
            'descripcion' => $texto,
            'created_at' => $fecha ?? now(),
        ]);
    }

    private function crearJuanPerez(): void
    {
        $solicitante = $this->solicitante([
            'nombre' => 'Juan Pérez López', 'correo' => 'juan.perez@email.com', 'telefono' => '5555-1234',
            'genero' => 'Masculino', 'rango_edad' => '36-50', 'pais' => 'Guatemala', 'departamento' => 'Guatemala',
        ]);

        $enlace = Enlace::where('dependencia_id', $this->dependencias['PLANIF'])->first();

        $s = Solicitud::updateOrCreate(['codigo_ns' => 'NS_01-2026'], [
            'contrasena' => '01-2026',
            'codigo_acceso' => 'A8K4-XP29',
            'solicitante_id' => $solicitante->id,
            'asunto' => 'Solicita información sobre ejecución presupuestaria del año 2026, incluyendo montos asignados y ejecutados por programa.',
            'medio_recepcion' => 'electronica',
            'es_informacion_publica' => 'si',
            'es_competencia' => 'si',
            'requiere_aclaracion' => false,
            'observaciones' => 'Se revisó y se remitió a Dirección de Planificación. Respuesta entregada dentro de plazo.',
            'estado_id' => $this->estados['finalizada'],
            'dependencia_id' => $this->dependencias['PLANIF'],
            'enlace_id' => $enlace?->id,
            'fecha_ingreso' => '2026-08-14',
            'fecha_vencimiento' => '2026-08-28',
            'fecha_respuesta' => '2026-08-28',
            'fecha_finalizacion' => '2026-08-28',
            'creado_por_user_id' => null,
        ]);

        Asignacion::create([
            'solicitud_id' => $s->id, 'dependencia_id' => $this->dependencias['PLANIF'], 'enlace_id' => $enlace?->id,
            'user_id' => $this->admin->id, 'fecha_asignacion' => '2026-08-14',
            'notas' => 'Asignación inicial tras validar la solicitud.',
        ]);

        Aclaracion::create([
            'solicitud_id' => $s->id, 'user_id' => $this->admin->id,
            'fecha_solicitud' => '2026-08-18', 'plazo_dias_habiles' => 2,
            'fecha_limite_respuesta' => '2026-08-20', 'fecha_respuesta' => '2026-08-19',
            'respuesta' => 'El interesado precisó que requiere información del ejercicio fiscal 2026, programas de infraestructura vial.',
            'estado' => 'respondida',
        ]);

        Documento::insert([
            $this->doc($s->id, 'solicitud_2026-00089.pdf', 'pdf', true, '2026-08-14'),
            $this->doc($s->id, 'oficio_aclaracion.pdf', 'pdf', true, '2026-08-18'),
            $this->doc($s->id, 'aclaracion_interesado.pdf', 'pdf', true, '2026-08-19'),
            $this->doc($s->id, 'respuesta_informacion.pdf', 'pdf', true, '2026-08-28'),
            $this->doc($s->id, 'nota_interna_planificacion.pdf', 'pdf', false, '2026-08-20'),
        ]);

        $this->historial($s, 'Solicitud recibida y código NS_01-2026 generado.', 'sistema', '2026-08-14 09:15:00');
        $this->historial($s, 'Validada como información pública y de competencia de la institución.', 'administrador', '2026-08-14 09:20:00');
        $this->historial($s, 'Contraseña asignada: No. 01-2026.', 'administrador', '2026-08-14 09:30:00');
        $this->historial($s, 'Asignada a Dirección de Planificación, enlace Lic. Marta Solís.', 'administrador', '2026-08-14 09:40:00');
        $this->historial($s, 'Se solicitó aclaración al interesado sobre el alcance de la solicitud.', 'administrador', '2026-08-18 16:45:00');
        $this->historial($s, 'Respuesta a la aclaración recibida por correo.', 'ciudadano', '2026-08-19 09:15:00');
        $this->historial($s, 'Solicitud finalizada. Información entregada y notificación enviada.', 'administrador', '2026-08-28 16:30:00');
    }

    private function crearMariaGonzalez(): void
    {
        $solicitante = $this->solicitante([
            'nombre' => 'María González', 'correo' => 'maria.gonzalez@email.com', 'telefono' => '5555-2211',
            'genero' => 'Femenino', 'rango_edad' => '26-35', 'pais' => 'Guatemala', 'departamento' => 'Escuintla',
        ]);

        $s = Solicitud::updateOrCreate(['codigo_ns' => 'NS_02-2026'], [
            'contrasena' => null,
            'codigo_acceso' => 'K3M8-QW71',
            'solicitante_id' => $solicitante->id,
            'asunto' => 'Información sobre proyectos de infraestructura ejecutados en 2025 en el departamento de Escuintla.',
            'medio_recepcion' => 'correo',
            'es_informacion_publica' => 'pendiente',
            'es_competencia' => 'pendiente',
            'estado_id' => $this->estados['pendiente_validacion'],
            'fecha_ingreso' => '2026-08-14',
        ]);

        Documento::insert([$this->doc($s->id, 'solicitud_2026-00090.pdf', 'pdf', true, '2026-08-14')]);

        $this->historial($s, 'Solicitud recibida y código NS_02-2026 generado.', 'sistema', '2026-08-14 08:40:00');
        $this->historial($s, 'Correo automático de recepción enviado al interesado.', 'sistema', '2026-08-14 08:41:00');
    }

    private function crearCarlosRamirez(): void
    {
        $solicitante = $this->solicitante([
            'nombre' => 'Carlos Ramírez', 'correo' => 'carlos.ramirez@email.com', 'telefono' => '5555-3390',
            'genero' => 'Masculino', 'rango_edad' => '26-35', 'pais' => 'Guatemala', 'departamento' => 'Guatemala',
        ]);

        $enlace = Enlace::where('dependencia_id', $this->dependencias['FIN'])->first();

        $s = Solicitud::updateOrCreate(['codigo_ns' => 'NS_03-2026'], [
            'contrasena' => '02-2026',
            'codigo_acceso' => 'P9L2-VR44',
            'solicitante_id' => $solicitante->id,
            'asunto' => 'Copia del contrato de servicios de mantenimiento vehicular vigente para la flotilla institucional.',
            'medio_recepcion' => 'electronica',
            'es_informacion_publica' => 'si',
            'es_competencia' => 'si',
            'observaciones' => 'Dirección de Finanzas solicitó más tiempo para recopilar los documentos contractuales completos.',
            'estado_id' => $this->estados['prorroga'],
            'dependencia_id' => $this->dependencias['FIN'],
            'enlace_id' => $enlace?->id,
            'fecha_ingreso' => '2026-08-13',
            'fecha_vencimiento' => '2026-08-27',
        ]);

        Documento::insert([
            $this->doc($s->id, 'solicitud_2026-00091.pdf', 'pdf', true, '2026-08-13'),
            $this->doc($s->id, 'oficio_prorroga_2026-000091.pdf', 'pdf', true, '2026-08-18'),
        ]);

        Prorroga::create([
            'solicitud_id' => $s->id, 'user_id' => $this->admin->id,
            'fecha_anterior' => '2026-08-20', 'fecha_nueva' => '2026-08-27',
            'solicitada_en' => '2026-08-18',
            'motivo' => 'Se revisó y se requiere más tiempo para recopilar la información contractual completa.',
        ]);

        $this->historial($s, 'Solicitud recibida y código NS_03-2026 generado.', 'sistema', '2026-08-13 11:20:00');
        $this->historial($s, 'Contraseña asignada: No. 02-2026.', 'administrador', '2026-08-13 11:50:00');
        $this->historial($s, 'Asignada a Dirección de Finanzas, enlace Ing. Pedro Ramírez.', 'administrador', '2026-08-14 09:00:00');
        $this->historial($s, 'Se revisó y se requiere más tiempo para recopilar la información. Se registra prórroga (dentro del 8vo día hábil).', 'administrador', '2026-08-18 13:00:00');
    }

    private function crearAnaLopez(): void
    {
        $solicitante = $this->solicitante([
            'nombre' => 'Ana López García', 'correo' => 'ana.lopez@email.com', 'telefono' => '5555-4477',
            'genero' => 'Femenino', 'rango_edad' => '36-50', 'pais' => 'Guatemala', 'departamento' => 'Quetzaltenango',
        ]);

        $enlace = Enlace::where('dependencia_id', $this->dependencias['PLANIF'])->first();

        $s = Solicitud::updateOrCreate(['codigo_ns' => 'NS_04-2026'], [
            'contrasena' => '03-2026',
            'codigo_acceso' => 'H4T7-BN20',
            'solicitante_id' => $solicitante->id,
            'asunto' => 'Información estadística sobre solicitudes de información pública recibidas durante 2025.',
            'medio_recepcion' => 'fisica',
            'es_informacion_publica' => 'si',
            'es_competencia' => 'si',
            'observaciones' => 'El interesado presentó recurso de revisión por respuesta parcial.',
            'estado_id' => $this->estados['recurso_revision'],
            'dependencia_id' => $this->dependencias['PLANIF'],
            'enlace_id' => $enlace?->id,
            'fecha_ingreso' => '2026-08-12',
            'fecha_vencimiento' => '2026-08-25',
        ]);

        Documento::insert([
            $this->doc($s->id, 'solicitud_2026-00092.pdf', 'pdf', true, '2026-08-12'),
            $this->doc($s->id, 'recurso_revision_2026-000092.pdf', 'pdf', true, '2026-08-17'),
        ]);

        // Correlativo del recurso: independiente de la contraseña de la solicitud.
        RecursoRevision::create([
            'solicitud_id' => $s->id,
            'correlativo' => '30-2026',
            'fecha_presentacion' => '2026-08-17',
            'fecha_vencimiento' => '2026-08-25',
            'motivo' => 'El interesado considera que la información entregada fue incompleta respecto al período solicitado.',
            'estado' => 'en_tramite',
        ]);

        $this->historial($s, 'Solicitud recibida y código NS_04-2026 generado.', 'sistema', '2026-08-12 16:45:00');
        $this->historial($s, 'Aceptada como información pública. Contraseña asignada: No. 03-2026.', 'administrador', '2026-08-13 08:10:00');
        $this->historial($s, 'Presenta Recurso de Revisión No. 30-2026 por respuesta considerada incompleta.', 'ciudadano', '2026-08-17 09:30:00');
        $this->historial($s, 'Se registra actuación del recurso y se notifica su recepción.', 'administrador', '2026-08-18 11:45:00');
    }

    private function crearLuisMendoza(): void
    {
        $solicitante = $this->solicitante([
            'nombre' => 'Luis Mendoza', 'correo' => 'luis.mendoza@email.com', 'telefono' => '5555-5588',
            'genero' => 'Masculino', 'rango_edad' => '18-25', 'pais' => 'Guatemala', 'departamento' => 'Guatemala',
        ]);

        $enlace = Enlace::where('dependencia_id', $this->dependencias['JURID'])->first();

        $s = Solicitud::updateOrCreate(['codigo_ns' => 'NS_05-2026'], [
            'contrasena' => '04-2026',
            'codigo_acceso' => 'D6F1-ZX88',
            'solicitante_id' => $solicitante->id,
            'asunto' => 'Aclaración sobre información previamente solicitada respecto a convenios interinstitucionales.',
            'medio_recepcion' => 'electronica',
            'es_informacion_publica' => 'si',
            'es_competencia' => 'si',
            'requiere_aclaracion' => true,
            'observaciones' => 'Se requiere que el interesado precise el alcance temporal de su solicitud.',
            'estado_id' => $this->estados['aclaracion_solicitada'],
            'dependencia_id' => $this->dependencias['JURID'],
            'enlace_id' => $enlace?->id,
            'fecha_ingreso' => '2026-08-11',
            'fecha_vencimiento' => '2026-08-26',
        ]);

        Documento::insert([
            $this->doc($s->id, 'solicitud_2026-00093.pdf', 'pdf', true, '2026-08-11'),
            $this->doc($s->id, 'oficio_aclaracion_2026-000093.pdf', 'pdf', true, '2026-08-17'),
        ]);

        // Aun sin responder: demuestra el plazo real de 2 dias habiles corriendo.
        Aclaracion::create([
            'solicitud_id' => $s->id, 'user_id' => $this->admin->id,
            'fecha_solicitud' => '2026-08-17', 'plazo_dias_habiles' => 2,
            'fecha_limite_respuesta' => '2026-08-19',
            'estado' => 'pendiente',
        ]);

        $this->historial($s, 'Solicitud recibida y código NS_05-2026 generado.', 'sistema', '2026-08-11 10:10:00');
        $this->historial($s, 'Aceptada como información pública. Contraseña asignada: No. 04-2026.', 'administrador', '2026-08-12 09:00:00');
        $this->historial($s, 'Se solicita aclaración al interesado sobre el alcance temporal (plazo: 2 días hábiles).', 'administrador', '2026-08-17 10:30:00');
    }

    private function crearRosaHernandez(): void
    {
        $solicitante = $this->solicitante([
            'nombre' => 'Rosa Hernández', 'correo' => 'rosa.hernandez@email.com', 'telefono' => '5555-6690',
            'genero' => 'Femenino', 'rango_edad' => '51+', 'pais' => 'Guatemala', 'departamento' => 'Guatemala',
        ]);

        $enlace = Enlace::where('dependencia_id', $this->dependencias['RRHH'])->first();

        $s = Solicitud::updateOrCreate(['codigo_ns' => 'NS_06-2026'], [
            'contrasena' => '05-2026',
            'codigo_acceso' => 'M2C9-YT53',
            'solicitante_id' => $solicitante->id,
            'asunto' => 'Solicita información sobre plazas vacantes en la institución durante 2026.',
            'medio_recepcion' => 'correo',
            'es_informacion_publica' => 'si',
            'es_competencia' => 'si',
            'observaciones' => 'Resolución entregada dentro de plazo. El interesado solicitó posteriormente una ampliación, que no procede como actuación de este expediente (la Ley de Acceso a la Información Pública no regula ampliaciones post-resolución).',
            'estado_id' => $this->estados['finalizada'],
            'dependencia_id' => $this->dependencias['RRHH'],
            'enlace_id' => $enlace?->id,
            'fecha_ingreso' => '2026-08-10',
            'fecha_vencimiento' => '2026-08-20',
            'fecha_respuesta' => '2026-08-20',
            'fecha_finalizacion' => '2026-08-20',
        ]);

        Documento::insert([
            $this->doc($s->id, 'solicitud_2026-00094.pdf', 'pdf', true, '2026-08-10'),
            $this->doc($s->id, 'respuesta_informacion_2026-000094.pdf', 'pdf', true, '2026-08-20'),
        ]);

        // Regla real confirmada por el usuario: se registra la ampliación
        // (para auditoria/trazabilidad) pero queda marcada como no procedente.
        Ampliacion::create([
            'solicitud_id' => $s->id,
            'fecha_solicitud' => '2026-08-19',
            'descripcion' => 'El interesado solicita el detalle de plazas vacantes por dirección, no solo el total general.',
            'estado' => 'rechazada_no_regulada',
            'respuesta_enviada' => true,
            'fecha_respuesta' => '2026-08-19',
        ]);

        $this->historial($s, 'Solicitud recibida y código NS_06-2026 generado.', 'sistema', '2026-08-10 09:00:00');
        $this->historial($s, 'Solicitud finalizada. Información entregada y notificación enviada.', 'administrador', '2026-08-20 16:10:00');
        $this->historial($s, 'El interesado solicita ampliación sobre la información ya entregada.', 'ciudadano', '2026-08-19 09:20:00');
        $this->historial($s, 'Se responde que la ampliación post-resolución no está regulada por la ley; se indica presentar una solicitud nueva.', 'administrador', '2026-08-19 10:00:00');
    }

    private function doc(int $solicitudId, string $nombre, string $tipo, bool $visible, string $fecha): array
    {
        return [
            'solicitud_id' => $solicitudId,
            'actuacion_id' => null,
            'plantilla_id' => null,
            'nombre' => $nombre,
            'ruta_archivo' => 'expedientes/' . $solicitudId . '/' . Str::random(8) . '_' . $nombre,
            'tipo' => $tipo,
            'visible_ciudadano' => $visible,
            'subido_por_user_id' => $this->admin?->id,
            'subido_por_ciudadano' => false,
            'created_at' => $fecha,
            'updated_at' => $fecha,
        ];
    }
}

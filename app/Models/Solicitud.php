<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// Expedientes / solicitudes de informacion publica
class Solicitud extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'solicitudes';

    protected $fillable = [
        'codigo_ns',
        'contrasena',
        'rc',
        'folio',
        'codigo_acceso',
        'solicitante_id',
        'asunto',
        'medio_recepcion',
        'es_informacion_publica',
        'es_competencia',
        'requiere_aclaracion',
        'observaciones',
        'estado_id',
        'dependencia_id',
        'enlace_id',
        'fecha_ingreso',
        'fecha_vencimiento',
        'fecha_respuesta',
        'fecha_finalizacion',
        'creado_por_user_id',
    ];

    protected $casts = [
        'requiere_aclaracion' => 'boolean',
        'fecha_ingreso' => 'date',
        'fecha_vencimiento' => 'date',
        'fecha_respuesta' => 'date',
        'fecha_finalizacion' => 'date',
    ];

    public function solicitante()
    {
        return $this->belongsTo(Solicitante::class, 'solicitante_id');
    }

    public function estado()
    {
        return $this->belongsTo(SolicitudEstado::class, 'estado_id');
    }

    public function dependencia()
    {
        return $this->belongsTo(Dependencia::class, 'dependencia_id');
    }

    public function enlace()
    {
        return $this->belongsTo(Enlace::class, 'enlace_id');
    }

    public function creado_por_user()
    {
        return $this->belongsTo(User::class, 'creado_por_user_id');
    }

    public function solicitud_historial()
    {
        return $this->hasMany(SolicitudHistorial::class, 'solicitud_id');
    }

    public function actuaciones()
    {
        return $this->hasMany(Actuacion::class, 'solicitud_id');
    }

    public function documentos()
    {
        return $this->hasMany(Documento::class, 'solicitud_id');
    }

    public function prorrogas()
    {
        return $this->hasMany(Prorroga::class, 'solicitud_id');
    }

    public function aclaraciones()
    {
        return $this->hasMany(Aclaracion::class, 'solicitud_id');
    }

    public function ampliaciones()
    {
        return $this->hasMany(Ampliacion::class, 'solicitud_id');
    }

    public function recursos_revision()
    {
        return $this->hasMany(RecursoRevision::class, 'solicitud_id');
    }

    public function asignaciones()
    {
        return $this->hasMany(Asignacion::class, 'solicitud_id');
    }

    public function correos_enviados()
    {
        return $this->hasMany(CorreoEnviado::class, 'solicitud_id');
    }

    public function correos_recibidos()
    {
        return $this->hasMany(CorreoRecibido::class, 'solicitud_id');
    }

    public function notificaciones()
    {
        return $this->hasMany(Notificacion::class, 'solicitud_id');
    }

    // --- Helpers de negocio (Fase 6) ---

    /**
     * Días hábiles restantes hasta fecha_vencimiento (negativo = vencida),
     * descontando sábados/domingos y feriados registrados. Null si aún no
     * hay fecha de vencimiento (spec tabla 6: "Días restantes y alertas").
     */
    public function diasHabilesRestantes(): ?int
    {
        if (! $this->fecha_vencimiento) {
            return null;
        }

        $hoy = Carbon::today();
        $venc = Carbon::parse($this->fecha_vencimiento)->startOfDay();

        if ($hoy->isSameDay($venc)) {
            return 0;
        }

        $feriados = Feriado::pluck('fecha')
            ->map(fn ($f) => Carbon::parse($f)->toDateString())
            ->all();

        $dir = $venc->gt($hoy) ? 1 : -1;
        $cursor = $hoy->copy();
        $count = 0;

        while (! $cursor->isSameDay($venc)) {
            $cursor->addDays($dir);
            if ($cursor->isWeekend() || in_array($cursor->toDateString(), $feriados, true)) {
                continue;
            }
            $count += $dir;
        }

        return $count;
    }

    /**
     * Fecha límite hasta la cual el interesado puede seguir consultando y
     * descargando documentos en el portal público (Fase 12) DESPUÉS de que
     * el expediente fue finalizado: 10 días hábiles después de
     * fecha_finalizacion. Esto es un plazo distinto e independiente de
     * fecha_vencimiento (que es el plazo del ADMINISTRADOR para resolver,
     * ajustable manualmente por prórroga o recurso de revisión) — mientras
     * el expediente no esté finalizado no hay límite, el ciudadano puede
     * consultar en cualquier momento.
     */
    public function fechaLimiteAccesoPortal(): ?Carbon
    {
        if (! $this->fecha_finalizacion) {
            return null;
        }

        return Feriado::sumarDiasHabiles(Carbon::parse($this->fecha_finalizacion)->startOfDay(), 10);
    }

    /**
     * ¿Ya venció la ventana de 10 días hábiles para consultar el portal
     * después de finalizado el expediente? Siempre false mientras el
     * expediente siga activo (fecha_finalizacion null).
     */
    public function accesoPortalVencido(): bool
    {
        $limite = $this->fechaLimiteAccesoPortal();

        return $limite !== null && Carbon::today()->gt($limite);
    }

    /**
     * ¿Ya se puede asignar contraseña? Solo después de que la solicitud
     * fue aceptada (validada) — nunca en pendiente_validacion.
     */
    public function puedeAsignarContrasena(): bool
    {
        return $this->estado?->clave !== 'pendiente_validacion';
    }

    /**
     * Búsqueda case-insensitive portable entre motores (Postgres en
     * producción, SQLite en los tests) usando LOWER()+LIKE en vez de ILIKE
     * (propio de Postgres) para no atarnos a un solo driver.
     */
    public function scopeBuscar($query, ?string $texto)
    {
        if (! $texto) {
            return $query;
        }

        $like = '%'.mb_strtolower($texto).'%';

        return $query->where(function ($q) use ($like) {
            $q->whereRaw('LOWER(codigo_ns) LIKE ?', [$like])
                ->orWhereRaw('LOWER(contrasena) LIKE ?', [$like])
                ->orWhereHas('solicitante', function ($sq) use ($like) {
                    $sq->whereRaw('LOWER(nombre) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(correo) LIKE ?', [$like]);
                });
        });
    }
}

<?php

namespace App\Models;

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
}

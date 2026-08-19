<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Catalogo configurable de estados (spec seccion 25)
class SolicitudEstado extends Model
{
    use HasFactory;

    protected $table = 'solicitud_estados';

    public $timestamps = false;

    protected $fillable = [
        'clave',
        'etiqueta',
        'color',
        'orden',
        'es_final',
    ];

    protected $casts = [
        'es_final' => 'boolean',
    ];

    public function solicitudes()
    {
        return $this->hasMany(Solicitud::class, 'estado_id');
    }

    public function estado_anterior_solicitud_historial()
    {
        return $this->hasMany(SolicitudHistorial::class, 'estado_anterior_id');
    }

    public function estado_nuevo_solicitud_historial()
    {
        return $this->hasMany(SolicitudHistorial::class, 'estado_nuevo_id');
    }
}

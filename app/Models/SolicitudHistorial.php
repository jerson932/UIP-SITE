<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Linea de tiempo / bitacora de cada expediente (solo lectura)
class SolicitudHistorial extends Model
{
    use HasFactory;

    protected $table = 'solicitud_historial';

    const UPDATED_AT = null;

    protected $fillable = [
        'solicitud_id',
        'user_id',
        'tipo_actor',
        'descripcion',
        'estado_anterior_id',
        'estado_nuevo_id',
        'metadata',
        'visible_ciudadano',
    ];

    protected $casts = [
        'metadata' => 'array',
        'visible_ciudadano' => 'boolean',
    ];

    public function solicitud()
    {
        return $this->belongsTo(Solicitud::class, 'solicitud_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function estado_anterior()
    {
        return $this->belongsTo(SolicitudEstado::class, 'estado_anterior_id');
    }

    public function estado_nuevo()
    {
        return $this->belongsTo(SolicitudEstado::class, 'estado_nuevo_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Solicitudes de ampliacion (se registran aunque no sean procedentes, para auditoria)
class Ampliacion extends Model
{
    use HasFactory;

    protected $table = 'ampliaciones';

    protected $fillable = [
        'solicitud_id',
        'fecha_solicitud',
        'descripcion',
        'estado',
        'respuesta_enviada',
        'fecha_respuesta',
    ];

    protected $casts = [
        'fecha_solicitud' => 'date',
        'respuesta_enviada' => 'boolean',
        'fecha_respuesta' => 'date',
    ];

    public function solicitud()
    {
        return $this->belongsTo(Solicitud::class, 'solicitud_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Aclaraciones solicitadas al ciudadano (plazo real: 2 dias habiles)
class Aclaracion extends Model
{
    use HasFactory;

    protected $table = 'aclaraciones';

    protected $fillable = [
        'solicitud_id',
        'actuacion_id',
        'documento_id',
        'user_id',
        'fecha_solicitud',
        'plazo_dias_habiles',
        'fecha_limite_respuesta',
        'fecha_respuesta',
        'respuesta',
        'estado',
    ];

    protected $casts = [
        'fecha_solicitud' => 'date',
        'fecha_limite_respuesta' => 'date',
        'fecha_respuesta' => 'date',
    ];

    public function solicitud()
    {
        return $this->belongsTo(Solicitud::class, 'solicitud_id');
    }

    public function actuacion()
    {
        return $this->belongsTo(Actuacion::class, 'actuacion_id');
    }

    public function documento()
    {
        return $this->belongsTo(Documento::class, 'documento_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

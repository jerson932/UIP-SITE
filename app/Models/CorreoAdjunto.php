<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Adjuntos de correos enviados/recibidos
class CorreoAdjunto extends Model
{
    use HasFactory;

    protected $table = 'correo_adjuntos';

    const UPDATED_AT = null;

    protected $fillable = [
        'correo_enviado_id',
        'correo_recibido_id',
        'nombre_archivo',
        'ruta_archivo',
        'tamano_bytes',
    ];

    public function correo_enviado()
    {
        return $this->belongsTo(CorreoEnviado::class, 'correo_enviado_id');
    }

    public function correo_recibido()
    {
        return $this->belongsTo(CorreoRecibido::class, 'correo_recibido_id');
    }
}

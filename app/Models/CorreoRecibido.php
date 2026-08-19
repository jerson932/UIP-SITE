<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Bandeja de recibidos (IMAP)
class CorreoRecibido extends Model
{
    use HasFactory;

    protected $table = 'correos_recibidos';

    protected $fillable = [
        'solicitud_id',
        'remitente',
        'asunto',
        'cuerpo',
        'recibido_en',
        'estado',
    ];

    protected $casts = [
        'recibido_en' => 'datetime',
    ];

    public function solicitud()
    {
        return $this->belongsTo(Solicitud::class, 'solicitud_id');
    }

    public function correo_adjuntos()
    {
        return $this->hasMany(CorreoAdjunto::class, 'correo_recibido_id');
    }
}

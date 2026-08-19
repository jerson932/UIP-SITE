<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Bandeja de enviados (SMTP)
class CorreoEnviado extends Model
{
    use HasFactory;

    protected $table = 'correos_enviados';

    protected $fillable = [
        'solicitud_id',
        'plantilla_id',
        'enviado_por_user_id',
        'destinatario',
        'asunto',
        'cuerpo',
        'estado_entrega',
        'enviado_en',
    ];

    protected $casts = [
        'enviado_en' => 'datetime',
    ];

    public function solicitud()
    {
        return $this->belongsTo(Solicitud::class, 'solicitud_id');
    }

    public function plantilla()
    {
        return $this->belongsTo(PlantillaCorreo::class, 'plantilla_id');
    }

    public function enviado_por_user()
    {
        return $this->belongsTo(User::class, 'enviado_por_user_id');
    }

    public function correo_adjuntos()
    {
        return $this->hasMany(CorreoAdjunto::class, 'correo_enviado_id');
    }
}

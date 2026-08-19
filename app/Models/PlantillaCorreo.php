<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Plantillas de correo con el tono/formato real de la UIP
class PlantillaCorreo extends Model
{
    use HasFactory;

    protected $table = 'plantillas_correo';

    protected $fillable = [
        'clave',
        'evento',
        'asunto_template',
        'cuerpo_template',
        'activa',
    ];

    protected $casts = [
        'activa' => 'boolean',
    ];

    public function correos_enviados()
    {
        return $this->hasMany(CorreoEnviado::class, 'plantilla_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Ciudadanos/solicitantes (datos demograficos del formulario real)
class Solicitante extends Model
{
    use HasFactory;

    protected $table = 'solicitantes';

    protected $fillable = [
        'nombre',
        'correo',
        'telefono',
        'genero',
        'rango_edad',
        'pais',
        'departamento',
    ];

    public function solicitudes()
    {
        return $this->hasMany(Solicitud::class, 'solicitante_id');
    }
}

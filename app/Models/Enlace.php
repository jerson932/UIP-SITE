<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Persona de contacto/enlace de una dependencia
class Enlace extends Model
{
    use HasFactory;

    protected $table = 'enlaces';

    protected $fillable = [
        'dependencia_id',
        'user_id',
        'nombre',
        'correo',
        'telefono',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function dependencia()
    {
        return $this->belongsTo(Dependencia::class, 'dependencia_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function solicitudes()
    {
        return $this->hasMany(Solicitud::class, 'enlace_id');
    }

    public function asignaciones()
    {
        return $this->hasMany(Asignacion::class, 'enlace_id');
    }
}

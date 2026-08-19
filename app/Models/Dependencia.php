<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Dependencias/unidades a las que se asignan solicitudes (spec tabla 10)
class Dependencia extends Model
{
    use HasFactory;

    protected $table = 'dependencias';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'activa',
    ];

    protected $casts = [
        'activa' => 'boolean',
    ];

    public function enlaces()
    {
        return $this->hasMany(Enlace::class, 'dependencia_id');
    }

    public function solicitudes()
    {
        return $this->hasMany(Solicitud::class, 'dependencia_id');
    }

    public function asignaciones()
    {
        return $this->hasMany(Asignacion::class, 'dependencia_id');
    }
}

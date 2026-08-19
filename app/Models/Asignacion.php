<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Historial de asignaciones a dependencias/enlaces
class Asignacion extends Model
{
    use HasFactory;

    protected $table = 'asignaciones';

    protected $fillable = [
        'solicitud_id',
        'dependencia_id',
        'enlace_id',
        'user_id',
        'fecha_asignacion',
        'notas',
    ];

    protected $casts = [
        'fecha_asignacion' => 'date',
    ];

    public function solicitud()
    {
        return $this->belongsTo(Solicitud::class, 'solicitud_id');
    }

    public function dependencia()
    {
        return $this->belongsTo(Dependencia::class, 'dependencia_id');
    }

    public function enlace()
    {
        return $this->belongsTo(Enlace::class, 'enlace_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

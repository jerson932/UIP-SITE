<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Prorrogas del plazo de 10 dias habiles
class Prorroga extends Model
{
    use HasFactory;

    protected $table = 'prorrogas';

    protected $fillable = [
        'solicitud_id',
        'actuacion_id',
        'documento_id',
        'user_id',
        'fecha_anterior',
        'fecha_nueva',
        'solicitada_en',
        'motivo',
    ];

    protected $casts = [
        'fecha_anterior' => 'date',
        'fecha_nueva' => 'date',
        'solicitada_en' => 'date',
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Recursos de revision (correlativo independiente de la solicitud)
class RecursoRevision extends Model
{
    use HasFactory;

    protected $table = 'recursos_revision';

    protected $fillable = [
        'solicitud_id',
        'documento_id',
        'correlativo',
        'fecha_presentacion',
        'fecha_vencimiento',
        'motivo',
        'estado',
        'fecha_resolucion',
    ];

    protected $casts = [
        'fecha_presentacion' => 'date',
        'fecha_vencimiento' => 'date',
        'fecha_resolucion' => 'date',
    ];

    public function solicitud()
    {
        return $this->belongsTo(Solicitud::class, 'solicitud_id');
    }

    public function documento()
    {
        return $this->belongsTo(Documento::class, 'documento_id');
    }
}

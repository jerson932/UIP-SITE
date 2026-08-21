<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Documentos internos y publicados al ciudadano
class Documento extends Model
{
    use HasFactory;

    protected $table = 'documentos';

    protected $fillable = [
        'solicitud_id',
        'actuacion_id',
        'plantilla_id',
        'nombre',
        'ruta_archivo',
        'tipo',
        'no_oficio',
        'no_providencia',
        'visible_ciudadano',
        'subido_por_user_id',
        'subido_por_ciudadano',
    ];

    protected $casts = [
        'visible_ciudadano' => 'boolean',
        'subido_por_ciudadano' => 'boolean',
    ];

    public function solicitud()
    {
        return $this->belongsTo(Solicitud::class, 'solicitud_id');
    }

    public function actuacion()
    {
        return $this->belongsTo(Actuacion::class, 'actuacion_id');
    }

    public function plantilla()
    {
        return $this->belongsTo(PlantillaDocumento::class, 'plantilla_id');
    }

    public function subido_por_user()
    {
        return $this->belongsTo(User::class, 'subido_por_user_id');
    }

    public function prorrogas()
    {
        return $this->hasMany(Prorroga::class, 'documento_id');
    }

    public function aclaraciones()
    {
        return $this->hasMany(Aclaracion::class, 'documento_id');
    }

    public function recursos_revision()
    {
        return $this->hasMany(RecursoRevision::class, 'documento_id');
    }
}

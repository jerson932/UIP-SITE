<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Plantillas de documentos generables (spec tabla 3)
class PlantillaDocumento extends Model
{
    use HasFactory;

    protected $table = 'plantillas_documentos';

    protected $fillable = [
        'clave',
        'nombre',
        'tipo',
        'contenido',
        'visible_ciudadano_default',
        'activa',
    ];

    protected $casts = [
        'visible_ciudadano_default' => 'boolean',
        'activa' => 'boolean',
    ];

    public function documentos()
    {
        return $this->hasMany(Documento::class, 'plantilla_id');
    }
}

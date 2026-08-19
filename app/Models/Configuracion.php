<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Parametros generales del sistema
class Configuracion extends Model
{
    use HasFactory;

    protected $table = 'configuracion';

    const CREATED_AT = null;

    protected $fillable = [
        'clave',
        'valor',
        'descripcion',
    ];
}

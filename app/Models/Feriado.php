<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Dias no habiles, para el calculo de plazos (10 y 2 dias habiles)
class Feriado extends Model
{
    use HasFactory;

    protected $table = 'feriados';

    protected $fillable = [
        'fecha',
        'descripcion',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];
}

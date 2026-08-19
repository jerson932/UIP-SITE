<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Registro formal de cada actuacion (spec tabla 7)
class Actuacion extends Model
{
    use HasFactory;

    protected $table = 'actuaciones';

    protected $fillable = [
        'solicitud_id',
        'tipo',
        'iniciado_por',
        'user_id',
        'fecha',
        'descripcion',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function solicitud()
    {
        return $this->belongsTo(Solicitud::class, 'solicitud_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function documentos()
    {
        return $this->hasMany(Documento::class, 'actuacion_id');
    }

    public function prorrogas()
    {
        return $this->hasMany(Prorroga::class, 'actuacion_id');
    }

    public function aclaraciones()
    {
        return $this->hasMany(Aclaracion::class, 'actuacion_id');
    }
}

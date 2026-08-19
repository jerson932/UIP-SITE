<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Notificaciones internas para el personal UIP
class Notificacion extends Model
{
    use HasFactory;

    protected $table = 'notificaciones';

    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'solicitud_id',
        'tipo',
        'mensaje',
        'leida',
        'leida_en',
    ];

    protected $casts = [
        'leida' => 'boolean',
        'leida_en' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function solicitud()
    {
        return $this->belongsTo(Solicitud::class, 'solicitud_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Auditoria tecnica (quien hizo que, cuando)
class Log extends Model
{
    use HasFactory;

    protected $table = 'logs';

    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'accion',
        'entidad',
        'entidad_id',
        'ip',
        'detalle',
    ];

    protected $casts = [
        'detalle' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

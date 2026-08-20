<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Ciudadanos/solicitantes (datos demograficos del formulario real)
class Solicitante extends Model
{
    use HasFactory;

    protected $table = 'solicitantes';

    protected $fillable = [
        'nombre',
        'correo',
        'telefono',
        'genero',
        'rango_edad',
        'pais',
        'departamento',
    ];

    public function solicitudes()
    {
        return $this->hasMany(Solicitud::class, 'solicitante_id');
    }

    /**
     * Reutiliza el registro existente si ya hay un solicitante con ese
     * correo (para no duplicar la misma persona cada vez que presenta una
     * solicitud nueva), actualizando sus datos demográficos por si
     * cambiaron; si no hay correo o no existe, crea uno nuevo. Usado tanto
     * por el formulario público como por el registro interno de la UIP.
     */
    public static function localizarOCrear(array $datos): self
    {
        if (! empty($datos['correo'])) {
            $existente = static::where('correo', $datos['correo'])->first();
            if ($existente) {
                $existente->update($datos);

                return $existente;
            }
        }

        return static::create($datos);
    }
}

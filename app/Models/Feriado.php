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

    /**
     * Suma $dias dias habiles a partir de $desde (sin contar $desde),
     * saltando fines de semana y los feriados registrados en esta tabla.
     * Usado para calcular plazos legales (aclaraciones, prorrogas, etc.)
     * de forma consistente en todo el sistema (Fase 9).
     */
    public static function sumarDiasHabiles(\Carbon\Carbon $desde, int $dias): \Carbon\Carbon
    {
        $feriados = static::pluck('fecha')
            ->map(fn ($f) => \Carbon\Carbon::parse($f)->toDateString())
            ->all();

        $cursor = $desde->copy();
        $restantes = $dias;

        while ($restantes > 0) {
            $cursor->addDay();
            if ($cursor->isWeekend() || in_array($cursor->toDateString(), $feriados, true)) {
                continue;
            }
            $restantes--;
        }

        return $cursor;
    }
}

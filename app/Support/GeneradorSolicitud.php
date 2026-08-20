<?php

namespace App\Support;

use App\Models\Solicitud;

/**
 * Genera el Código NS (correlativo público del expediente, ej. "NS_07-2026")
 * y el código de acceso (la "contraseña" de 8 caracteres que usa el
 * ciudadano en el portal de seguimiento, ej. "A8K4-XP29") para una
 * solicitud nueva. Se usa tanto desde el formulario público
 * (SolicitudPublicaController) como desde el registro interno de la UIP
 * (Admin\SolicitudController), para que ambos puntos de entrada generen
 * códigos con el mismo formato y sin colisiones.
 */
class GeneradorSolicitud
{
    public static function codigoNs(): string
    {
        $anio = now()->year;

        // count()+1 es solo un punto de partida razonable; el ciclo de
        // abajo es lo que realmente garantiza que no se repita (por
        // ejemplo si algún codigo_ns de este año se hubiera saltado un
        // número, o si dos solicitudes se están creando casi al mismo
        // tiempo).
        $secuencia = Solicitud::withTrashed()
            ->where('codigo_ns', 'like', "NS_%-{$anio}")
            ->count() + 1;

        do {
            $codigo = sprintf('NS_%02d-%d', $secuencia, $anio);
            $existe = Solicitud::withTrashed()->where('codigo_ns', $codigo)->exists();
            $secuencia++;
        } while ($existe);

        return $codigo;
    }

    public static function codigoAcceso(): string
    {
        // Alfabeto sin 0/O ni 1/I, para que no se confundan al leerlo o
        // transcribirlo a mano (el ciudadano lo necesita para el portal).
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $letras = '';
            for ($i = 0; $i < 8; $i++) {
                $letras .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
            }
            $codigo = substr($letras, 0, 4).'-'.substr($letras, 4, 4);
        } while (Solicitud::where('codigo_acceso', $codigo)->exists());

        return $codigo;
    }
}

<?php

namespace App\Services;

use App\Models\Dependencia;
use App\Models\Documento;
use App\Models\PlantillaDocumento;
use App\Models\Solicitud;
use App\Support\FormatoOficial;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;

// Genera el Oficio o la Providencia de traslado a la dependencia asignada,
// a partir de las 10 plantillas .docx reales que la UIP ya usa
// (resources/plantillas_oficiales/*.docx) — mismo enfoque que el sistema de
// referencia que compartió el usuario (PhpOffice\PhpWord\TemplateProcessor
// reemplazando ${marcadores}), adaptado a que la plantilla a usar se lee de
// dependencias.plantilla_clave en vez de un switch con ids fijos, para que
// se pueda ajustar el mapeo sin tocar código.
//
// Mapeo dependencia -> tipo de plantilla (confirmado contra el mapeo por id
// del sistema de referencia del usuario, que coincide exactamente con estos
// nombres):
//   oficio_despacho      = Despacho Superior
//   oficio_primer_vice   = Primer Viceministerio
//   oficio_segundo_vice  = Segundo Viceministerio
//   oficio_tercer_vice   = Tercer Viceministerio de Gobernación
//   oficio_cuarto_vice   = Cuarto Viceministerio
//   oficio_quinto_vice   = Quinto Viceministerio
//   providencia_digessp  = Dirección General de Servicios de Seguridad Privada
//   providencia_pnc      = Dirección General de la Policía Nacional Civil
//   providencia_repeju   = Registro de Personas Jurídicas
//   providencia_generica = cualquier otra dependencia (incluyendo "Otro")
class DocumentoOficialService
{
    private const MESES = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];

    /**
     * "oficio" o "providencia" según la plantilla que le corresponde a la
     * dependencia (por convención de nombre de clave: "oficio_*" / "providencia_*").
     */
    public function tipoParaDependencia(?Dependencia $dependencia): string
    {
        $clave = $this->claveParaDependencia($dependencia);

        return str_starts_with($clave, 'oficio_') ? 'oficio' : 'providencia';
    }

    public function claveParaDependencia(?Dependencia $dependencia): string
    {
        return $dependencia?->plantilla_clave ?: 'providencia_generica';
    }

    /**
     * Un expediente puede generar varios oficios/providencias a lo largo
     * del tiempo, cada uno hacia su propia dependencia — por eso la
     * dependencia se recibe explícita aquí en vez de leerse de
     * $solicitud->dependencia (que es solo la asignación "actual").
     *
     * @param  array{rc?: ?string, folio?: ?string, no_oficio?: ?string, no_providencia?: ?string}  $datos
     */
    public function generar(Solicitud $solicitud, Dependencia $dependencia, array $datos, ?int $userId): Documento
    {
        $clave = $this->claveParaDependencia($dependencia);
        $tipo = $this->tipoParaDependencia($dependencia);

        $plantilla = PlantillaDocumento::where('clave', $clave)->where('activa', true)->first();
        if (! $plantilla) {
            throw new \RuntimeException("No se encontró la plantilla activa \"{$clave}\".");
        }

        $rutaPlantilla = resource_path("plantillas_oficiales/{$clave}.docx");
        if (! file_exists($rutaPlantilla)) {
            throw new \RuntimeException("No se encontró el archivo de plantilla en {$rutaPlantilla}.");
        }

        // RC se reutiliza/actualiza en cada generación (puede variar según
        // a quién se traslada). FOLIO, en cambio, solo se asigna UNA vez —
        // en el primer oficio o primera providencia del expediente — y ya
        // no cambia después aunque el formulario mande otro valor: así lo
        // pidió el usuario ("el folio solo se asigna al primer oficio o
        // primera providencia"). Ver también el input de folio en
        // solicitudes/show.blade.php, que se bloquea en la vista una vez
        // que ya hay uno guardado.
        if (array_key_exists('rc', $datos) && $datos['rc'] !== null && $datos['rc'] !== '') {
            $solicitud->rc = $datos['rc'];
        }
        if (! $solicitud->folio && array_key_exists('folio', $datos) && $datos['folio'] !== null && $datos['folio'] !== '') {
            $solicitud->folio = $datos['folio'];
        }
        if ($solicitud->isDirty(['rc', 'folio'])) {
            $solicitud->save();
        }

        $noOficio = $tipo === 'oficio' ? ($datos['no_oficio'] ?? null) : null;
        $noProvidencia = $tipo === 'providencia' ? ($datos['no_providencia'] ?? null) : null;

        $tp = new TemplateProcessor($rutaPlantilla);
        $tp->setValue('no_solicitud', FormatoOficial::conComas($solicitud->contrasena));
        $tp->setValue('rc', $this->texto($solicitud->rc));
        $tp->setValue('folio', $this->texto($solicitud->folio));
        $tp->setValue('interesado', $this->texto($solicitud->solicitante?->nombre));
        $tp->setValue('descripcion', $this->texto($solicitud->asunto));
        $tp->setValue('dependencia', $this->texto($dependencia->nombre));
        $tp->setValue('no_oficio', FormatoOficial::conComas($noOficio));
        $tp->setValue('no_providencia', FormatoOficial::conComas($noProvidencia));
        $tp->setValue('titulo_numero', $this->tituloNumero($tipo, $noOficio, $noProvidencia));
        $tp->setValue('fecha_genera', $this->fechaGenera($tipo));

        // Sufijo aleatorio además de la fecha/hora: dos generaciones para
        // el mismo expediente en el mismo segundo (p. ej. al probar
        // reasignaciones seguidas) no deben pisarse el archivo.
        $nombreArchivo = strtoupper($tipo).'_'.$solicitud->codigo_ns.'_'.now()->format('YmdHis').'_'.Str::random(6).'.docx';
        $rutaRelativa = 'documentos/solicitud_'.$solicitud->id.'/'.$nombreArchivo;

        Storage::disk('local')->makeDirectory('documentos/solicitud_'.$solicitud->id);
        $tp->saveAs(Storage::disk('local')->path($rutaRelativa));

        return Documento::create([
            'solicitud_id' => $solicitud->id,
            'plantilla_id' => $plantilla->id,
            'dependencia_id' => $dependencia->id,
            'nombre' => ($tipo === 'oficio' ? 'Oficio' : 'Providencia').' — '.$dependencia->nombre,
            'ruta_archivo' => $rutaRelativa,
            'tipo' => 'docx',
            'visible_ciudadano' => $plantilla->visible_ciudadano_default,
            'subido_por_user_id' => $userId,
            'subido_por_ciudadano' => false,
            'no_oficio' => $noOficio,
            'no_providencia' => $noProvidencia,
        ]);
    }

    private function texto(?string $valor): string
    {
        return $valor ?? '';
    }

    private function tituloNumero(string $tipo, ?string $noOficio, ?string $noProvidencia): string
    {
        if ($tipo === 'oficio' && $noOficio) {
            return 'OFICIO No: '.FormatoOficial::conComas($noOficio);
        }

        if ($tipo === 'providencia' && $noProvidencia) {
            return 'PROVIDENCIA '.FormatoOficial::conComas($noProvidencia);
        }

        return '';
    }

    /**
     * Fecha completamente en letras ("veintiuno de agosto de dos mil
     * veintiséis"), como se pidió. Los oficios llevan la línea
     * "${fecha_genera}" sola (necesita el prefijo "Guatemala, "), las
     * providencias ya traen "GUATEMALA, ${fecha_genera}." impreso (no debe
     * repetirse el prefijo). Ambos casos van en mayúsculas.
     */
    private function fechaGenera(string $tipo): string
    {
        $hoy = now();
        $base = $this->numeroEnLetras($hoy->day).' de '.self::MESES[(int) $hoy->format('n')].' de '.$this->numeroEnLetras($hoy->year);

        if ($tipo === 'oficio') {
            return mb_strtoupper('Guatemala, '.$base, 'UTF-8');
        }

        return mb_strtoupper($base, 'UTF-8');
    }

    /**
     * Convierte un entero (0-999,999) a su forma en letras en español,
     * suficiente para el día ("veintiuno") y el año ("dos mil veintiséis")
     * de fecha_genera. No es un conversor de propósito general — cubre
     * exactamente los rangos que puede necesitar una fecha.
     */
    private function numeroEnLetras(int $n): string
    {
        if ($n < 0) {
            return 'menos '.$this->numeroEnLetras(-$n);
        }

        $unidades = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve'];
        $especiales = ['diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve'];
        $veintis = ['veinte', 'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco', 'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve'];
        $decenas = ['', '', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
        $centenas = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];

        if ($n === 0) {
            return 'cero';
        }
        if ($n < 10) {
            return $unidades[$n];
        }
        if ($n < 20) {
            return $especiales[$n - 10];
        }
        if ($n < 30) {
            return $veintis[$n - 20];
        }
        if ($n < 100) {
            $d = intdiv($n, 10);
            $u = $n % 10;

            return $u === 0 ? $decenas[$d] : $decenas[$d].' y '.$unidades[$u];
        }
        if ($n === 100) {
            return 'cien';
        }
        if ($n < 1000) {
            $c = intdiv($n, 100);
            $resto = $n % 100;

            return $resto === 0 ? $centenas[$c] : $centenas[$c].' '.$this->numeroEnLetras($resto);
        }
        if ($n < 1000000) {
            $miles = intdiv($n, 1000);
            $resto = $n % 1000;
            $prefijo = $miles === 1 ? 'mil' : $this->numeroEnLetras($miles).' mil';

            return $resto === 0 ? $prefijo : $prefijo.' '.$this->numeroEnLetras($resto);
        }

        return (string) $n;
    }
}

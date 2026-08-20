<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dependencia;
use App\Models\Log;
use App\Models\Solicitud;
use App\Models\SolicitudEstado;
use App\Support\SimpleXlsxWriter;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

// Reportes y exportación (permiso 'reportes.exportar' — Administrador,
// Coordinador y Consulta, según PermissionSeeder). El archivo .xlsx lo arma
// SimpleXlsxWriter (ver app/Support/SimpleXlsxWriter.php) a mano con
// ext-zip, sin depender de PhpSpreadsheet/Maatwebsite: este entorno de
// desarrollo no tiene acceso a Packagist para instalar esa librería (ni sus
// varias dependencias transitivas). El resultado es un .xlsx real y válido
// — se probó abriéndolo con Excel/openpyxl —, solo que sin las funciones
// avanzadas de una librería completa (múltiples hojas, fórmulas, gráficos).
class ReporteController extends Controller
{
    /**
     * Aplica los filtros comunes (rango de fecha_ingreso, estado,
     * dependencia) tanto al resumen (index) como a la exportación, para que
     * el CSV descargado corresponda exactamente a lo que se está viendo en
     * pantalla.
     */
    private function consultaFiltrada(Request $request)
    {
        return Solicitud::query()
            ->with(['solicitante', 'estado', 'dependencia'])
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha_ingreso', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha_ingreso', '<=', $request->date('hasta')))
            ->when($request->filled('estado'), fn ($q) => $q->whereHas(
                'estado',
                fn ($eq) => $eq->where('clave', $request->string('estado'))
            ))
            ->when($request->filled('dependencia_id'), fn ($q) => $q->where('dependencia_id', $request->integer('dependencia_id')));
    }

    private function etiquetaMedio(string $medio): string
    {
        return match ($medio) {
            'fisica' => 'Física',
            'electronica' => 'Electrónica',
            'correo' => 'Correo',
            default => $medio,
        };
    }

    public function index(Request $request): View
    {
        $filtradas = $this->consultaFiltrada($request)->get();

        $porEstado = SolicitudEstado::orderBy('orden')->get()->map(fn ($estado) => [
            'etiqueta' => $estado->etiqueta,
            'color' => $estado->color,
            'total' => $filtradas->where('estado_id', $estado->id)->count(),
        ])->filter(fn ($fila) => $fila['total'] > 0)->values();

        $porDependencia = $filtradas->groupBy(fn ($s) => $s->dependencia?->nombre ?? 'Sin asignar')
            ->map(fn ($grupo, $nombre) => ['nombre' => $nombre, 'total' => $grupo->count()])
            ->sortByDesc('total')
            ->values();

        $porMedio = $filtradas->groupBy('medio_recepcion')
            ->map(fn ($grupo, $medio) => ['nombre' => $this->etiquetaMedio($medio), 'total' => $grupo->count()])
            ->sortByDesc('total')
            ->values();

        // Solo tiene sentido hablar de "vencidas" / "próximas a vencer" para
        // expedientes que todavía están activos (estado no final) y ya
        // tienen fecha_vencimiento asignada.
        $activas = $filtradas->filter(fn ($s) => ! $s->estado?->es_final && $s->fecha_vencimiento);
        $vencidas = $activas->filter(fn ($s) => $s->diasHabilesRestantes() < 0)->count();
        $proximasAVencer = $activas->filter(fn ($s) => $s->diasHabilesRestantes() >= 0 && $s->diasHabilesRestantes() <= 2)->count();

        return view('admin.reportes.index', [
            'total' => $filtradas->count(),
            'vencidas' => $vencidas,
            'proximasAVencer' => $proximasAVencer,
            'porEstado' => $porEstado,
            'porDependencia' => $porDependencia,
            'porMedio' => $porMedio,
            'estados' => SolicitudEstado::orderBy('orden')->get(),
            'dependencias' => Dependencia::orderBy('nombre')->get(),
            'filtros' => $request->only(['desde', 'hasta', 'estado', 'dependencia_id']),
        ]);
    }

    public function exportar(Request $request): BinaryFileResponse
    {
        $filas = $this->consultaFiltrada($request)->orderByDesc('fecha_ingreso')->get();

        Log::create([
            'user_id' => auth()->id(),
            'accion' => 'reporte.exportado',
            'entidad' => 'solicitudes',
            'entidad_id' => null,
            'ip' => $request->ip(),
            'detalle' => ['filtros' => $request->only(['desde', 'hasta', 'estado', 'dependencia_id']), 'total_filas' => $filas->count()],
        ]);

        $encabezados = [
            'Código NS', 'Contraseña', 'Fecha ingreso', 'Interesado', 'Correo',
            'Medio de recepción', 'Asunto', 'Estado', 'Dependencia',
            'Fecha vencimiento', 'Días restantes', 'Fecha finalización',
        ];

        $filasParaExcel = $filas->map(function (Solicitud $s) {
            $dias = $s->diasHabilesRestantes();

            return [
                $s->codigo_ns,
                $s->contrasena ?? '',
                optional($s->fecha_ingreso)->format('d/m/Y'),
                $s->solicitante?->nombre ?? '',
                $s->solicitante?->correo ?? '',
                $this->etiquetaMedio($s->medio_recepcion),
                $s->asunto,
                $s->estado?->etiqueta ?? '',
                $s->dependencia?->nombre ?? '',
                optional($s->fecha_vencimiento)->format('d/m/Y'),
                $dias, // int|null: SimpleXlsxWriter lo escribe como celda numérica.
                optional($s->fecha_finalizacion)->format('d/m/Y'),
            ];
        });

        $nombreArchivo = 'reporte-solicitudes-'.now()->format('Y-m-d_His').'.xlsx';
        $directorioTmp = storage_path('app/private/tmp');
        if (! is_dir($directorioTmp)) {
            mkdir($directorioTmp, 0755, true);
        }
        $rutaTmp = $directorioTmp.'/'.$nombreArchivo;

        SimpleXlsxWriter::generar($rutaTmp, $encabezados, $filasParaExcel->all());

        return response()->download($rutaTmp, $nombreArchivo, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}

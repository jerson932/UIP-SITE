<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Documentos internos y publicados al ciudadano
class Documento extends Model
{
    use HasFactory;

    protected $table = 'documentos';

    protected $fillable = [
        'solicitud_id',
        'actuacion_id',
        'plantilla_id',
        'dependencia_id',
        'nombre',
        'ruta_archivo',
        'tipo',
        'no_oficio',
        'no_providencia',
        'visible_ciudadano',
        'subido_por_user_id',
        'subido_por_ciudadano',
    ];

    protected $casts = [
        'visible_ciudadano' => 'boolean',
        'subido_por_ciudadano' => 'boolean',
    ];

    public function solicitud()
    {
        return $this->belongsTo(Solicitud::class, 'solicitud_id');
    }

    public function actuacion()
    {
        return $this->belongsTo(Actuacion::class, 'actuacion_id');
    }

    public function plantilla()
    {
        return $this->belongsTo(PlantillaDocumento::class, 'plantilla_id');
    }

    public function dependencia()
    {
        return $this->belongsTo(Dependencia::class, 'dependencia_id');
    }

    public function subido_por_user()
    {
        return $this->belongsTo(User::class, 'subido_por_user_id');
    }

    /**
     * Nombre a usar en el Content-Disposition al descargar. "nombre" es una
     * etiqueta libre (p. ej. "Providencia — Dirección General de X") que en
     * los documentos generados (Oficio/Providencia) NUNCA trae extensión —
     * a diferencia de un archivo subido, donde ya viene de
     * getClientOriginalName(). Sin extensión, el navegador guarda el
     * archivo sin ".docx"/".pdf" y Windows no sabe con qué programa
     * abrirlo, aunque el archivo en sí esté completo y correcto: eso es lo
     * que se reportó como "no se descargó el word". Se resuelve agregando
     * la extensión real del archivo guardado cuando "nombre" no la trae.
     */
    public function nombreDescarga(): string
    {
        $nombre = trim((string) $this->nombre) !== '' ? trim((string) $this->nombre) : 'documento';
        $extensionArchivo = strtolower(pathinfo((string) $this->ruta_archivo, PATHINFO_EXTENSION));
        $extensionNombre = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));

        if ($extensionArchivo !== '' && $extensionNombre !== $extensionArchivo) {
            $nombre .= '.'.$extensionArchivo;
        }

        return $nombre;
    }

    public function prorrogas()
    {
        return $this->hasMany(Prorroga::class, 'documento_id');
    }

    public function aclaraciones()
    {
        return $this->hasMany(Aclaracion::class, 'documento_id');
    }

    public function recursos_revision()
    {
        return $this->hasMany(RecursoRevision::class, 'documento_id');
    }
}

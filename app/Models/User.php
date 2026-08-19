<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role_id', 'dependencia_id', 'activo'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    // --- Columnas de negocio agregadas en 2026_02_01_000005_add_business_columns_to_users_table ---

    public function rol()
    {
        return $this->belongsTo(Rol::class, 'role_id');
    }

    public function dependencia()
    {
        return $this->belongsTo(Dependencia::class, 'dependencia_id');
    }

    public function enlace()
    {
        return $this->hasOne(Enlace::class, 'user_id');
    }

    public function solicitudesCreadas()
    {
        return $this->hasMany(Solicitud::class, 'creado_por_user_id');
    }

    public function historialAcciones()
    {
        return $this->hasMany(SolicitudHistorial::class, 'user_id');
    }

    public function actuacionesRegistradas()
    {
        return $this->hasMany(Actuacion::class, 'user_id');
    }

    public function documentosSubidos()
    {
        return $this->hasMany(Documento::class, 'subido_por_user_id');
    }

    public function correosEnviados()
    {
        return $this->hasMany(CorreoEnviado::class, 'enviado_por_user_id');
    }

    public function notificaciones()
    {
        return $this->hasMany(Notificacion::class, 'user_id');
    }

    public function logs()
    {
        return $this->hasMany(Log::class, 'user_id');
    }

    // --- Helpers de autorización (Fase 3) ---

    /**
     * ¿El usuario tiene el permiso indicado (por clave, ej. 'solicitudes.validar')?
     * Se apoya en la relación roles->permissions ya definida en Rol/Permission.
     */
    public function hasPermission(string $clave): bool
    {
        if (! $this->role_id) {
            return false;
        }

        return $this->rol
            ->permissions()
            ->where('clave', $clave)
            ->exists();
    }

    /**
     * ¿El usuario tiene alguno de los permisos indicados?
     */
    public function hasAnyPermission(array $claves): bool
    {
        if (! $this->role_id) {
            return false;
        }

        return $this->rol
            ->permissions()
            ->whereIn('clave', $claves)
            ->exists();
    }

    /**
     * ¿El usuario tiene el rol indicado (por nombre, ej. 'Administrador')?
     */
    public function hasRole(string $nombre): bool
    {
        return $this->rol && $this->rol->nombre === $nombre;
    }

    /**
     * ¿El usuario tiene alguno de los roles indicados?
     */
    public function hasAnyRole(array $nombres): bool
    {
        return $this->rol && in_array($this->rol->nombre, $nombres, true);
    }
}

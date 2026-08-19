<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Catalogo de permisos granulares
class Permission extends Model
{
    use HasFactory;

    protected $table = 'permissions';

    public $timestamps = false;

    protected $fillable = [
        'clave',
        'nombre',
        'descripcion',
    ];

    public function role_permission()
    {
        return $this->hasMany(RolePermission::class, 'permission_id');
    }

    public function roles()
    {
        return $this->belongsToMany(Rol::class, 'role_permission', 'permission_id', 'role_id');
    }
}

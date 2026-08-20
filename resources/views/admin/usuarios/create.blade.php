@extends('layouts.admin')

@section('title', 'Nuevo usuario')

@section('content')
  <a href="{{ route('admin.usuarios.index') }}" style="font-size:13px; color:#52514e; text-decoration:none;">← Volver al listado</a>

  @if ($errors->any())
    <div style="background:#fdecea; border:1px solid #f3c6c1; color:#8a2c22; border-radius:8px; padding:10px 14px; font-size:13.5px; margin:12px 0;">
      <ul style="margin:0; padding-left:18px;">@foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul>
    </div>
  @endif

  <div class="card" style="max-width:520px; margin-top:12px;">
    <h2 style="margin:0 0 16px;">Nuevo usuario</h2>

    <form method="POST" action="{{ route('admin.usuarios.store') }}" style="display:flex; flex-direction:column; gap:12px;">
      @csrf

      <div>
        <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">Nombre completo</label>
        <input type="text" name="name" value="{{ old('name') }}" required
               style="width:100%; padding:9px 12px; border:1px solid #d8d6cf; border-radius:7px; font-size:13.5px;">
      </div>

      <div>
        <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">Correo electrónico</label>
        <input type="email" name="email" value="{{ old('email') }}" required
               style="width:100%; padding:9px 12px; border:1px solid #d8d6cf; border-radius:7px; font-size:13.5px;">
      </div>

      <div>
        <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">Contraseña</label>
        <input type="password" name="password" required minlength="8"
               style="width:100%; padding:9px 12px; border:1px solid #d8d6cf; border-radius:7px; font-size:13.5px;">
        <p style="font-size:11.5px; color:#898781; margin:4px 0 0;">Mínimo 8 caracteres. Compártela con el usuario por un medio seguro.</p>
      </div>

      <div>
        <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">Rol</label>
        <select name="role_id" required style="width:100%; padding:9px 12px; border:1px solid #d8d6cf; border-radius:7px; font-size:13.5px;">
          <option value="">Selecciona un rol…</option>
          @foreach ($roles as $rol)
            <option value="{{ $rol->id }}" @selected(old('role_id') == $rol->id)>{{ $rol->nombre }}</option>
          @endforeach
        </select>
      </div>

      <div>
        <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">Dependencia (opcional)</label>
        <select name="dependencia_id" style="width:100%; padding:9px 12px; border:1px solid #d8d6cf; border-radius:7px; font-size:13.5px;">
          <option value="">Sin dependencia asignada</option>
          @foreach ($dependencias as $dep)
            <option value="{{ $dep->id }}" @selected(old('dependencia_id') == $dep->id)>{{ $dep->nombre }}</option>
          @endforeach
        </select>
      </div>

      <button type="submit" style="align-self:flex-start; background:#2a78d6; color:#fff; border:0; border-radius:7px; padding:9px 18px; font-size:13.5px; font-weight:600; cursor:pointer; margin-top:6px;">
        Crear usuario
      </button>
    </form>
  </div>
@endsection

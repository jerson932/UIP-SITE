@extends('layouts.admin')

@section('title', 'Editar enlace')

@section('content')
  <a href="{{ route('admin.enlaces.index') }}" style="font-size:13px; color:#52514e; text-decoration:none;">← Volver al listado</a>

  @if ($errors->any())
    <div style="background:#fdecea; border:1px solid #f3c6c1; color:#8a2c22; border-radius:8px; padding:10px 14px; font-size:13.5px; margin:12px 0;">
      <ul style="margin:0; padding-left:18px;">@foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul>
    </div>
  @endif

  @if (session('acceso_generado'))
    @php $acceso = session('acceso_generado'); @endphp
    <div style="background:#eef4fd; border:1px solid #bcd7f7; border-radius:8px; padding:14px 16px; font-size:13.5px; margin:12px 0;">
      <strong>Comparte este acceso con {{ $enlace->nombre }}:</strong>
      <div style="margin-top:8px; display:flex; flex-direction:column; gap:3px; font-family:monospace; font-size:13px;">
        <div>URL: {{ $acceso['url'] }}</div>
        <div>Usuario: {{ $acceso['email'] }}</div>
        <div>Contraseña temporal: {{ $acceso['password'] }}</div>
      </div>
      <p style="font-size:12px; color:#52514e; margin:8px 0 0;">Esta contraseña solo se muestra una vez — cópiala ahora. El enlace puede cambiarla luego desde su propio perfil.</p>
    </div>
  @endif

  <div style="display:grid; grid-template-columns:minmax(0,420px) minmax(0,420px); gap:18px; margin-top:12px; align-items:start;">
    <div class="card">
      <h2 style="margin:0 0 16px;">Datos del enlace</h2>

      <form method="POST" action="{{ route('admin.enlaces.update', $enlace) }}" style="display:flex; flex-direction:column; gap:12px;">
        @csrf

        <div>
          <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">Dependencia</label>
          <select name="dependencia_id" required style="width:100%; padding:9px 12px; border:1px solid #d8d6cf; border-radius:7px; font-size:13.5px;">
            @foreach ($dependencias as $dep)
              <option value="{{ $dep->id }}" @selected(old('dependencia_id', $enlace->dependencia_id) == $dep->id)>{{ $dep->nombre }}</option>
            @endforeach
          </select>
        </div>

        <div>
          <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">Nombre del contacto</label>
          <input type="text" name="nombre" value="{{ old('nombre', $enlace->nombre) }}" required
                 style="width:100%; padding:9px 12px; border:1px solid #d8d6cf; border-radius:7px; font-size:13.5px;">
        </div>

        <div>
          <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">Correo de contacto</label>
          <input type="email" name="correo" value="{{ old('correo', $enlace->correo) }}"
                 style="width:100%; padding:9px 12px; border:1px solid #d8d6cf; border-radius:7px; font-size:13.5px;">
        </div>

        <div>
          <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">Teléfono</label>
          <input type="text" name="telefono" value="{{ old('telefono', $enlace->telefono) }}"
                 style="width:100%; padding:9px 12px; border:1px solid #d8d6cf; border-radius:7px; font-size:13.5px;">
        </div>

        <label style="font-size:12.5px; display:flex; align-items:center; gap:6px;">
          <input type="hidden" name="activo" value="0">
          <input type="checkbox" name="activo" value="1" @checked(old('activo', $enlace->activo))> Enlace activo
        </label>

        <button type="submit" style="align-self:flex-start; background:#2a78d6; color:#fff; border:0; border-radius:7px; padding:9px 18px; font-size:13.5px; font-weight:600; cursor:pointer; margin-top:6px;">
          Guardar cambios
        </button>
      </form>
    </div>

    <div class="card">
      <h2 style="margin:0 0 6px;">Acceso al portal de enlace</h2>

      @if ($enlace->user)
        <p style="font-size:13px; color:#52514e; margin:0 0 4px;">
          Ya tiene acceso: <strong>{{ $enlace->user->email }}</strong>
          — {{ $enlace->user->activo ? 'cuenta activa' : 'cuenta desactivada' }}.
        </p>
        <p style="font-size:12px; color:#898781; margin:0 0 14px;">
          URL de acceso: <code>{{ $loginUrl }}</code>. Puedes cambiar el correo o generar una nueva contraseña
          temporal a continuación.
        </p>
      @else
        <p style="font-size:12.5px; color:#898781; margin:0 0 14px;">
          Este enlace todavía no puede iniciar sesión. Asígnale un correo para crear su cuenta con el rol
          "Enlace" (solo verá los expedientes de {{ $enlace->dependencia?->nombre ?? 'su dependencia' }}).
        </p>
      @endif

      <form method="POST" action="{{ route('admin.enlaces.acceso', $enlace) }}"
            onsubmit="return confirm('¿{{ $enlace->user ? 'Reiniciar la contraseña de' : 'Crear acceso para' }} este enlace?');"
            style="display:flex; flex-direction:column; gap:8px;">
        @csrf
        <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">Correo de inicio de sesión</label>
        <input type="email" name="email" value="{{ old('email', $enlace->user->email ?? $enlace->correo) }}" required
               style="width:100%; padding:9px 12px; border:1px solid #d8d6cf; border-radius:7px; font-size:13.5px;">
        <button type="submit"
                style="align-self:flex-start; background:{{ $enlace->user ? '#a86a06' : '#0ca30c' }}; color:#fff; border:0; border-radius:7px; padding:9px 18px; font-size:13.5px; font-weight:600; cursor:pointer; margin-top:4px;">
          {{ $enlace->user ? 'Generar nueva contraseña' : 'Crear acceso' }}
        </button>
      </form>
    </div>
  </div>
@endsection

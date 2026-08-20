@extends('layouts.admin')

@section('title', 'Editar plantilla')

@section('content')
  <a href="{{ route('admin.configuracion.index') }}" style="font-size:13px; color:#52514e; text-decoration:none;">← Volver a configuración</a>

  @if ($errors->any())
    <div style="background:#fdecea; border:1px solid #f3c6c1; color:#8a2c22; border-radius:8px; padding:10px 14px; font-size:13.5px; margin:12px 0;">
      <ul style="margin:0; padding-left:18px;">@foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul>
    </div>
  @endif

  <div class="card" style="max-width:640px; margin-top:12px;">
    <h2 style="margin:0 0 2px;">{{ $plantilla->evento }}</h2>
    <p style="margin:0 0 16px; font-size:12.5px; color:#898781;">Clave: <code>{{ $plantilla->clave }}</code></p>

    <div style="background:#f4f7fb; border:1px solid #dbe6f2; border-radius:8px; padding:10px 14px; font-size:12.5px; margin-bottom:16px;">
      Placeholders disponibles (no todas las plantillas usan todos): <code>@{{nombre}}</code>,
      <code>@{{contrasena}}</code>, <code>@{{asunto}}</code>, <code>@{{correlativo_recurso}}</code>.
      Se sustituyen automáticamente al enviar el correo real.
    </div>

    <form method="POST" action="{{ route('admin.configuracion.plantillas.actualizar', $plantilla) }}" style="display:flex; flex-direction:column; gap:12px;">
      @csrf

      <div>
        <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">Asunto</label>
        <input type="text" name="asunto_template" value="{{ old('asunto_template', $plantilla->asunto_template) }}" required maxlength="255"
               style="width:100%; padding:9px 12px; border:1px solid #d8d6cf; border-radius:7px; font-size:13.5px;">
      </div>

      <div>
        <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">Cuerpo del correo</label>
        <textarea name="cuerpo_template" rows="14" required
                  style="width:100%; padding:9px 12px; border:1px solid #d8d6cf; border-radius:7px; font-size:13.5px; font-family:inherit; white-space:pre-wrap;">{{ old('cuerpo_template', $plantilla->cuerpo_template) }}</textarea>
      </div>

      <label style="display:flex; align-items:center; gap:8px; font-size:13px; color:#52514e;">
        <input type="checkbox" name="activa" value="1" @checked(old('activa', $plantilla->activa))>
        Plantilla activa (si se desactiva, ese correo simplemente no se envía y queda registrado en el log del sistema)
      </label>

      <button type="submit" style="align-self:flex-start; background:#2a78d6; color:#fff; border:0; border-radius:7px; padding:9px 18px; font-size:13.5px; font-weight:600; cursor:pointer; margin-top:6px;">
        Guardar cambios
      </button>
    </form>
  </div>
@endsection

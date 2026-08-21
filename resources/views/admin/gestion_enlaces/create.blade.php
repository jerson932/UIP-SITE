@extends('layouts.admin')

@section('title', 'Nuevo enlace')

@section('content')
  <a href="{{ route('admin.enlaces.index') }}" style="font-size:13px; color:#52514e; text-decoration:none;">← Volver al listado</a>

  @if ($errors->any())
    <div style="background:#fdecea; border:1px solid #f3c6c1; color:#8a2c22; border-radius:8px; padding:10px 14px; font-size:13.5px; margin:12px 0;">
      <ul style="margin:0; padding-left:18px;">@foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul>
    </div>
  @endif

  <div class="card" style="max-width:520px; margin-top:12px;">
    <h2 style="margin:0 0 6px;">Nuevo enlace</h2>
    <p style="font-size:12.5px; color:#898781; margin:0 0 16px;">
      Esto solo registra el contacto de la dependencia. Si además necesita entrar a su propio portal, dale
      acceso desde "Editar" una vez creado.
    </p>

    <form method="POST" action="{{ route('admin.enlaces.store') }}" style="display:flex; flex-direction:column; gap:12px;">
      @csrf

      <div>
        <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">Dependencia</label>
        <select name="dependencia_id" required style="width:100%; padding:9px 12px; border:1px solid #d8d6cf; border-radius:7px; font-size:13.5px;">
          <option value="">Selecciona una dependencia…</option>
          @foreach ($dependencias as $dep)
            <option value="{{ $dep->id }}" @selected(old('dependencia_id') == $dep->id)>{{ $dep->nombre }}</option>
          @endforeach
        </select>
      </div>

      <div>
        <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">Nombre del contacto</label>
        <input type="text" name="nombre" value="{{ old('nombre') }}" required
               style="width:100%; padding:9px 12px; border:1px solid #d8d6cf; border-radius:7px; font-size:13.5px;">
      </div>

      <div>
        <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">Correo de contacto (opcional)</label>
        <input type="email" name="correo" value="{{ old('correo') }}"
               style="width:100%; padding:9px 12px; border:1px solid #d8d6cf; border-radius:7px; font-size:13.5px;">
      </div>

      <div>
        <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">Teléfono (opcional)</label>
        <input type="text" name="telefono" value="{{ old('telefono') }}"
               style="width:100%; padding:9px 12px; border:1px solid #d8d6cf; border-radius:7px; font-size:13.5px;">
      </div>

      <button type="submit" style="align-self:flex-start; background:#2a78d6; color:#fff; border:0; border-radius:7px; padding:9px 18px; font-size:13.5px; font-weight:600; cursor:pointer; margin-top:6px;">
        Crear enlace
      </button>
    </form>
  </div>
@endsection

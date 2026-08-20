@extends('layouts.admin')

@section('title', 'Registrar solicitud')

@section('content')
  <a href="{{ route('admin.solicitudes.index') }}" style="font-size:13px; color:#52514e; text-decoration:none;">← Volver al listado</a>

  @if ($errors->any())
    <div style="background:#fdecea; border:1px solid #f3c6c1; color:#8a2c22; border-radius:8px; padding:10px 14px; font-size:13.5px; margin:12px 0;">
      <ul style="margin:0; padding-left:18px;">@foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul>
    </div>
  @endif

  <div class="card" style="max-width:620px; margin-top:12px;">
    <h2 style="margin:0 0 4px;">Registrar solicitud</h2>
    <p style="margin:0 0 16px; font-size:13px; color:#898781;">
      Para expedientes que llegan físicos, por correo o electrónicamente y se registran manualmente en la UIP.
      Se genera el código NS y el código de acceso automáticamente, en estado "Pendiente de validación".
    </p>

    <form method="POST" action="{{ route('admin.solicitudes.store') }}" style="display:flex; flex-direction:column; gap:12px;">
      @csrf

      <div>
        <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">Medio de recepción</label>
        <select name="medio_recepcion" required style="width:100%; padding:9px 12px; border:1px solid #d8d6cf; border-radius:7px; font-size:13.5px;">
          @foreach ($medios as $clave => $etiqueta)
            <option value="{{ $clave }}" @selected(old('medio_recepcion', 'electronica') === $clave)>{{ $etiqueta }}</option>
          @endforeach
        </select>
      </div>

      <div>
        <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">Nombre completo del interesado</label>
        <input type="text" name="nombre" value="{{ old('nombre') }}" required
               style="width:100%; padding:9px 12px; border:1px solid #d8d6cf; border-radius:7px; font-size:13.5px;">
      </div>

      <div style="display:flex; gap:12px;">
        <div style="flex:1;">
          <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">Correo (opcional)</label>
          <input type="email" name="correo" value="{{ old('correo') }}"
                 style="width:100%; padding:9px 12px; border:1px solid #d8d6cf; border-radius:7px; font-size:13.5px;">
          <p style="font-size:11.5px; color:#898781; margin:4px 0 0;">Si lo proporciona, se le envía el código de acceso por correo.</p>
        </div>
        <div style="flex:1;">
          <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">Teléfono (opcional)</label>
          <input type="text" name="telefono" value="{{ old('telefono') }}"
                 style="width:100%; padding:9px 12px; border:1px solid #d8d6cf; border-radius:7px; font-size:13.5px;">
        </div>
      </div>

      <div style="display:flex; gap:12px;">
        <div style="flex:1;">
          <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">Género (opcional)</label>
          <select name="genero" style="width:100%; padding:9px 12px; border:1px solid #d8d6cf; border-radius:7px; font-size:13.5px;">
            <option value="">No indicar</option>
            @foreach ($generos as $g)
              <option value="{{ $g }}" @selected(old('genero') === $g)>{{ $g }}</option>
            @endforeach
          </select>
        </div>
        <div style="flex:1;">
          <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">Rango de edad (opcional)</label>
          <select name="rango_edad" style="width:100%; padding:9px 12px; border:1px solid #d8d6cf; border-radius:7px; font-size:13.5px;">
            <option value="">No indicar</option>
            @foreach ($rangosEdad as $r)
              <option value="{{ $r }}" @selected(old('rango_edad') === $r)>{{ $r }}</option>
            @endforeach
          </select>
        </div>
        <div style="flex:1;">
          <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">Departamento (opcional)</label>
          <select name="departamento" style="width:100%; padding:9px 12px; border:1px solid #d8d6cf; border-radius:7px; font-size:13.5px;">
            <option value="">No indicar</option>
            @foreach ($departamentos as $d)
              <option value="{{ $d }}" @selected(old('departamento') === $d)>{{ $d }}</option>
            @endforeach
          </select>
        </div>
      </div>

      <div>
        <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">Información solicitada</label>
        <textarea name="asunto" rows="5" required minlength="10"
                  style="width:100%; padding:9px 12px; border:1px solid #d8d6cf; border-radius:7px; font-size:13.5px; font-family:inherit;">{{ old('asunto') }}</textarea>
      </div>

      <button type="submit" style="align-self:flex-start; background:#2a78d6; color:#fff; border:0; border-radius:7px; padding:9px 18px; font-size:13.5px; font-weight:600; cursor:pointer; margin-top:6px;">
        Registrar solicitud
      </button>
    </form>
  </div>
@endsection

@extends('layouts.admin')

@section('title', 'Configuración')

@section('content')
  @if (session('status'))
    <div style="background:#e9f7ea; border:1px solid #b9e3bb; color:#256428; border-radius:8px; padding:10px 14px; font-size:13.5px; margin-bottom:14px;">{{ session('status') }}</div>
  @endif
  @if (session('error'))
    <div style="background:#fdecea; border:1px solid #f3c6c1; color:#8a2c22; border-radius:8px; padding:10px 14px; font-size:13.5px; margin-bottom:14px;">{{ session('error') }}</div>
  @endif
  @if ($errors->any())
    <div style="background:#fdecea; border:1px solid #f3c6c1; color:#8a2c22; border-radius:8px; padding:10px 14px; font-size:13.5px; margin-bottom:14px;">
      <ul style="margin:0; padding-left:18px;">@foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul>
    </div>
  @endif

  <h2 style="margin:0 0 4px;">Configuración general</h2>
  <p style="margin:0 0 16px; color:#52514e; font-size:13.5px;">Plantillas de correo (Fase 11) y días feriados usados en el cálculo de plazos.</p>

  <div class="card">
    <h4 style="margin:0 0 10px; font-size:13px; color:#898781; text-transform:uppercase; letter-spacing:.03em;">Plantillas de correo</h4>
    <table style="width:100%; border-collapse:collapse; font-size:13.5px;">
      <thead>
        <tr style="text-align:left; border-bottom:1px solid #e1e0d9; color:#898781;">
          <th style="padding:8px 4px;">Evento</th>
          <th style="padding:8px 4px;">Clave</th>
          <th style="padding:8px 4px;">Asunto</th>
          <th style="padding:8px 4px;">Estado</th>
          <th style="padding:8px 4px;"></th>
        </tr>
      </thead>
      <tbody>
        @foreach ($plantillas as $p)
          <tr style="border-bottom:1px solid #f0efec;">
            <td style="padding:8px 4px;">{{ $p->evento }}</td>
            <td style="padding:8px 4px;"><code style="font-size:12px;">{{ $p->clave }}</code></td>
            <td style="padding:8px 4px; max-width:260px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $p->asunto_template }}</td>
            <td style="padding:8px 4px;">
              @if ($p->activa)
                <span style="color:#0ca30c;">Activa</span>
              @else
                <span style="color:#d03b3b;">Inactiva</span>
              @endif
            </td>
            <td style="padding:8px 4px; text-align:right;">
              <a href="{{ route('admin.configuracion.plantillas.editar', $p) }}" style="color:#2a78d6; text-decoration:none; font-size:12.5px;">Editar</a>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <div class="card">
    <h4 style="margin:0 0 10px; font-size:13px; color:#898781; text-transform:uppercase; letter-spacing:.03em;">Días feriados</h4>
    <p style="margin:0 0 12px; font-size:12.5px; color:#898781;">
      Se usan para calcular todos los plazos en días hábiles del sistema (plazo de respuesta, aclaraciones, acceso al portal del ciudadano, etc.).
    </p>

    <form method="POST" action="{{ route('admin.configuracion.feriados.guardar') }}" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; margin-bottom:16px; padding-bottom:16px; border-bottom:1px solid #f0efec;">
      @csrf
      <div>
        <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">Fecha</label>
        <input type="date" name="fecha" required style="padding:8px 10px; border:1px solid #d8d6cf; border-radius:7px; font-size:13.5px;">
      </div>
      <div style="flex:1; min-width:200px;">
        <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">Descripción (opcional)</label>
        <input type="text" name="descripcion" placeholder="Ej. Día del Trabajo" style="width:100%; padding:8px 10px; border:1px solid #d8d6cf; border-radius:7px; font-size:13.5px;">
      </div>
      <button type="submit" style="padding:8px 16px; border:0; border-radius:8px; background:#2a78d6; color:#fff; font-size:13.5px; cursor:pointer;">Agregar feriado</button>
    </form>

    <table style="width:100%; border-collapse:collapse; font-size:13.5px;">
      <thead>
        <tr style="text-align:left; border-bottom:1px solid #e1e0d9; color:#898781;">
          <th style="padding:6px 4px;">Fecha</th>
          <th style="padding:6px 4px;">Descripción</th>
          <th style="padding:6px 4px;"></th>
        </tr>
      </thead>
      <tbody>
        @forelse ($feriados as $f)
          <tr style="border-bottom:1px solid #f0efec;">
            <td style="padding:8px 4px;">{{ $f->fecha->format('d/m/Y') }}</td>
            <td style="padding:8px 4px;">{{ $f->descripcion ?? '—' }}</td>
            <td style="padding:8px 4px; text-align:right;">
              <form method="POST" action="{{ route('admin.configuracion.feriados.eliminar', $f) }}" style="display:inline;">
                @csrf
                <button type="submit" style="background:none; border:0; padding:0; font-size:12.5px; cursor:pointer; color:#d03b3b;">Eliminar</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="3" style="padding:12px 4px; color:#898781;">No hay feriados registrados.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
@endsection

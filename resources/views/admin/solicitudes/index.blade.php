@extends('layouts.admin')

@section('title', 'Solicitudes')
@section('pageTitle', 'Solicitudes')
@section('pageSubtitle', 'Listado general — filtra por estado o busca por interesado, correo, código o contraseña')

@section('content')
  @if (session('status'))
    <div style="background:#e9f7ea; border:1px solid #b9e3bb; color:#256428; border-radius:8px; padding:10px 14px; font-size:13.5px; margin-bottom:14px;">{{ session('status') }}</div>
  @endif

  @if (auth()->user()->hasPermission('solicitudes.crear'))
    <div style="display:flex; justify-content:flex-end; margin-bottom:14px;">
      <a href="{{ route('admin.solicitudes.create') }}"
         style="background:#2a78d6; color:#fff; text-decoration:none; border-radius:7px; padding:9px 16px; font-size:13.5px; font-weight:600; white-space:nowrap;">
        + Registrar solicitud
      </a>
    </div>
  @endif

  <form method="GET" action="{{ route('admin.solicitudes.index') }}" class="card" style="padding:14px 18px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
    <input type="text" name="q" value="{{ $busqueda }}" placeholder="Buscar por nombre, correo, código NS o contraseña…"
           style="flex:1; min-width:220px; padding:8px 12px; border:1px solid #d8d6cf; border-radius:8px; font-size:13.5px;">
    <select name="estado" style="padding:8px 12px; border:1px solid #d8d6cf; border-radius:8px; font-size:13.5px;">
      <option value="">Todos los estados</option>
      @foreach ($estados as $estado)
        <option value="{{ $estado->clave }}" @selected($filtroEstado === $estado->clave)>{{ $estado->etiqueta }}</option>
      @endforeach
    </select>
    <button type="submit" style="padding:8px 16px; border:0; border-radius:8px; background:#2a78d6; color:#fff; font-size:13.5px; cursor:pointer;">Filtrar</button>
    @if ($busqueda || $filtroEstado)
      <a href="{{ route('admin.solicitudes.index') }}" style="font-size:13px; color:#898781;">Limpiar</a>
    @endif
  </form>

  <div class="card" style="padding:0; overflow-x:auto; margin-top:14px;">
    <table style="width:100%; border-collapse:collapse; font-size:13.5px;">
      <thead>
        <tr style="text-align:left; border-bottom:1px solid #e1e0d9; color:#898781;">
          <th style="padding:10px 14px;">Código NS</th>
          <th style="padding:10px 14px;">Contraseña</th>
          <th style="padding:10px 14px;">Fecha ingreso</th>
          <th style="padding:10px 14px;">Interesado</th>
          <th style="padding:10px 14px;">Asunto</th>
          <th style="padding:10px 14px;">Estado</th>
          <th style="padding:10px 14px;">Días restantes</th>
          <th style="padding:10px 14px;"></th>
        </tr>
      </thead>
      <tbody>
        @forelse ($solicitudes as $s)
          @php $dias = $s->diasHabilesRestantes(); @endphp
          <tr style="border-bottom:1px solid #f0efec;">
            <td style="padding:10px 14px; font-weight:600;">{{ $s->codigo_ns }}</td>
            <td style="padding:10px 14px;">{{ $s->contrasena ?? '—' }}</td>
            <td style="padding:10px 14px;">{{ $s->fecha_ingreso?->format('d/m/Y') }}</td>
            <td style="padding:10px 14px;">{{ $s->solicitante->nombre }}</td>
            <td style="padding:10px 14px; max-width:280px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $s->asunto }}</td>
            <td style="padding:10px 14px;">@include('admin.partials.estado-badge', ['estado' => $s->estado])</td>
            <td style="padding:10px 14px; color:{{ $dias === null ? '#898781' : ($dias < 0 ? '#d03b3b' : ($dias <= 2 ? '#a86a06' : '#0ca30c')) }};">
              @if ($dias === null) — @elseif ($dias < 0) {{ abs($dias) }} días vencida @else {{ $dias }} días hábiles @endif
            </td>
            <td style="padding:10px 14px;">
              <a href="{{ route('admin.solicitudes.show', $s) }}" style="color:#2a78d6; font-weight:600; text-decoration:none;">Ver →</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="8" style="padding:24px; text-align:center; color:#898781;">No hay solicitudes que coincidan con el filtro.</td></tr>
        @endforelse
      </tbody>
    </table>
    <div style="padding:14px 18px; border-top:1px solid #e1e0d9;">
      {{ $solicitudes->links() }}
    </div>
  </div>
@endsection

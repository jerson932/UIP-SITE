@extends('layouts.admin')

@section('title', 'Reportes')
@section('pageTitle', 'Reportes')
@section('pageSubtitle', 'Estadísticas de solicitudes según los filtros seleccionados — "Exportar" descarga exactamente lo que ves aquí')

@section('content')
  <form method="GET" action="{{ route('admin.reportes.index') }}" class="card" style="padding:14px 18px; display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
    <div>
      <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">Ingreso desde</label>
      <input type="date" name="desde" value="{{ $filtros['desde'] ?? '' }}" style="padding:8px 10px; border:1px solid #d8d6cf; border-radius:7px; font-size:13.5px;">
    </div>
    <div>
      <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">Ingreso hasta</label>
      <input type="date" name="hasta" value="{{ $filtros['hasta'] ?? '' }}" style="padding:8px 10px; border:1px solid #d8d6cf; border-radius:7px; font-size:13.5px;">
    </div>
    <div>
      <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">Estado</label>
      <select name="estado" style="padding:8px 10px; border:1px solid #d8d6cf; border-radius:7px; font-size:13.5px;">
        <option value="">Todos</option>
        @foreach ($estados as $estado)
          <option value="{{ $estado->clave }}" @selected(($filtros['estado'] ?? '') === $estado->clave)>{{ $estado->etiqueta }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">Dependencia</label>
      <select name="dependencia_id" style="padding:8px 10px; border:1px solid #d8d6cf; border-radius:7px; font-size:13.5px;">
        <option value="">Todas</option>
        @foreach ($dependencias as $dep)
          <option value="{{ $dep->id }}" @selected((string) ($filtros['dependencia_id'] ?? '') === (string) $dep->id)>{{ $dep->nombre }}</option>
        @endforeach
      </select>
    </div>
    <button type="submit" style="padding:8px 16px; border:0; border-radius:8px; background:#2a78d6; color:#fff; font-size:13.5px; cursor:pointer;">Filtrar</button>
    @if (array_filter($filtros))
      <a href="{{ route('admin.reportes.index') }}" style="font-size:13px; color:#898781;">Limpiar</a>
    @endif
    <a href="{{ route('admin.reportes.exportar', $filtros) }}"
       style="margin-left:auto; padding:8px 16px; border-radius:8px; background:#0ca30c; color:#fff; font-size:13.5px; text-decoration:none; font-weight:600;">
      ⬇ Exportar a Excel (.xlsx)
    </a>
  </form>

  <div style="display:flex; gap:14px; margin:16px 0; flex-wrap:wrap;">
    <div class="card" style="flex:1; min-width:160px; text-align:center;">
      <div style="font-size:28px; font-weight:700;">{{ $total }}</div>
      <div style="font-size:12.5px; color:#898781; margin-top:4px;">Total de solicitudes</div>
    </div>
    <div class="card" style="flex:1; min-width:160px; text-align:center;">
      <div style="font-size:28px; font-weight:700; color:#d03b3b;">{{ $vencidas }}</div>
      <div style="font-size:12.5px; color:#898781; margin-top:4px;">Vencidas (activas)</div>
    </div>
    <div class="card" style="flex:1; min-width:160px; text-align:center;">
      <div style="font-size:28px; font-weight:700; color:#a86a06;">{{ $proximasAVencer }}</div>
      <div style="font-size:12.5px; color:#898781; margin-top:4px;">Próximas a vencer (≤2 días hábiles)</div>
    </div>
  </div>

  <div style="display:flex; gap:14px; flex-wrap:wrap;">
    <div class="card" style="flex:1; min-width:260px;">
      <h4 style="margin:0 0 10px; font-size:13px; color:#898781; text-transform:uppercase; letter-spacing:.03em;">Por estado</h4>
      @forelse ($porEstado as $fila)
        <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #f0efec; font-size:13.5px;">
          <span><span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:{{ $fila['color'] }}; margin-right:6px;"></span>{{ $fila['etiqueta'] }}</span>
          <strong>{{ $fila['total'] }}</strong>
        </div>
      @empty
        <p style="font-size:13px; color:#898781; margin:0;">Sin datos para este filtro.</p>
      @endforelse
    </div>

    <div class="card" style="flex:1; min-width:260px;">
      <h4 style="margin:0 0 10px; font-size:13px; color:#898781; text-transform:uppercase; letter-spacing:.03em;">Por dependencia</h4>
      @forelse ($porDependencia as $fila)
        <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #f0efec; font-size:13.5px;">
          <span>{{ $fila['nombre'] }}</span>
          <strong>{{ $fila['total'] }}</strong>
        </div>
      @empty
        <p style="font-size:13px; color:#898781; margin:0;">Sin datos para este filtro.</p>
      @endforelse
    </div>

    <div class="card" style="flex:1; min-width:260px;">
      <h4 style="margin:0 0 10px; font-size:13px; color:#898781; text-transform:uppercase; letter-spacing:.03em;">Por medio de recepción</h4>
      @forelse ($porMedio as $fila)
        <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #f0efec; font-size:13.5px;">
          <span>{{ $fila['nombre'] }}</span>
          <strong>{{ $fila['total'] }}</strong>
        </div>
      @empty
        <p style="font-size:13px; color:#898781; margin:0;">Sin datos para este filtro.</p>
      @endforelse
    </div>

    <div class="card" style="flex:1; min-width:260px;">
      <h4 style="margin:0 0 10px; font-size:13px; color:#898781; text-transform:uppercase; letter-spacing:.03em;">Por género</h4>
      @forelse ($porGenero as $fila)
        <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #f0efec; font-size:13.5px;">
          <span>{{ $fila['nombre'] }}</span>
          <strong>{{ $fila['total'] }}</strong>
        </div>
      @empty
        <p style="font-size:13px; color:#898781; margin:0;">Sin datos para este filtro.</p>
      @endforelse
    </div>

    <div class="card" style="flex:1; min-width:260px;">
      <h4 style="margin:0 0 10px; font-size:13px; color:#898781; text-transform:uppercase; letter-spacing:.03em;">Por departamento</h4>
      @forelse ($porDepartamento as $fila)
        <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #f0efec; font-size:13.5px;">
          <span>{{ $fila['nombre'] }}</span>
          <strong>{{ $fila['total'] }}</strong>
        </div>
      @empty
        <p style="font-size:13px; color:#898781; margin:0;">Sin datos para este filtro.</p>
      @endforelse
    </div>
  </div>
@endsection

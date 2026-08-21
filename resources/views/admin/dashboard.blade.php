@extends('layouts.admin')

@section('title', 'Dashboard')
@section('pageTitle', 'Dashboard')
@section('pageSubtitle', 'Resumen general del sistema')

@section('content')
  <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:18px; gap:16px; flex-wrap:wrap;">
    <div>
      <h2 style="margin:0 0 4px;">Recepción de Solicitudes de Información Pública</h2>
      <p style="margin:0; color:#52514e; font-size:13.5px;">Hola, {{ $user->name }} — Rol: <strong>{{ $user->rol?->nombre ?? 'Sin rol asignado' }}</strong>@if ($user->dependencia) · Dependencia: <strong>{{ $user->dependencia->nombre }}</strong>@endif</p>
    </div>
    @if ($user->hasPermission('solicitudes.crear'))
      <a href="{{ route('admin.solicitudes.create') }}"
         style="background:#2a78d6; color:#fff; text-decoration:none; border-radius:7px; padding:9px 16px; font-size:13.5px; font-weight:600; white-space:nowrap;">
        + Nueva solicitud
      </a>
    @endif
  </div>

  @php
    $stats = [
      ['label' => 'Total solicitudes', 'total' => $totalSolicitudes, 'icon' => 'file', 'bg' => '#eaf1fb', 'fg' => '#2a78d6', 'href' => route('admin.solicitudes.index')],
      ['label' => 'Pendientes de validación', 'total' => $pendientesValidacion, 'icon' => 'clock', 'bg' => '#fdf3e2', 'fg' => '#a86a06', 'href' => route('admin.solicitudes.index', ['estado' => 'pendiente_validacion'])],
      ['label' => 'En seguimiento', 'total' => $enSeguimiento, 'icon' => 'clock', 'bg' => '#e9f7ea', 'fg' => '#1baf7a', 'href' => route('admin.solicitudes.index', ['estado' => 'en_seguimiento'])],
      ['label' => 'Por vencer', 'total' => $proximasAVencer, 'icon' => 'calendar', 'bg' => '#fdf3e2', 'fg' => '#a86a06', 'href' => route('admin.reportes.index')],
      ['label' => 'Vencidas', 'total' => $vencidas, 'icon' => 'alert', 'bg' => '#fdecea', 'fg' => '#d03b3b', 'href' => route('admin.reportes.index')],
      ['label' => 'Finalizadas', 'total' => $finalizadas, 'icon' => 'check', 'bg' => '#eef1f7', 'fg' => '#52514e', 'href' => route('admin.solicitudes.index', ['estado' => 'finalizada'])],
    ];
  @endphp

  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:12px; margin-bottom:18px;">
    @foreach ($stats as $stat)
      <a href="{{ $stat['href'] }}" class="card" style="text-decoration:none; color:inherit; display:block; margin-bottom:0;">
        <span style="display:flex; align-items:center; justify-content:center; width:34px; height:34px; border-radius:9px; background:{{ $stat['bg'] }}; color:{{ $stat['fg'] }}; margin-bottom:10px;">
          @include('admin.partials.icon', ['name' => $stat['icon'], 'size' => 17])
        </span>
        <div style="font-size:24px; font-weight:700; line-height:1;">{{ $stat['total'] }}</div>
        <div style="font-size:12.5px; color:#52514e; margin-top:6px;">{{ $stat['label'] }}</div>
      </a>
    @endforeach
  </div>

  <div style="display:flex; gap:14px; flex-wrap:wrap; align-items:flex-start;">
    <div class="card" style="flex:1.4; min-width:320px;">
      <h4 style="margin:0 0 12px; font-size:13px; color:#898781; text-transform:uppercase; letter-spacing:.03em;">
        Solicitudes por vencer (próximos días hábiles)
      </h4>
      @if ($solicitudesPorVencer->isEmpty())
        <p style="font-size:13px; color:#898781; margin:0;">No hay expedientes próximos a vencer.</p>
      @else
        <table style="width:100%; border-collapse:collapse; font-size:13.5px;">
          <thead>
            <tr style="text-align:left; border-bottom:1px solid #e1e0d9; color:#898781;">
              <th style="padding:6px 4px;">Código NS</th>
              <th style="padding:6px 4px;">Interesado</th>
              <th style="padding:6px 4px;">Estado</th>
              <th style="padding:6px 4px;">Días</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($solicitudesPorVencer as $s)
              <tr style="border-bottom:1px solid #f0efec;">
                <td style="padding:8px 4px;"><a href="{{ route('admin.solicitudes.show', $s) }}" style="color:#2a78d6; text-decoration:none; font-weight:600;">{{ $s->codigo_ns }}</a></td>
                <td style="padding:8px 4px;">{{ $s->solicitante?->nombre }}</td>
                <td style="padding:8px 4px;">@include('admin.partials.estado-badge', ['estado' => $s->estado])</td>
                <td style="padding:8px 4px; color:#a86a06; font-weight:600;">{{ $s->diasHabilesRestantes() }} días</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @endif
    </div>

    <div class="card" style="flex:1; min-width:280px;">
      <h4 style="margin:0 0 12px; font-size:13px; color:#898781; text-transform:uppercase; letter-spacing:.03em;">Actividad reciente</h4>
      @if ($actividadReciente->isEmpty())
        <p style="font-size:13px; color:#898781; margin:0;">Todavía no hay movimientos registrados.</p>
      @else
        <div style="position:relative; padding-left:16px; border-left:2px solid #f0efec;">
          @foreach ($actividadReciente as $item)
            <div style="position:relative; padding-bottom:14px;">
              <span style="position:absolute; left:-21px; top:3px; width:9px; height:9px; border-radius:50%; background:#2a78d6; border:2px solid #fff;"></span>
              <div style="font-size:12px; color:#898781;">
                {{ $item->created_at?->format('d/m/Y H:i') }} —
                {{ $item->tipo_actor === 'sistema' ? 'Sistema' : ($item->tipo_actor === 'ciudadano' ? 'Interesado' : ($item->user?->name ?? 'Administrador')) }}
                @if ($item->solicitud) · <a href="{{ route('admin.solicitudes.show', $item->solicitud) }}" style="color:#2a78d6; text-decoration:none;">{{ $item->solicitud->codigo_ns }}</a> @endif
              </div>
              <div style="font-size:13px; margin-top:2px;">{{ $item->descripcion }}</div>
            </div>
          @endforeach
        </div>
      @endif
    </div>
  </div>

  <div class="card" style="margin-top:14px;">
    <h4 style="margin:0 0 10px; font-size:13px; color:#898781; text-transform:uppercase; letter-spacing:.03em;">Tus permisos ({{ $permisos->count() }})</h4>
    <div style="display:flex; flex-wrap:wrap; gap:6px;">
      @forelse ($permisos as $clave)
        <span style="background:#eef1f7; color:#1c5cab; font-size:12px; padding:3px 9px; border-radius:999px;">{{ $clave }}</span>
      @empty
        <span style="color:#898781; font-size:13px;">Este rol no tiene permisos asignados.</span>
      @endforelse
    </div>
  </div>
@endsection

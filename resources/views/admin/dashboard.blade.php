@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
  <div class="card">
    <h2 style="margin-top:0;">Hola, {{ $user->name }}</h2>
    <p style="color:#52514e;">
      Rol: <strong>{{ $user->rol?->nombre ?? 'Sin rol asignado' }}</strong>
      @if ($user->dependencia) · Dependencia: <strong>{{ $user->dependencia->nombre }}</strong> @endif
    </p>
    <p style="color:#898781; font-size:13px;">
      Esta pantalla es la prueba de conexión de la Fase 3 (login + roles + permisos)
      con los datos reales de la Fase 2 — la conversión completa del prototipo HTML
      a estas rutas llega en las fases siguientes.
    </p>
  </div>

  <div class="card">
    <h3 style="margin-top:0;">Solicitudes por estado</h3>
    <p style="font-size:28px; font-weight:700; margin:0 0 14px;">{{ $totalSolicitudes }} <span style="font-size:13px; font-weight:400; color:#898781;">total</span></p>
    <table style="width:100%; border-collapse:collapse; font-size:13.5px;">
      <thead>
        <tr style="text-align:left; border-bottom:1px solid #e1e0d9; color:#898781;">
          <th style="padding:6px 4px;">Estado</th>
          <th style="padding:6px 4px;">Expedientes</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($porEstado as $estado)
          <tr style="border-bottom:1px solid #f0efec;">
            <td style="padding:8px 4px;">
              <span style="display:inline-block; width:9px; height:9px; border-radius:50%; background:{{ $estado->color }}; margin-right:8px;"></span>
              {{ $estado->etiqueta }}
            </td>
            <td style="padding:8px 4px;">{{ $estado->solicitudes_count }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <div class="card">
    <h3 style="margin-top:0;">Tus permisos ({{ $permisos->count() }})</h3>
    <div style="display:flex; flex-wrap:wrap; gap:6px;">
      @forelse ($permisos as $clave)
        <span style="background:#eef1f7; color:#1c5cab; font-size:12px; padding:3px 9px; border-radius:999px;">{{ $clave }}</span>
      @empty
        <span style="color:#898781; font-size:13px;">Este rol no tiene permisos asignados.</span>
      @endforelse
    </div>
  </div>
@endsection

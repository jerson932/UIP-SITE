@extends('layouts.admin')

@section('title', 'Solicitudes asignadas')
@section('pageTitle', 'Solicitudes asignadas')
@section('pageSubtitle', $dependencia?->nombre ?? 'Tu dependencia')

@section('content')
  <div class="card" style="margin-bottom:14px;">
    <p style="font-size:13px; color:#52514e; margin:0;">
      Aquí ves los expedientes que la Unidad de Información Pública asignó a
      <strong>{{ $dependencia?->nombre ?? 'tu dependencia' }}</strong> para que se busque la información solicitada.
      Puedes revisarlos, dejar una observación y adjuntar el documento con la respuesta — el resto del proceso
      (validar, finalizar, reasignar) lo maneja la UIP.
    </p>
  </div>

  <div class="card" style="padding:0; overflow-x:auto;">
    <table style="width:100%; border-collapse:collapse; font-size:13.5px;">
      <thead>
        <tr style="text-align:left; border-bottom:1px solid #e1e0d9; color:#898781;">
          <th style="padding:10px 14px;">Código NS</th>
          <th style="padding:10px 14px;">Fecha ingreso</th>
          <th style="padding:10px 14px;">Interesado</th>
          <th style="padding:10px 14px;">Asunto</th>
          <th style="padding:10px 14px;">Estado</th>
          <th style="padding:10px 14px;"></th>
        </tr>
      </thead>
      <tbody>
        @forelse ($solicitudes as $s)
          <tr style="border-bottom:1px solid #f0efec;">
            <td style="padding:10px 14px; font-weight:600;">{{ $s->codigo_ns }}</td>
            <td style="padding:10px 14px;">{{ $s->fecha_ingreso?->format('d/m/Y') }}</td>
            <td style="padding:10px 14px;">{{ $s->solicitante->nombre }}</td>
            <td style="padding:10px 14px; max-width:320px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $s->asunto }}</td>
            <td style="padding:10px 14px;">@include('admin.partials.estado-badge', ['estado' => $s->estado])</td>
            <td style="padding:10px 14px;">
              <a href="{{ route('admin.enlace.show', $s) }}" style="color:#2a78d6; font-weight:600; text-decoration:none;">Ver →</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="6" style="padding:24px; text-align:center; color:#898781;">Todavía no hay expedientes asignados a tu dependencia.</td></tr>
        @endforelse
      </tbody>
    </table>
    <div style="padding:14px 18px; border-top:1px solid #e1e0d9;">
      {{ $solicitudes->links() }}
    </div>
  </div>
@endsection

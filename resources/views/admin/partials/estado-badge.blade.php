{{-- Uso: @include('admin.partials.estado-badge', ['estado' => $solicitud->estado]) --}}
@if ($estado)
  <span style="display:inline-flex; align-items:center; gap:6px; background:{{ $estado->color }}1c; color:{{ $estado->color }}; font-size:12px; font-weight:600; padding:4px 10px; border-radius:999px;">
    <span style="width:7px; height:7px; border-radius:50%; background:{{ $estado->color }};"></span>
    {{ $estado->etiqueta }}
  </span>
@else
  <span style="color:#898781; font-size:12px;">Sin estado</span>
@endif

{{--
  Set compacto de íconos en línea (SVG, stroke=currentColor) para el sidebar,
  las tarjetas de estadísticas y la barra superior. Sin dependencias
  externas (sin CDN de íconos) para que la app siga funcionando sin acceso
  a internet, igual que el resto del sistema.

  Uso: @include('admin.partials.icon', ['name' => 'grid', 'size' => 18])
--}}
@php
  $size = $size ?? 18;
  $paths = [
    'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    'list' => '<path d="M8 6h13M8 12h13M8 18h13"/><circle cx="3.5" cy="6" r="1.4" fill="currentColor" stroke="none"/><circle cx="3.5" cy="12" r="1.4" fill="currentColor" stroke="none"/><circle cx="3.5" cy="18" r="1.4" fill="currentColor" stroke="none"/>',
    'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
    'chart' => '<path d="M4 20V10M11 20V4M18 20v-7"/><path d="M2 20h20"/>',
    'users' => '<circle cx="9" cy="8" r="3.2"/><path d="M2.5 20c0-3.6 2.9-6.2 6.5-6.2s6.5 2.6 6.5 6.2"/><circle cx="17.5" cy="8.5" r="2.6"/><path d="M15.7 13.9c2.9.3 5.3 2.6 5.3 6.1"/>',
    'gear' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 13.5a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6V20a2 2 0 1 1-4 0v-.2a1.7 1.7 0 0 0-1.1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.6-1H4a2 2 0 1 1 0-4h.2a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H10a1.7 1.7 0 0 0 1-1.6V4a2 2 0 1 1 4 0v.2a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V10c.1.7.6 1.3 1.6 1.4h.2a2 2 0 1 1 0 4h-.2a1.7 1.7 0 0 0-1.6 1z"/>',
    'eye' => '<path d="M1.5 12S5 5 12 5s10.5 7 10.5 7-3.5 7-10.5 7S1.5 12 1.5 12z"/><circle cx="12" cy="12" r="3"/>',
    'bell' => '<path d="M6 9a6 6 0 1 1 12 0c0 5 2 6 2 6H4s2-1 2-6z"/><path d="M9.5 19a2.5 2.5 0 0 0 5 0"/>',
    'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/>',
    'check' => '<path d="M20 6 9 17l-5-5"/>',
    'alert' => '<path d="M12 3 2 20h20L12 3z"/><path d="M12 10v4"/><circle cx="12" cy="17" r=".2" fill="currentColor"/>',
    'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
    'file' => '<path d="M6 2h9l5 5v15H6z"/><path d="M15 2v5h5"/>',
  ];
  $path = $paths[$name] ?? $paths['grid'];
@endphp
<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $path !!}</svg>

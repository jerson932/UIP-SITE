@extends('layouts.admin')

@section('title', 'Gestión de enlaces')
@section('pageTitle', 'Gestión de enlaces')
@section('pageSubtitle', 'Contactos de cada dependencia y su acceso al portal de enlace')

@section('content')

  <div class="card">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap;">
      <div>
        <h2 style="margin:0 0 4px;">Enlaces por dependencia</h2>
        <p style="color:#898781; font-size:13px; margin:0;">
          Un enlace es el contacto de una dependencia dentro del sistema. Puede tener solo datos de contacto,
          o además una cuenta propia para entrar a su portal (<code>/{{ 'login' }}</code>) y ver únicamente
          los expedientes asignados a su dependencia.
        </p>
      </div>
      <a href="{{ route('admin.enlaces.create') }}"
         style="align-self:flex-start; background:#2a78d6; color:#fff; text-decoration:none; border-radius:7px; padding:9px 16px; font-size:13.5px; font-weight:600; white-space:nowrap;">
        + Nuevo enlace
      </a>
    </div>

    <table style="width:100%; border-collapse:collapse; font-size:13.5px; margin-top:16px;">
      <thead>
        <tr style="text-align:left; border-bottom:1px solid #e1e0d9; color:#898781;">
          <th style="padding:6px 4px;">Nombre</th>
          <th style="padding:6px 4px;">Dependencia</th>
          <th style="padding:6px 4px;">Correo de contacto</th>
          <th style="padding:6px 4px;">Acceso al portal</th>
          <th style="padding:6px 4px;">Estado</th>
          <th style="padding:6px 4px;"></th>
        </tr>
      </thead>
      <tbody>
        @forelse ($enlaces as $e)
          <tr style="border-bottom:1px solid #f0efec;">
            <td style="padding:8px 4px;">{{ $e->nombre }}</td>
            <td style="padding:8px 4px;">{{ $e->dependencia?->nombre ?? '—' }}</td>
            <td style="padding:8px 4px;">{{ $e->correo ?? '—' }}</td>
            <td style="padding:8px 4px;">
              @if ($e->user)
                <span style="color:#0ca30c;">Sí — {{ $e->user->email }}</span>
              @else
                <span style="color:#898781;">Sin acceso todavía</span>
              @endif
            </td>
            <td style="padding:8px 4px;">
              @if ($e->activo)
                <span style="color:#0ca30c;">Activo</span>
              @else
                <span style="color:#d03b3b;">Inactivo</span>
              @endif
            </td>
            <td style="padding:8px 4px; text-align:right; white-space:nowrap;">
              <a href="{{ route('admin.enlaces.edit', $e) }}" style="color:#2a78d6; text-decoration:none; font-size:12.5px;">Editar</a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="6" style="padding:16px 4px; color:#898781;">Todavía no hay enlaces registrados.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
@endsection

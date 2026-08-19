@extends('layouts.admin')

@section('title', 'Usuarios')

@section('content')
  <div class="card">
    <h2 style="margin-top:0;">Usuarios del sistema</h2>
    <p style="color:#898781; font-size:13px; margin-top:-6px;">
      Esta pantalla solo es visible para roles con el permiso <code>usuarios.gestionar</code>
      (Administrador y Coordinador, según <code>PermissionSeeder</code>) — prueba de
      <code>CheckPermission</code> con datos reales.
    </p>
    <table style="width:100%; border-collapse:collapse; font-size:13.5px; margin-top:12px;">
      <thead>
        <tr style="text-align:left; border-bottom:1px solid #e1e0d9; color:#898781;">
          <th style="padding:6px 4px;">Nombre</th>
          <th style="padding:6px 4px;">Correo</th>
          <th style="padding:6px 4px;">Rol</th>
          <th style="padding:6px 4px;">Dependencia</th>
          <th style="padding:6px 4px;">Estado</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($usuarios as $u)
          <tr style="border-bottom:1px solid #f0efec;">
            <td style="padding:8px 4px;">{{ $u->name }}</td>
            <td style="padding:8px 4px;">{{ $u->email }}</td>
            <td style="padding:8px 4px;">{{ $u->rol?->nombre ?? '—' }}</td>
            <td style="padding:8px 4px;">{{ $u->dependencia?->nombre ?? '—' }}</td>
            <td style="padding:8px 4px;">
              @if ($u->activo)
                <span style="color:#0ca30c;">Activo</span>
              @else
                <span style="color:#d03b3b;">Inactivo</span>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endsection

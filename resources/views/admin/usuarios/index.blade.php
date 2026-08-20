@extends('layouts.admin')

@section('title', 'Usuarios')

@section('content')
  @if (session('status'))
    <div style="background:#e9f7ea; border:1px solid #b9e3bb; color:#256428; border-radius:8px; padding:10px 14px; font-size:13.5px; margin-bottom:14px;">{{ session('status') }}</div>
  @endif
  @if (session('error'))
    <div style="background:#fdecea; border:1px solid #f3c6c1; color:#8a2c22; border-radius:8px; padding:10px 14px; font-size:13.5px; margin-bottom:14px;">{{ session('error') }}</div>
  @endif

  <div class="card">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap;">
      <div>
        <h2 style="margin:0 0 4px;">Usuarios del sistema</h2>
        <p style="color:#898781; font-size:13px; margin:0;">
          Esta pantalla solo es visible para roles con el permiso <code>usuarios.gestionar</code>
          (Administrador y Coordinador, según <code>PermissionSeeder</code>).
        </p>
      </div>
      <a href="{{ route('admin.usuarios.create') }}"
         style="align-self:flex-start; background:#2a78d6; color:#fff; text-decoration:none; border-radius:7px; padding:9px 16px; font-size:13.5px; font-weight:600; white-space:nowrap;">
        + Nuevo usuario
      </a>
    </div>

    <table style="width:100%; border-collapse:collapse; font-size:13.5px; margin-top:16px;">
      <thead>
        <tr style="text-align:left; border-bottom:1px solid #e1e0d9; color:#898781;">
          <th style="padding:6px 4px;">Nombre</th>
          <th style="padding:6px 4px;">Correo</th>
          <th style="padding:6px 4px;">Rol</th>
          <th style="padding:6px 4px;">Dependencia</th>
          <th style="padding:6px 4px;">Estado</th>
          <th style="padding:6px 4px;"></th>
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
            <td style="padding:8px 4px; text-align:right; white-space:nowrap;">
              <a href="{{ route('admin.usuarios.edit', $u) }}" style="color:#2a78d6; text-decoration:none; font-size:12.5px; margin-right:12px;">Editar</a>
              @if ($u->id !== auth()->id())
                <form method="POST" action="{{ route('admin.usuarios.estado', $u) }}" style="display:inline;">
                  @csrf
                  <button type="submit"
                          style="background:none; border:0; padding:0; font-size:12.5px; cursor:pointer; color:{{ $u->activo ? '#d03b3b' : '#0ca30c' }};">
                    {{ $u->activo ? 'Desactivar' : 'Activar' }}
                  </button>
                </form>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endsection

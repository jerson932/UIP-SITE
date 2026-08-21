<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'Panel administrativo') — UIP MINGOB</title>
<style>
  :root{
    --navy-900:#0f1826;
    --navy-800:#16233a;
    --navy-700:#1c2c47;
    --accent:#2a78d6;
    --accent-soft:#eaf1fb;
    --line:#e1e0d9;
    --ink:#14181f;
    --ink-2:#52514e;
    --ink-3:#898781;
    --page-bg:#f4f5f7;
    --sidebar-w:252px;
    --topbar-h:64px;
  }
  *{box-sizing:border-box;}
  html,body{height:100%;}
  body{margin:0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background:var(--page-bg); color:var(--ink);}
  a{color:inherit;}

  .app-shell{display:flex; min-height:100vh;}

  /* ---------- Sidebar ---------- */
  .sidebar{
    width:var(--sidebar-w); flex:0 0 var(--sidebar-w); background:var(--navy-900); color:#cfd8ea;
    display:flex; flex-direction:column; position:sticky; top:0; height:100vh; overflow-y:auto;
  }
  .sidebar::-webkit-scrollbar{width:6px;}
  .sidebar::-webkit-scrollbar-thumb{background:var(--navy-700); border-radius:3px;}
  .sidebar-brand{
    display:flex; align-items:center; gap:10px; padding:20px 18px; border-bottom:1px solid rgba(255,255,255,.08);
  }
  .sidebar-brand .logo{
    width:34px; height:34px; border-radius:9px; background:var(--accent); color:#fff; font-weight:700; font-size:12.5px;
    display:flex; align-items:center; justify-content:center; flex:0 0 auto;
  }
  .sidebar-brand .name{font-weight:700; font-size:14.5px; color:#fff; line-height:1.2;}
  .sidebar-brand .sub{font-size:11px; color:#8291ab; line-height:1.2; margin-top:1px;}

  .sidebar-nav{padding:12px 10px 20px; flex:1;}
  .nav-section{margin-top:16px;}
  .nav-section:first-child{margin-top:2px;}
  .nav-section-label{
    font-size:10.5px; font-weight:700; letter-spacing:.07em; text-transform:uppercase;
    color:#5f6f8c; padding:6px 12px 6px;
  }
  .nav-link{
    display:flex; align-items:center; gap:10px; padding:9px 12px; border-radius:8px; margin:1px 0;
    font-size:13.5px; text-decoration:none; color:#c3ccdd; border-left:3px solid transparent;
  }
  .nav-link svg{flex:0 0 auto; opacity:.85;}
  .nav-link:hover{background:rgba(255,255,255,.06); color:#fff;}
  .nav-link.is-active{
    background:rgba(42,120,214,.18); color:#fff; border-left:3px solid var(--accent); font-weight:600;
  }
  .nav-link.is-active svg{opacity:1;}
  .nav-link.nav-sublink{padding-left:34px; font-size:13px;}

  /* ---------- Topbar ---------- */
  .content-col{flex:1; min-width:0; display:flex; flex-direction:column;}
  .topbar{
    height:var(--topbar-h); background:#fff; border-bottom:1px solid var(--line); padding:0 24px;
    display:flex; align-items:center; justify-content:space-between; gap:16px; position:sticky; top:0; z-index:5;
  }
  .topbar-title h1{font-size:17px; margin:0; line-height:1.25;}
  .topbar-title p{font-size:12.5px; color:var(--ink-3); margin:1px 0 0;}
  .topbar-actions{display:flex; align-items:center; gap:14px; flex:0 0 auto;}
  .topbar-btn{
    display:inline-flex; align-items:center; gap:6px; border:1px solid var(--line); background:#fff; color:var(--ink-2);
    border-radius:999px; padding:7px 14px; font-size:12.5px; text-decoration:none; font-weight:600; white-space:nowrap;
  }
  .topbar-btn:hover{border-color:#c7cede; color:var(--ink);}
  .icon-btn{
    display:flex; align-items:center; justify-content:center; width:36px; height:36px; border-radius:9px;
    color:var(--ink-2); background:#f4f5f7;
  }
  .topbar-user{display:flex; align-items:center; gap:10px; padding-left:14px; border-left:1px solid var(--line);}
  .topbar-user .avatar{
    width:34px; height:34px; border-radius:50%; background:var(--accent); color:#fff; font-weight:700; font-size:12.5px;
    display:flex; align-items:center; justify-content:center; flex:0 0 auto;
  }
  .topbar-user .who{line-height:1.2;}
  .topbar-user .who .name{font-size:13px; font-weight:600; color:var(--ink);}
  .topbar-user .who .role{font-size:11.5px; color:var(--ink-3);}
  .topbar-user form button{
    background:none; border:0; color:var(--ink-3); font-size:12px; cursor:pointer; padding:0; margin-top:1px; font-family:inherit;
  }
  .topbar-user form button:hover{color:#d03b3b;}

  main{padding:26px 24px 40px; max-width:1240px; margin:0 auto; width:100%;}
  .card{background:#fff; border:1px solid var(--line); border-radius:12px; padding:20px 22px; margin-bottom:18px;}

  @media (max-width: 880px){
    :root{ --sidebar-w:210px; }
    .topbar-title p{display:none;}
  }
</style>
</head>
<body>
@php
  $navUser = auth()->user();
  $iniciales = $navUser ? collect(explode(' ', trim($navUser->name)))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') : '';
@endphp
<div class="app-shell">
  <aside class="sidebar">
    <div class="sidebar-brand">
      <span class="logo">UIP</span>
      <div>
        <div class="name">UIP · MINGOB</div>
        <div class="sub">Información Pública</div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <a href="{{ route('admin.dashboard') }}" class="nav-link @if (request()->routeIs('admin.dashboard')) is-active @endif">
        @include('admin.partials.icon', ['name' => 'grid'])
        Dashboard
      </a>

      @if ($navUser?->hasPermission('solicitudes.ver'))
        <div class="nav-section">
          <div class="nav-section-label">Solicitudes</div>
          <a href="{{ route('admin.solicitudes.index') }}"
             class="nav-link @if (request()->routeIs('admin.solicitudes.index') && ! request('estado')) is-active @endif">
            @include('admin.partials.icon', ['name' => 'list'])
            Todas las solicitudes
          </a>
          <a href="{{ route('admin.solicitudes.index', ['estado' => 'pendiente_validacion']) }}"
             class="nav-link nav-sublink @if (request()->routeIs('admin.solicitudes.index') && request('estado') === 'pendiente_validacion') is-active @endif">
            Pendientes de validación
          </a>
          <a href="{{ route('admin.solicitudes.index', ['estado' => 'en_seguimiento']) }}"
             class="nav-link nav-sublink @if (request()->routeIs('admin.solicitudes.index') && request('estado') === 'en_seguimiento') is-active @endif">
            En seguimiento
          </a>
          <a href="{{ route('admin.solicitudes.index', ['estado' => 'finalizada']) }}"
             class="nav-link nav-sublink @if (request()->routeIs('admin.solicitudes.index') && request('estado') === 'finalizada') is-active @endif">
            Finalizadas
          </a>
          @if ($navUser?->hasPermission('solicitudes.crear'))
            <a href="{{ route('admin.solicitudes.create') }}" class="nav-link nav-sublink @if (request()->routeIs('admin.solicitudes.create')) is-active @endif">
              + Registrar solicitud
            </a>
          @endif
        </div>
      @endif

      @if ($navUser?->hasPermission('enlace.ver_asignadas'))
        <div class="nav-section">
          <div class="nav-section-label">Enlace</div>
          <a href="{{ route('admin.enlace.index') }}" class="nav-link @if (request()->routeIs('admin.enlace.*')) is-active @endif">
            @include('admin.partials.icon', ['name' => 'list'])
            Solicitudes asignadas
          </a>
        </div>
      @endif

      @if ($navUser?->hasPermission('reportes.exportar') || $navUser?->hasPermission('usuarios.gestionar') || $navUser?->hasPermission('configuracion.gestionar'))
        <div class="nav-section">
          <div class="nav-section-label">Administración</div>
          @if ($navUser?->hasPermission('reportes.exportar'))
            <a href="{{ route('admin.reportes.index') }}" class="nav-link @if (request()->routeIs('admin.reportes.*')) is-active @endif">
              @include('admin.partials.icon', ['name' => 'chart'])
              Reportes
            </a>
          @endif
          @if ($navUser?->hasPermission('usuarios.gestionar'))
            <a href="{{ route('admin.usuarios.index') }}" class="nav-link @if (request()->routeIs('admin.usuarios.*')) is-active @endif">
              @include('admin.partials.icon', ['name' => 'users'])
              Usuarios y permisos
            </a>
            <a href="{{ route('admin.enlaces.index') }}" class="nav-link @if (request()->routeIs('admin.enlaces.*')) is-active @endif">
              @include('admin.partials.icon', ['name' => 'link'])
              Gestión de enlaces
            </a>
          @endif
          @if ($navUser?->hasPermission('configuracion.gestionar'))
            <a href="{{ route('admin.configuracion.index') }}" class="nav-link @if (request()->routeIs('admin.configuracion.*')) is-active @endif">
              @include('admin.partials.icon', ['name' => 'gear'])
              Configuración
            </a>
          @endif
        </div>
      @endif
    </nav>
  </aside>

  <div class="content-col">
    <header class="topbar">
      <div class="topbar-title">
        <h1>@yield('pageTitle', 'Panel administrativo')</h1>
        @hasSection('pageSubtitle')
          <p>@yield('pageSubtitle')</p>
        @endif
      </div>
      <div class="topbar-actions">
        <a href="{{ route('ciudadano.seguimiento.form') }}" target="_blank" class="topbar-btn">
          @include('admin.partials.icon', ['name' => 'eye', 'size' => 15])
          Vista ciudadano
        </a>
        <span class="icon-btn" title="Notificaciones">@include('admin.partials.icon', ['name' => 'bell', 'size' => 17])</span>
        <span class="icon-btn" title="Correos">@include('admin.partials.icon', ['name' => 'mail', 'size' => 17])</span>
        @if ($navUser)
          <div class="topbar-user">
            <span class="avatar">{{ $iniciales ?: '?' }}</span>
            <div class="who">
              <div class="name">{{ $navUser->name }}</div>
              <div class="role">{{ $navUser->rol?->nombre ?? 'Sin rol' }}</div>
              <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Cerrar sesión</button>
              </form>
            </div>
          </div>
        @endif
      </div>
    </header>

    <main>
      @yield('content')
    </main>
  </div>
</div>
</body>
</html>

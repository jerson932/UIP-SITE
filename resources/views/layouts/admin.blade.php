<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'Panel administrativo') — UIP MINGOB</title>
<style>
  :root{ --navy-900:#0f1826; --accent:#2a78d6; --line:#e1e0d9; --ink-2:#52514e; }
  *{box-sizing:border-box;}
  body{margin:0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background:#f9f9f7; color:#0b0b0b;}
  header{
    background:var(--navy-900); color:#fff; padding:14px 24px; display:flex; align-items:center;
    justify-content:space-between; gap:16px;
  }
  header .brand{display:flex; align-items:center; gap:10px; font-weight:600;}
  header .brand .logo{width:30px; height:30px; border-radius:8px; background:var(--accent); display:flex; align-items:center; justify-content:center; font-size:12px;}
  header nav a{color:#cfd8ea; text-decoration:none; font-size:13.5px; margin-right:18px;}
  header nav a:hover{color:#fff;}
  header .user{display:flex; align-items:center; gap:12px; font-size:13px; color:#cfd8ea;}
  header form button{
    background:transparent; border:1px solid rgba(255,255,255,.25); color:#fff; border-radius:6px;
    padding:6px 12px; font-size:12.5px; cursor:pointer; font-family:inherit;
  }
  main{padding:28px 24px; max-width:1100px; margin:0 auto;}
  .card{background:#fff; border:1px solid var(--line); border-radius:12px; padding:20px 22px; margin-bottom:18px;}
</style>
</head>
<body>
<header>
  <div class="brand"><span class="logo">UIP</span> UIP MINGOB</div>
  <nav>
    <a href="{{ route('admin.dashboard') }}">Dashboard</a>
    @if ($user = auth()->user())
      @if ($user->hasPermission('solicitudes.ver'))
        <a href="{{ route('admin.solicitudes.index') }}">Solicitudes</a>
      @endif
      @if ($user->hasPermission('usuarios.gestionar'))
        <a href="{{ route('admin.usuarios.index') }}">Usuarios</a>
      @endif
      @if ($user->hasPermission('reportes.exportar'))
        <a href="{{ route('admin.reportes.index') }}">Reportes</a>
      @endif
    @endif
  </nav>
  <div class="user">
    {{ auth()->user()->name }} · {{ auth()->user()->rol?->nombre ?? 'Sin rol' }}
    <form method="POST" action="{{ route('logout') }}">
      @csrf
      <button type="submit">Cerrar sesión</button>
    </form>
  </div>
</header>
<main>
  @yield('content')
</main>
</body>
</html>

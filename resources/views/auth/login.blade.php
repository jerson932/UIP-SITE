<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Iniciar sesión — UIP MINGOB</title>
<style>
  :root{ --navy-900:#0f1826; --accent:#2a78d6; --accent-dark:#1c5cab; }
  *{box-sizing:border-box;}
  body{
    margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
    background:var(--navy-900); font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
  }
  .card{
    background:#fff; border-radius:14px; padding:36px 34px; width:100%; max-width:380px;
    box-shadow:0 20px 60px rgba(0,0,0,.35);
  }
  .logo{
    width:44px; height:44px; border-radius:10px; background:var(--accent);
    display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; margin-bottom:16px;
  }
  h1{font-size:19px; margin:0 0 4px; color:#0b0b0b;}
  p.sub{margin:0 0 24px; color:#52514e; font-size:13.5px;}
  label{display:block; font-size:13px; font-weight:600; color:#33322f; margin-bottom:6px;}
  input[type=email], input[type=password]{
    width:100%; padding:10px 12px; border:1px solid #d8d6cf; border-radius:8px; font-size:14px;
    margin-bottom:16px; font-family:inherit;
  }
  input:focus{outline:2px solid var(--accent); outline-offset:1px; border-color:var(--accent);}
  .row-check{display:flex; align-items:center; gap:8px; margin-bottom:20px; font-size:13px; color:#52514e;}
  button{
    width:100%; padding:11px; border:0; border-radius:8px; background:var(--accent); color:#fff;
    font-size:14.5px; font-weight:600; cursor:pointer; font-family:inherit;
  }
  button:hover{background:var(--accent-dark);}
  .errors{
    background:#fdecea; border:1px solid #f3c6c1; color:#8a2c22; border-radius:8px;
    padding:10px 12px; font-size:13px; margin-bottom:18px;
  }
  .errors ul{margin:0; padding-left:18px;}
  footer{margin-top:22px; font-size:11.5px; color:#898781; text-align:center;}
</style>
</head>
<body>
  <div class="card">
    <div class="logo">UIP</div>
    <h1>Unidad de Información Pública</h1>
    <p class="sub">Ministerio de Gobernación — panel administrativo</p>

    @if ($errors->any())
      <div class="errors">
        <ul>
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ route('login.store') }}">
      @csrf
      <label for="email">Correo institucional</label>
      <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>

      <label for="password">Contraseña</label>
      <input id="password" type="password" name="password" required>

      <label class="row-check">
        <input type="checkbox" name="remember"> Mantener sesión iniciada
      </label>

      <button type="submit">Ingresar</button>
    </form>
    <footer>Acceso restringido al personal autorizado de la UIP-MINGOB.</footer>
  </div>
</body>
</html>

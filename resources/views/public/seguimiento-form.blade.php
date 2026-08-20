<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Consulta tu expediente — UIP MINGOB</title>
<style>
  :root{ --navy-900:#0f1826; --accent:#2a78d6; --accent-dark:#1c5cab; }
  *{box-sizing:border-box;}
  body{
    margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
    background:var(--navy-900); font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
  }
  .card{
    background:#fff; border-radius:14px; padding:36px 34px; width:100%; max-width:420px;
    box-shadow:0 20px 60px rgba(0,0,0,.35);
  }
  .logo{
    width:44px; height:44px; border-radius:10px; background:var(--accent);
    display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; margin-bottom:16px;
  }
  h1{font-size:19px; margin:0 0 4px; color:#0b0b0b;}
  p.sub{margin:0 0 24px; color:#52514e; font-size:13.5px; line-height:1.5;}
  label{display:block; font-size:13px; font-weight:600; color:#33322f; margin-bottom:6px;}
  input[type=text]{
    width:100%; padding:10px 12px; border:1px solid #d8d6cf; border-radius:8px; font-size:14px;
    margin-bottom:16px; font-family:inherit; text-transform:uppercase;
  }
  input:focus{outline:2px solid var(--accent); outline-offset:1px; border-color:var(--accent);}
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
  footer{margin-top:22px; font-size:11.5px; color:#898781; text-align:center; line-height:1.5;}
  footer a{color:#898781;}
</style>
</head>
<body>
  <div class="card">
    <div class="logo">UIP</div>
    <h1>Consulta tu expediente</h1>
    <p class="sub">Ingresa el código de acceso que te entregamos al recibir tu solicitud de información para ver su estado.</p>

    @if ($errors->any())
      <div class="errors">
        <ul>
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ route('ciudadano.seguimiento.consultar') }}">
      @csrf
      <label for="codigo_acceso">Código de acceso</label>
      <input id="codigo_acceso" type="text" name="codigo_acceso" value="{{ old('codigo_acceso') }}" placeholder="ej. A8K4-XP29" required autofocus>

      <button type="submit">Consultar</button>
    </form>
    <footer>
      Unidad de Información Pública — Ministerio de Gobernación<br>
      ¿Aún no tienes una solicitud? <a href="{{ route('solicitudes.nueva.form') }}">Presenta una aquí</a>.<br>
      ¿Trabajas en la UIP? <a href="{{ route('login') }}">Ingresa al panel administrativo</a>.
    </footer>
  </div>
</body>
</html>

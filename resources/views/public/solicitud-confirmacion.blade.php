<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Solicitud enviada — UIP MINGOB</title>
<style>
  :root{ --navy-900:#0f1826; --accent:#2a78d6; --accent-dark:#1c5cab; --ink-2:#52514e; }
  *{box-sizing:border-box;}
  body{
    margin:0; min-height:100vh; padding:32px 16px; display:flex; align-items:center; justify-content:center;
    background:var(--navy-900); font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
  }
  .card{
    background:#fff; border-radius:14px; padding:36px 34px; width:100%; max-width:460px;
    box-shadow:0 20px 60px rgba(0,0,0,.35); text-align:center;
  }
  .check{
    width:52px; height:52px; border-radius:50%; background:#e9f7ea; color:#0ca30c; font-size:26px;
    display:flex; align-items:center; justify-content:center; margin:0 auto 16px;
  }
  h1{font-size:19px; margin:0 0 4px; color:#0b0b0b;}
  p.sub{margin:0 0 22px; color:var(--ink-2); font-size:13.5px; line-height:1.5;}
  .codigo-box{
    background:#f4f7fb; border:1px solid #dbe6f2; border-radius:10px; padding:18px; margin-bottom:18px; text-align:left;
  }
  .codigo-box .etiqueta{font-size:11.5px; color:#898781; text-transform:uppercase; letter-spacing:.03em; margin-bottom:2px;}
  .codigo-box .valor{font-size:20px; font-weight:700; color:var(--navy-900); letter-spacing:.02em; margin-bottom:12px;}
  .codigo-box .valor:last-child{margin-bottom:0;}
  .aviso{
    background:#fff8e6; border:1px solid #f0dfa8; color:#7a5a00; border-radius:8px; padding:10px 12px;
    font-size:12.5px; text-align:left; margin-bottom:22px; line-height:1.5;
  }
  a.boton{
    display:block; padding:11px; border-radius:8px; background:var(--accent); color:#fff; text-decoration:none;
    font-size:14.5px; font-weight:600;
  }
  a.boton:hover{background:var(--accent-dark);}
  footer{margin-top:22px; font-size:11.5px; color:#898781; text-align:center; line-height:1.5;}
  footer a{color:#898781;}
</style>
</head>
<body>
  <div class="card">
    <div class="check">✓</div>
    <h1>Tu solicitud fue registrada</h1>
    <p class="sub">Guarda esta información — la necesitarás para dar seguimiento a tu trámite.</p>

    <div class="codigo-box">
      <div class="etiqueta">Código de expediente</div>
      <div class="valor">{{ $solicitud->codigo_ns }}</div>
      <div class="etiqueta">Código de acceso</div>
      <div class="valor">{{ $solicitud->codigo_acceso }}</div>
    </div>

    @if ($correoEnviado)
      <div class="aviso">También te enviamos esta información a tu correo electrónico.</div>
    @else
      <div class="aviso">No pudimos confirmar el envío del correo con esta información — por favor guarda o imprime esta página antes de salir, ya que el código de acceso no se muestra de nuevo.</div>
    @endif

    <a class="boton" href="{{ route('ciudadano.seguimiento.form') }}">Consultar el estado de mi solicitud</a>

    <footer>
      Unidad de Información Pública — Ministerio de Gobernación<br>
      <a href="{{ route('solicitudes.nueva.form') }}">Presentar otra solicitud</a>
    </footer>
  </div>
</body>
</html>

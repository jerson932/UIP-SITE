<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Presentar una solicitud — UIP MINGOB</title>
<style>
  :root{ --navy-900:#0f1826; --accent:#2a78d6; --accent-dark:#1c5cab; --line:#e1e0d9; --ink-2:#52514e; }
  *{box-sizing:border-box;}
  body{
    margin:0; min-height:100vh; padding:32px 16px; display:flex; align-items:flex-start; justify-content:center;
    background:var(--navy-900); font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
  }
  .card{
    background:#fff; border-radius:14px; padding:36px 34px; width:100%; max-width:560px;
    box-shadow:0 20px 60px rgba(0,0,0,.35);
  }
  .logo{
    width:44px; height:44px; border-radius:10px; background:var(--accent);
    display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; margin-bottom:16px;
  }
  h1{font-size:19px; margin:0 0 4px; color:#0b0b0b;}
  p.sub{margin:0 0 22px; color:var(--ink-2); font-size:13.5px; line-height:1.5;}
  label{display:block; font-size:13px; font-weight:600; color:#33322f; margin-bottom:6px;}
  .campo{margin-bottom:16px;}
  input[type=text], input[type=email], input[type=tel], select, textarea{
    width:100%; padding:10px 12px; border:1px solid #d8d6cf; border-radius:8px; font-size:14px;
    font-family:inherit;
  }
  textarea{resize:vertical;}
  input:focus, select:focus, textarea:focus{outline:2px solid var(--accent); outline-offset:1px; border-color:var(--accent);}
  .fila{display:flex; gap:12px;}
  .fila > div{flex:1;}
  button{
    width:100%; padding:11px; border:0; border-radius:8px; background:var(--accent); color:#fff;
    font-size:14.5px; font-weight:600; cursor:pointer; font-family:inherit; margin-top:6px;
  }
  button:hover{background:var(--accent-dark);}
  .errors{
    background:#fdecea; border:1px solid #f3c6c1; color:#8a2c22; border-radius:8px;
    padding:10px 12px; font-size:13px; margin-bottom:18px;
  }
  .errors ul{margin:0; padding-left:18px;}
  .nota{font-size:12px; color:var(--ink-2); margin-top:4px;}
  footer{margin-top:22px; font-size:11.5px; color:#898781; text-align:center; line-height:1.5;}
  footer a{color:#898781;}
</style>
</head>
<body>
  <div class="card">
    <div class="logo">UIP</div>
    <h1>Presentar una solicitud de información</h1>
    <p class="sub">Completa este formulario para solicitar información pública a la institución. Al enviarlo recibirás un código de acceso para dar seguimiento a tu trámite.</p>

    @if ($errors->any())
      <div class="errors">
        <ul>
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ route('solicitudes.nueva.store') }}">
      @csrf

      <div class="campo">
        <label for="nombre">Nombre completo</label>
        <input id="nombre" type="text" name="nombre" value="{{ old('nombre') }}" required>
      </div>

      <div class="fila">
        <div class="campo">
          <label for="correo">Correo electrónico</label>
          <input id="correo" type="email" name="correo" value="{{ old('correo') }}" required>
          <p class="nota">Aquí te enviaremos tu código de acceso.</p>
        </div>
        <div class="campo">
          <label for="telefono">Teléfono (opcional)</label>
          <input id="telefono" type="tel" name="telefono" value="{{ old('telefono') }}">
        </div>
      </div>

      <div class="fila">
        <div class="campo">
          <label for="genero">Género (opcional)</label>
          <select id="genero" name="genero">
            <option value="">Prefiero no indicar</option>
            @foreach ($generos as $g)
              <option value="{{ $g }}" @selected(old('genero') === $g)>{{ $g }}</option>
            @endforeach
          </select>
        </div>
        <div class="campo">
          <label for="rango_edad">Rango de edad (opcional)</label>
          <select id="rango_edad" name="rango_edad">
            <option value="">No indicar</option>
            @foreach ($rangosEdad as $r)
              <option value="{{ $r }}" @selected(old('rango_edad') === $r)>{{ $r }}</option>
            @endforeach
          </select>
        </div>
      </div>

      <div class="campo">
        <label for="departamento">Departamento (opcional)</label>
        <select id="departamento" name="departamento">
          <option value="">No indicar</option>
          @foreach ($departamentos as $d)
            <option value="{{ $d }}" @selected(old('departamento') === $d)>{{ $d }}</option>
          @endforeach
        </select>
      </div>

      <div class="campo">
        <label for="asunto">¿Qué información solicitas?</label>
        <textarea id="asunto" name="asunto" rows="5" required minlength="10">{{ old('asunto') }}</textarea>
      </div>

      <button type="submit">Enviar solicitud</button>
    </form>

    <footer>
      Unidad de Información Pública — Ministerio de Gobernación<br>
      ¿Ya tienes una solicitud? <a href="{{ route('ciudadano.seguimiento.form') }}">Consulta su estado aquí</a>.
      ¿Trabajas en la UIP? <a href="{{ route('login') }}">Ingresa al panel administrativo</a>.
    </footer>
  </div>
</body>
</html>

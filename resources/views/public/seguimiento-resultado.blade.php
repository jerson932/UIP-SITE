<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Expediente {{ $solicitud->codigo_ns }} — UIP MINGOB</title>
<style>
  :root{ --navy-900:#0f1826; --accent:#2a78d6; --line:#e1e0d9; --ink-2:#52514e; }
  *{box-sizing:border-box;}
  body{margin:0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background:#f9f9f7; color:#0b0b0b;}
  header{background:var(--navy-900); color:#fff; padding:14px 24px; display:flex; align-items:center; gap:10px; font-weight:600;}
  header .logo{width:30px; height:30px; border-radius:8px; background:var(--accent); display:flex; align-items:center; justify-content:center; font-size:12px;}
  main{padding:28px 24px; max-width:640px; margin:0 auto;}
  .card{background:#fff; border:1px solid var(--line); border-radius:12px; padding:20px 22px; margin-bottom:18px;}
  h4{margin:0 0 10px; font-size:13px; color:#898781; text-transform:uppercase; letter-spacing:.03em;}
  .field{font-size:13.5px; margin-bottom:10px;}
  .field span{color:#898781; display:block; font-size:12px; margin-bottom:2px;}
  a.volver{font-size:13px; color:#52514e; text-decoration:none;}
  .timeline-item{display:flex; gap:10px; padding:9px 0; border-bottom:1px solid #f0efec; font-size:13px;}
  .timeline-item .dot{width:8px; height:8px; border-radius:50%; background:#2a78d6; margin-top:4px; flex:0 0 auto;}
  .timeline-item .fecha{color:#898781; font-size:12px; margin-top:2px;}
  .doc-item{display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f0efec; padding:9px 0; font-size:13px;}
  .doc-item a{color:#2a78d6; text-decoration:none; font-size:12.5px;}
  details.autoservicio{margin-top:14px; border-top:1px solid #f0efec; padding-top:12px;}
  details.autoservicio summary{cursor:pointer; font-size:13px; color:#2a78d6; font-weight:600;}
  details.autoservicio textarea{width:100%; padding:9px 10px; border:1px solid #d8d6cf; border-radius:8px; font-size:13.5px; font-family:inherit; resize:vertical; margin:10px 0;}
  details.autoservicio button{padding:8px 16px; border:0; border-radius:8px; background:#2a78d6; color:#fff; font-size:13px; cursor:pointer;}
</style>
</head>
<body>
<header><span class="logo">UIP</span> UIP MINGOB — Portal del ciudadano</header>
<main>
  <a class="volver" href="{{ route('ciudadano.seguimiento.form') }}">← Consultar otro expediente</a>

  @if ($status ?? session('status'))
    <div style="background:#e9f7ea; border:1px solid #b9e3bb; color:#256428; border-radius:8px; padding:10px 14px; font-size:13.5px; margin:14px 0 0;">{{ $status ?? session('status') }}</div>
  @endif
  @if ($error ?? null)
    <div style="background:#fdecea; border:1px solid #f3c6c1; color:#8a2c22; border-radius:8px; padding:10px 14px; font-size:13.5px; margin:14px 0 0;">{{ $error }}</div>
  @endif

  <div style="display:flex; justify-content:space-between; align-items:flex-start; margin:16px 0 18px; gap:16px; flex-wrap:wrap;">
    <div>
      <h2 style="margin:0 0 2px;">Expediente {{ $solicitud->codigo_ns }}</h2>
      <p style="margin:0; color:var(--ink-2); font-size:13.5px;">
        {{ $solicitud->contrasena ? 'Contraseña No. '.$solicitud->contrasena : 'Aún sin contraseña asignada' }}
      </p>
    </div>
    @include('admin.partials.estado-badge', ['estado' => $solicitud->estado])
  </div>

  <div class="card">
    <h4>Fechas</h4>
    <div class="field"><span>Fecha de ingreso</span>{{ $solicitud->fecha_ingreso?->format('d/m/Y') }}</div>
    <div class="field"><span>Fecha de vencimiento</span>{{ $solicitud->fecha_vencimiento?->format('d/m/Y') ?? 'Pendiente de asignar' }}</div>
    @if ($dias !== null)
      <div class="field">
        <span>Días restantes</span>
        <strong style="color:{{ $dias < 0 ? '#d03b3b' : ($dias <= 2 ? '#a86a06' : '#0ca30c') }};">
          @if ($dias < 0) {{ abs($dias) }} días vencida @else {{ $dias }} días hábiles @endif
        </strong>
      </div>
    @endif
  </div>

  <div class="card">
    <h4>Documentos disponibles</h4>
    @if ($solicitud->fecha_finalizacion)
      <p style="font-size:12px; color:#a86a06; background:#fff8e6; border:1px solid #f0dfa8; border-radius:8px; padding:8px 10px; margin:0 0 12px;">
        Este expediente fue finalizado. Puedes consultarlo y descargar tus documentos hasta el
        <strong>{{ $solicitud->fechaLimiteAccesoPortal()?->format('d/m/Y') }}</strong> (10 días hábiles después de la finalización).
      </p>
    @endif
    @forelse ($documentos as $doc)
      <div class="doc-item">
        <div>{{ $doc->nombre }}</div>
        <a href="{{ $doc->url_descarga }}">Descargar</a>
      </div>
    @empty
      <p style="font-size:13px; color:var(--ink-2); margin:0;">Todavía no hay documentos publicados para este expediente.</p>
    @endforelse
  </div>

  <div class="card">
    <h4>Seguimiento</h4>
    @forelse ($solicitud->solicitud_historial as $h)
      <div class="timeline-item">
        <span class="dot"></span>
        <div>
          <div>{{ $h->descripcion }}</div>
          <div class="fecha">{{ $h->created_at?->format('d/m/Y H:i') }}</div>
        </div>
      </div>
    @empty
      <p style="font-size:13px; color:var(--ink-2); margin:0;">Aún no hay movimientos registrados.</p>
    @endforelse

    @if ($solicitud->estado?->clave !== 'pendiente_validacion')
      <details class="autoservicio">
        <summary>¿No estás de acuerdo con la respuesta? Presenta un recurso de revisión</summary>
        <form method="POST" action="{{ route('ciudadano.recurso.solicitar') }}">
          @csrf
          <input type="hidden" name="codigo_acceso" value="{{ $solicitud->codigo_acceso }}">
          <textarea name="motivo" rows="4" required minlength="10" placeholder="Explica el motivo de tu recurso de revisión..."></textarea>
          <button type="submit">Presentar recurso de revisión</button>
        </form>
      </details>
    @endif

    <details class="autoservicio">
      <summary>¿Necesitas pedir una ampliación de la información?</summary>
      <form method="POST" action="{{ route('ciudadano.ampliacion.solicitar') }}">
        @csrf
        <input type="hidden" name="codigo_acceso" value="{{ $solicitud->codigo_acceso }}">
        <textarea name="descripcion" rows="4" required minlength="10" placeholder="Describe la ampliación que necesitas..."></textarea>
        <button type="submit">Solicitar ampliación</button>
      </form>
    </details>
  </div>
</main>
</body>
</html>

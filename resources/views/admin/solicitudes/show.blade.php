@extends('layouts.admin')

@section('title', 'Expediente '.$solicitud->codigo_ns)
@section('pageTitle', 'Expediente '.$solicitud->codigo_ns)
@section('pageSubtitle', 'Detalle y seguimiento de la solicitud')

@php
  $dias = $solicitud->diasHabilesRestantes();
  $puedeContrasena = $solicitud->puedeAsignarContrasena();
  $estadoClave = $solicitud->estado?->clave;
  $tabs = [
    ['key' => 'seguimiento', 'label' => 'Seguimiento'],
    ['key' => 'prorroga', 'label' => 'Prórroga', 'has' => $solicitud->prorrogas->isNotEmpty()],
    ['key' => 'recurso', 'label' => 'Recurso de Revisión', 'has' => $solicitud->recursos_revision->isNotEmpty()],
    ['key' => 'aclaracion', 'label' => 'Aclaración', 'has' => $solicitud->aclaraciones->isNotEmpty()],
    ['key' => 'ampliacion', 'label' => 'Ampliación', 'has' => $solicitud->ampliaciones->isNotEmpty()],
    ['key' => 'documentos', 'label' => 'Documentos ('.$solicitud->documentos->count().')'],
    ['key' => 'correos', 'label' => 'Correos ('.($solicitud->correos_enviados->count() + $solicitud->correos_recibidos->count()).')'],
    ['key' => 'historial', 'label' => 'Historial ('.$solicitud->solicitud_historial->count().')'],
  ];
@endphp

@section('content')
  <a href="{{ route('admin.solicitudes.index') }}" style="font-size:13px; color:#52514e; text-decoration:none;">← Volver al listado</a>

  @if (session('status'))
    <div style="background:#e9f7ea; border:1px solid #b9e3bb; color:#256428; border-radius:8px; padding:10px 14px; font-size:13.5px; margin:12px 0;">{{ session('status') }}</div>
  @endif
  @if (session('error'))
    <div style="background:#fdecea; border:1px solid #f3c6c1; color:#8a2c22; border-radius:8px; padding:10px 14px; font-size:13.5px; margin:12px 0;">{{ session('error') }}</div>
  @endif
  @if ($errors->any())
    <div style="background:#fdecea; border:1px solid #f3c6c1; color:#8a2c22; border-radius:8px; padding:10px 14px; font-size:13.5px; margin:12px 0;">
      <ul style="margin:0; padding-left:18px;">@foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul>
    </div>
  @endif

  <div style="display:flex; justify-content:space-between; align-items:flex-start; margin:12px 0 6px; gap:16px; flex-wrap:wrap;">
    <div>
      <h2 style="margin:0 0 2px;">Expediente {{ $solicitud->codigo_ns }}</h2>
      <p style="margin:0; color:#52514e; font-size:13.5px;">
        {{ $solicitud->contrasena ? 'Contraseña No. '.$solicitud->contrasena : 'Sin contraseña asignada' }}
      </p>
    </div>
    @include('admin.partials.estado-badge', ['estado' => $solicitud->estado])
  </div>

  <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:18px;">
    @if ($estadoClave === 'pendiente_validacion' && auth()->user()->hasPermission('solicitudes.validar'))
      <form method="POST" action="{{ route('admin.solicitudes.aceptar', $solicitud) }}">
        @csrf
        <button type="submit" style="background:#0ca30c; color:#fff; border:0; border-radius:7px; padding:8px 16px; font-size:13px; font-weight:600; cursor:pointer;">Aceptar solicitud</button>
      </form>
      <form method="POST" action="{{ route('admin.solicitudes.rechazar', $solicitud) }}" onsubmit="return confirm('¿Rechazar esta solicitud?');">
        @csrf
        <button type="submit" style="background:#d03b3b; color:#fff; border:0; border-radius:7px; padding:8px 16px; font-size:13px; font-weight:600; cursor:pointer;">Rechazar solicitud</button>
      </form>
    @elseif (! in_array($estadoClave, ['finalizada', 'rechazada']) && auth()->user()->hasPermission('solicitudes.finalizar'))
      <a href="?tab=seguimiento#notificacion-resolucion"
         style="background:{{ $solicitud->contrasena ? '#0ca30c' : '#c3c2b7' }}; color:#fff; border-radius:7px; padding:8px 16px; font-size:13px; font-weight:600; text-decoration:none; pointer-events:{{ $solicitud->contrasena ? 'auto' : 'none' }};">
        Finalizar expediente
      </a>
    @endif
    <a href="?tab=historial" style="align-self:center; font-size:13px; color:#2a78d6; text-decoration:none;">Ver historial</a>
    <a href="?tab=documentos" style="align-self:center; font-size:13px; color:#2a78d6; text-decoration:none;">Ver documentos</a>
    <a href="?tab=correos" style="align-self:center; font-size:13px; color:#2a78d6; text-decoration:none;">Ver correos</a>
  </div>

  <div style="display:grid; grid-template-columns:minmax(0,380px) minmax(0,1fr); gap:18px; align-items:start;">
    <div style="display:flex; flex-direction:column; gap:16px;">

      <div class="card">
        <h4 style="margin:0 0 10px; font-size:13px; color:#898781; text-transform:uppercase; letter-spacing:.03em;">Descripción de la solicitud</h4>
        <p style="font-size:13.2px; margin:0 0 14px;">{{ $solicitud->asunto }}</p>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px 16px; font-size:13px;">
          <div><span style="color:#898781;">Género</span><br>{{ $solicitud->solicitante->genero ?? '—' }}</div>
          <div><span style="color:#898781;">Rango de edad</span><br>{{ $solicitud->solicitante->rango_edad ?? '—' }}</div>
          <div><span style="color:#898781;">País</span><br>{{ $solicitud->solicitante->pais ?? '—' }}</div>
          <div><span style="color:#898781;">Departamento</span><br>{{ $solicitud->solicitante->departamento ?? '—' }}</div>
        </div>
        <div style="font-size:13px; margin-top:10px;"><span style="color:#898781;">Medio para recibir la información</span><br>{{ ucfirst($solicitud->medio_recepcion) }}</div>
      </div>

      <div class="card">
        <h4 style="margin:0 0 10px; font-size:13px; color:#898781; text-transform:uppercase; letter-spacing:.03em;">Información del interesado</h4>
        <div style="font-size:13px; display:flex; flex-direction:column; gap:6px;">
          <div><span style="color:#898781;">Nombre</span><br>{{ $solicitud->solicitante->nombre }}</div>
          <div><span style="color:#898781;">Correo</span><br>{{ $solicitud->solicitante->correo ?? '—' }}</div>
          <div><span style="color:#898781;">Teléfono</span><br>{{ $solicitud->solicitante->telefono ?? '—' }}</div>
          <div><span style="color:#898781;">Fecha de ingreso</span><br>{{ $solicitud->fecha_ingreso?->format('d/m/Y') }}</div>
          <div><span style="color:#898781;">Código de acceso</span><br>{{ $solicitud->codigo_acceso }}</div>
        </div>
      </div>

      <div class="card">
        <h4 style="margin:0 0 10px; font-size:13px; color:#898781; text-transform:uppercase; letter-spacing:.03em;">Validación inicial</h4>
        <div style="font-size:13px; display:flex; flex-direction:column; gap:6px;">
          <div><span style="color:#898781;">¿Información pública?</span><br>{{ ucfirst($solicitud->es_informacion_publica) }}</div>
          <div><span style="color:#898781;">¿Competencia de la institución?</span><br>{{ ucfirst($solicitud->es_competencia) }}</div>
          <div><span style="color:#898781;">¿Requiere aclaración?</span><br>{{ $solicitud->requiere_aclaracion ? 'Sí' : 'No' }}</div>
        </div>
        @if ($estadoClave !== 'pendiente_validacion')
          <p style="font-size:12px; color:#898781; margin:10px 0 0;">Decisión registrada — no se puede modificar sin dejar evidencia en el historial.</p>
        @endif
      </div>

      <div class="card">
        <h4 style="margin:0 0 10px; font-size:13px; color:#898781; text-transform:uppercase; letter-spacing:.03em;">Contraseña</h4>
        @if (! $puedeContrasena)
          <p style="font-size:13px; color:#898781; margin:0;">Disponible una vez se acepte la solicitud como información pública — hasta entonces no puede asignarse contraseña.</p>
        @elseif ($solicitud->contrasena)
          <div style="font-size:13px;"><span style="color:#898781;">Contraseña</span><br><strong>No. {{ $solicitud->contrasena }}</strong></div>
          <p style="font-size:12px; color:#898781; margin:8px 0 0;">Asignada manualmente por el administrador. Plazo: 10 días hábiles para resolver (prórroga puede solicitarse hasta el 8vo día).</p>
        @elseif (auth()->user()->hasPermission('solicitudes.asignar_contrasena'))
          <form method="POST" action="{{ route('admin.solicitudes.contrasena', $solicitud) }}" style="display:flex; gap:8px;">
            @csrf
            <input name="contrasena" placeholder="ej. 1631-2026" required
                   style="flex:1; padding:8px 10px; border:1px solid #d8d6cf; border-radius:7px; font-size:13px;">
            <button type="submit" style="background:#2a78d6; color:#fff; border:0; border-radius:7px; padding:8px 14px; font-size:13px; font-weight:600; cursor:pointer;">Guardar</button>
          </form>
          <p style="font-size:12px; color:#898781; margin:8px 0 0;">Código del sistema: <strong>{{ $solicitud->codigo_ns }}</strong> (no confundir con la contraseña). Al guardar se registra el plazo de 10 días hábiles.</p>
        @endif
      </div>

      <div class="card">
        <h4 style="margin:0 0 10px; font-size:13px; color:#898781; text-transform:uppercase; letter-spacing:.03em;">Dependencia y enlace</h4>
        @if ($solicitud->dependencia)
          <div style="font-size:13px;"><span style="color:#898781;">Dependencia</span><br>{{ $solicitud->dependencia->nombre }}</div>
          <div style="font-size:13px; margin-top:6px;"><span style="color:#898781;">Enlace responsable</span><br>{{ $solicitud->enlace->nombre ?? '—' }}</div>
          <p style="font-size:11.5px; color:#898781; margin:10px 0 0;">
            La asignación se hace automáticamente al generar un Oficio o Providencia hacia una dependencia
            (más abajo, "Oficios y providencias") — no hay un formulario manual por separado.
          </p>
        @else
          <p style="font-size:13px; color:#898781; margin:0;">
            Aún no se ha asignado dependencia. Se asigna automáticamente al generar el primer Oficio o
            Providencia hacia una dependencia (más abajo, "Oficios y providencias").
          </p>
        @endif
      </div>

      @if (auth()->user()->hasPermission('solicitudes.generar_documento'))
        @php
          $documentosOficiales = $solicitud->documentos->whereNotNull('plantilla_id')->sortByDesc('created_at');
        @endphp
        <div class="card">
          <h4 style="margin:0 0 10px; font-size:13px; color:#898781; text-transform:uppercase; letter-spacing:.03em;">Oficios y providencias</h4>
          <p style="font-size:12px; color:#898781; margin:0 0 10px;">
            Un mismo expediente puede generar varios, hacia distintas dependencias — cada uno queda guardado por separado, sin reemplazar al anterior.
          </p>

          @forelse ($documentosOficiales as $doc)
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f0efec; padding:7px 0; font-size:12.5px; gap:8px;">
              <div>
                <div>{{ $doc->nombre }}@if ($doc->no_oficio) (No. {{ $doc->no_oficio }}) @elseif ($doc->no_providencia) (No. {{ $doc->no_providencia }}) @endif</div>
                <div style="color:#898781; font-size:11.5px;">{{ $doc->created_at?->format('d/m/Y H:i') }}</div>
              </div>
              <a href="{{ route('admin.solicitudes.documentos.descargar', [$solicitud, $doc]) }}" style="color:#2a78d6; text-decoration:none; flex:0 0 auto;">Descargar</a>
            </div>
          @empty
            <p style="font-size:13px; color:#898781; margin:0 0 10px;">Todavía no se ha generado ningún oficio o providencia.</p>
          @endforelse

          <form method="POST" action="{{ route('admin.solicitudes.documento_oficial', $solicitud) }}" style="display:flex; flex-direction:column; gap:8px; margin-top:12px; padding-top:12px; border-top:1px solid #f0efec;">
            @csrf
            <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">Dependencia a la que se dirige</label>
            <select name="dependencia_id" id="doc-oficial-dependencia" required style="padding:8px 10px; border:1px solid #d8d6cf; border-radius:7px; font-size:13px;">
              <option value="">Selecciona dependencia…</option>
              @foreach ($dependencias as $dep)
                <option value="{{ $dep->id }}" data-tipo="{{ str_starts_with((string) $dep->plantilla_clave, 'oficio_') ? 'oficio' : 'providencia' }}">{{ $dep->nombre }}</option>
              @endforeach
            </select>
            <div style="display:flex; gap:8px;">
              <div style="flex:1;">
                <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">RC</label>
                <input name="rc" value="{{ $solicitud->rc }}" placeholder="ej. 1234"
                       style="width:100%; padding:8px 10px; border:1px solid #d8d6cf; border-radius:7px; font-size:13px;">
              </div>
              <div style="flex:1;">
                <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">FOLIO</label>
                @if ($solicitud->folio)
                  {{-- El folio solo se asigna una vez, en el primer oficio/providencia del
                       expediente — ya no se puede cambiar desde aquí (DocumentoOficialService::generar()
                       lo ignora si ya hay uno guardado, esto solo lo deja claro en la pantalla). --}}
                  <input value="{{ $solicitud->folio }}" disabled
                         style="width:100%; padding:8px 10px; border:1px solid #e1e0d9; border-radius:7px; font-size:13px; background:#f4f5f7; color:#52514e;">
                @else
                  <input name="folio" placeholder="ej. 56"
                         style="width:100%; padding:8px 10px; border:1px solid #d8d6cf; border-radius:7px; font-size:13px;">
                @endif
              </div>
            </div>
            @if ($solicitud->folio)
              <p style="font-size:11.5px; color:#898781; margin:0;">El folio se asignó con el primer oficio/providencia de este expediente y ya no cambia.</p>
            @endif
            <div id="doc-oficial-campo-oficio">
              <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">No. de oficio</label>
              <input name="no_oficio" placeholder="ej. 123-2026"
                     style="width:100%; padding:8px 10px; border:1px solid #d8d6cf; border-radius:7px; font-size:13px;">
            </div>
            <div id="doc-oficial-campo-providencia">
              <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">No. de providencia</label>
              <input name="no_providencia" placeholder="ej. 123-2026"
                     style="width:100%; padding:8px 10px; border:1px solid #d8d6cf; border-radius:7px; font-size:13px;">
            </div>
            <p style="font-size:11.5px; color:#898781; margin:0;">Solo se usa el número que corresponda según la dependencia elegida (oficio para Despacho/Viceministerios, providencia para el resto).</p>
            <button type="submit" style="align-self:flex-start; background:#4a3aa7; color:#fff; border:0; border-radius:7px; padding:8px 14px; font-size:13px; font-weight:600; cursor:pointer;">
              Generar
            </button>
          </form>
        </div>

        <script>
          (function () {
            var select = document.getElementById('doc-oficial-dependencia');
            var campoOficio = document.getElementById('doc-oficial-campo-oficio');
            var campoProvidencia = document.getElementById('doc-oficial-campo-providencia');
            if (! select) { return; }
            function actualizar() {
              var opcion = select.options[select.selectedIndex];
              var tipo = opcion ? opcion.getAttribute('data-tipo') : null;
              campoOficio.style.display = (tipo === 'oficio') ? '' : 'none';
              campoProvidencia.style.display = (tipo === 'providencia') ? '' : 'none';
            }
            select.addEventListener('change', actualizar);
            actualizar();
          })();
        </script>
      @endif

      <div class="card">
        <h4 style="margin:0 0 10px; font-size:13px; color:#898781; text-transform:uppercase; letter-spacing:.03em;">Fechas</h4>
        <div style="font-size:13px;"><span style="color:#898781;">Fecha de vencimiento</span><br>{{ $solicitud->fecha_vencimiento?->format('d/m/Y') ?? 'Pendiente de ingresar' }}</div>
        <div style="font-size:13px; margin-top:6px;">
          <span style="color:#898781;">Días restantes</span><br>
          <strong style="color:{{ $dias === null ? 'inherit' : ($dias < 0 ? '#d03b3b' : ($dias <= 2 ? '#a86a06' : '#0ca30c')) }};">
            @if ($dias === null) — @elseif ($dias < 0) {{ abs($dias) }} días vencida @else {{ $dias }} días hábiles @endif
          </strong>
        </div>
        <p style="font-size:12px; color:#898781; margin:10px 0 0;">Plazo legal: 10 días hábiles desde la contraseña, con posibilidad de prórroga notificada hasta el 8vo día, +5 días hábiles si hay recurso de revisión y +5 más si es aprobado.</p>

        @if ($solicitud->fecha_finalizacion)
          <p style="font-size:12px; color:#a86a06; background:#fff8e6; border:1px solid #f0dfa8; border-radius:8px; padding:8px 10px; margin:10px 0 0;">
            Finalizado el {{ \Illuminate\Support\Carbon::parse($solicitud->fecha_finalizacion)->format('d/m/Y') }}. El portal del ciudadano deja de mostrar este expediente a partir del
            <strong>{{ $solicitud->fechaLimiteAccesoPortal()?->format('d/m/Y') }}</strong> (10 días hábiles después).
          </p>
        @endif

        @if (auth()->user()->hasPermission('solicitudes.ajustar_vencimiento') && $estadoClave !== 'pendiente_validacion')
          <form method="POST" action="{{ route('admin.solicitudes.vencimiento', $solicitud) }}" style="display:flex; flex-direction:column; gap:8px; margin-top:12px; padding-top:12px; border-top:1px solid #f0efec;">
            @csrf
            <label style="font-size:12px; color:#898781;">Ajustar fecha de vencimiento manualmente (ej. tras un recurso de revisión aprobado)</label>
            <input type="date" name="fecha_vencimiento" value="{{ $solicitud->fecha_vencimiento?->toDateString() }}" required
                   style="padding:8px 10px; border:1px solid #d8d6cf; border-radius:7px; font-size:13px;">
            <textarea name="motivo" rows="2" required placeholder="Motivo del ajuste…"
                      style="padding:8px 10px; border:1px solid #d8d6cf; border-radius:7px; font-size:13px; font-family:inherit;"></textarea>
            <button type="submit" style="align-self:flex-start; background:#52514e; color:#fff; border:0; border-radius:7px; padding:7px 12px; font-size:12.5px; font-weight:600; cursor:pointer;">Ajustar fecha</button>
          </form>
        @endif
      </div>
    </div>

    <div class="card">
      <div style="display:flex; gap:4px; flex-wrap:wrap; border-bottom:1px solid #e1e0d9; margin-bottom:16px; padding-bottom:2px;">
        @foreach ($tabs as $t)
          <a href="?tab={{ $t['key'] }}"
             style="padding:8px 12px; font-size:13px; text-decoration:none; border-bottom:2px solid {{ $tab === $t['key'] ? '#2a78d6' : 'transparent' }}; color:{{ $tab === $t['key'] ? '#2a78d6' : '#52514e' }}; font-weight:{{ $tab === $t['key'] ? '600' : '400' }};">
            {{ $t['label'] }}
            @if (! empty($t['has'])) <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:#4a3aa7; margin-left:3px;"></span> @endif
          </a>
        @endforeach
      </div>

      @if ($tab === 'seguimiento')
        <p style="font-size:13.5px; color:#52514e;">Estado actual: <strong>{{ $solicitud->estado?->etiqueta }}</strong>.</p>
        @if ($solicitud->observaciones)
          <p style="font-size:13px; color:#52514e; background:#f9f9f7; border-radius:8px; padding:10px 12px;">{{ $solicitud->observaciones }}</p>
        @endif

        @if (! in_array($estadoClave, ['finalizada', 'rechazada']) && auth()->user()->hasPermission('solicitudes.finalizar'))
          <div id="notificacion-resolucion" style="margin-top:18px; padding-top:16px; border-top:1px solid #f0efec;">
            <h4 style="margin:0 0 4px; font-size:13px; color:#898781; text-transform:uppercase; letter-spacing:.03em;">Notificación de resolución</h4>
            @if (! $solicitud->contrasena)
              <p style="font-size:12.5px; color:#898781; margin:6px 0 0;">Debe asignarse la contraseña antes de poder finalizar el expediente.</p>
            @else
              <p style="font-size:12px; color:#898781; margin:6px 0 10px;">
                Finaliza el expediente y notifica al interesado con asunto
                "RESPUESTA SOLICITUD No. {{ \App\Support\FormatoOficial::conComas($solicitud->contrasena) }}".
              </p>
              <form method="POST" action="{{ route('admin.solicitudes.finalizar', $solicitud) }}" enctype="multipart/form-data"
                    onsubmit="return confirm('¿Finalizar este expediente?');"
                    style="display:flex; flex-direction:column; gap:8px;">
                @csrf
                @include('admin.partials.enviar-correo-campos', ['label' => 'el documento de resolución'])
                <button type="submit" style="align-self:flex-start; background:#0ca30c; color:#fff; border:0; border-radius:7px; padding:8px 16px; font-size:13px; font-weight:600; cursor:pointer;">
                  Finalizar y notificar
                </button>
              </form>
            @endif
          </div>
        @endif

        @if (auth()->user()->hasPermission('correos.enviar'))
          <div style="margin-top:18px; padding-top:16px; border-top:1px solid #f0efec;">
            <h4 style="margin:0 0 4px; font-size:13px; color:#898781; text-transform:uppercase; letter-spacing:.03em;">Enviar correo</h4>
            <p style="font-size:12px; color:#898781; margin:6px 0 10px;">Correo libre (sin plantilla) — para casos que no encajan en una notificación automática.</p>
            <form method="POST" action="{{ route('admin.solicitudes.correo', $solicitud) }}" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:8px;">
              @csrf
              <label style="font-size:12px; color:#898781;">Para</label>
              <input type="email" name="destinatario" value="{{ old('destinatario', $solicitud->solicitante->correo) }}" required
                     style="padding:8px 10px; border:1px solid #d8d6cf; border-radius:7px; font-size:13px;">
              <label style="font-size:12px; color:#898781;">Asunto</label>
              <input name="asunto" value="{{ old('asunto') }}" required
                     style="padding:8px 10px; border:1px solid #d8d6cf; border-radius:7px; font-size:13px;">
              <label style="font-size:12px; color:#898781;">Mensaje</label>
              <textarea name="cuerpo" rows="4" required
                        style="padding:8px 10px; border:1px solid #d8d6cf; border-radius:7px; font-size:13px; font-family:inherit;">{{ old('cuerpo') }}</textarea>
              <label style="font-size:12px; color:#898781; display:block; margin-bottom:-2px;">Adjuntar PDF (opcional)</label>
              <input type="file" name="documento" accept=".pdf" style="padding:6px 0; font-size:13px;">
              <button type="submit" style="align-self:flex-start; background:#2a78d6; color:#fff; border:0; border-radius:7px; padding:8px 14px; font-size:13px; font-weight:600; cursor:pointer;">Enviar correo</button>
            </form>
          </div>
        @endif

      @elseif ($tab === 'prorroga')
        @forelse ($solicitud->prorrogas as $p)
          <div style="border-bottom:1px solid #f0efec; padding:10px 0; font-size:13px;">
            <div>Fecha anterior: <strong>{{ \Illuminate\Support\Carbon::parse($p->fecha_anterior)->format('d/m/Y') }}</strong> → nueva: <strong>{{ \Illuminate\Support\Carbon::parse($p->fecha_nueva)->format('d/m/Y') }}</strong></div>
            <div style="color:#898781; margin-top:4px;">{{ $p->motivo }}</div>
          </div>
        @empty
          <p style="font-size:13px; color:#898781;">No hay prórrogas registradas en este expediente.</p>
        @endforelse

        @if (auth()->user()->hasPermission('actuaciones.prorroga') && ! in_array($estadoClave, ['pendiente_validacion', 'finalizada', 'rechazada']))
          @if ($solicitud->fecha_vencimiento)
            <form method="POST" action="{{ route('admin.solicitudes.prorroga', $solicitud) }}" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:8px; margin-top:14px; padding-top:14px; border-top:1px solid #f0efec;">
              @csrf
              <label style="font-size:12px; color:#898781;">Nueva fecha de vencimiento (actual: {{ $solicitud->fecha_vencimiento->format('d/m/Y') }})</label>
              <input type="date" name="fecha_nueva" min="{{ $solicitud->fecha_vencimiento->copy()->addDay()->toDateString() }}" required
                     style="padding:8px 10px; border:1px solid #d8d6cf; border-radius:7px; font-size:13px;">
              <label style="font-size:12px; color:#898781;">Motivo</label>
              <textarea name="motivo" rows="2" required placeholder="Por qué se requiere más tiempo…"
                        style="padding:8px 10px; border:1px solid #d8d6cf; border-radius:7px; font-size:13px; font-family:inherit;"></textarea>
              @include('admin.partials.enviar-correo-campos', ['label' => 'el PDF de la prórroga'])
              <p style="font-size:11px; color:#898781; margin:0;">Asunto del correo: "Prórroga Solicitud {{ \App\Support\FormatoOficial::conComas($solicitud->contrasena) }}".</p>
              <button type="submit" style="align-self:flex-start; background:#4a3aa7; color:#fff; border:0; border-radius:7px; padding:8px 14px; font-size:13px; font-weight:600; cursor:pointer;">Registrar prórroga</button>
            </form>
          @else
            <p style="font-size:12px; color:#898781; margin-top:14px; padding-top:14px; border-top:1px solid #f0efec;">Debe asignarse la contraseña antes de poder registrar una prórroga.</p>
          @endif
        @endif

      @elseif ($tab === 'recurso')
        @forelse ($solicitud->recursos_revision as $r)
          <div style="border-bottom:1px solid #f0efec; padding:10px 0; font-size:13px;">
            @if ($r->correlativo)
              <div>Recurso de Revisión No. <strong>{{ $r->correlativo }}</strong> — presentado {{ \Illuminate\Support\Carbon::parse($r->fecha_presentacion)->format('d/m/Y') }}</div>
              <div style="color:#898781; margin-top:4px;">{{ $r->motivo }}</div>
              <div style="margin-top:4px;">Estado: <strong>{{ ucfirst(str_replace('_', ' ', $r->estado)) }}</strong></div>
            @else
              <div style="background:#fff8e6; border:1px solid #f0dfa8; border-radius:8px; padding:8px 10px; margin-bottom:8px; font-size:12.5px; color:#8a6100;">
                Presentado por el interesado desde su portal de seguimiento el {{ \Illuminate\Support\Carbon::parse($r->fecha_presentacion)->format('d/m/Y') }} — todavía sin número de correlativo.
              </div>
              <div style="color:#898781;">{{ $r->motivo }}</div>
              @if (auth()->user()->hasPermission('actuaciones.recurso'))
                <form method="POST" action="{{ route('admin.solicitudes.recurso.correlativo', [$solicitud, $r]) }}" style="display:flex; gap:8px; align-items:flex-end; margin-top:10px; flex-wrap:wrap;">
                  @csrf
                  <div>
                    <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">Correlativo</label>
                    <input name="correlativo" placeholder="ej. 30-2026" required
                           style="padding:8px 10px; border:1px solid #d8d6cf; border-radius:7px; font-size:13px;">
                  </div>
                  <div>
                    <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">Fecha de vencimiento (opcional)</label>
                    <input type="date" name="fecha_vencimiento" min="{{ now()->toDateString() }}"
                           style="padding:8px 10px; border:1px solid #d8d6cf; border-radius:7px; font-size:13px;">
                  </div>
                  <button type="submit" style="background:#eb6834; color:#fff; border:0; border-radius:7px; padding:8px 14px; font-size:13px; font-weight:600; cursor:pointer;">Asignar correlativo</button>
                </form>
              @endif
            @endif
          </div>
        @empty
          <p style="font-size:13px; color:#898781;">No hay recursos de revisión en este expediente.</p>
        @endforelse

        @if (auth()->user()->hasPermission('actuaciones.recurso') && $estadoClave !== 'pendiente_validacion')
          <form method="POST" action="{{ route('admin.solicitudes.recurso', $solicitud) }}" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:8px; margin-top:14px; padding-top:14px; border-top:1px solid #f0efec;">
            @csrf
            <label style="font-size:12px; color:#898781;">Correlativo</label>
            <input name="correlativo" placeholder="ej. 30-2026" required
                   style="padding:8px 10px; border:1px solid #d8d6cf; border-radius:7px; font-size:13px;">
            <label style="font-size:12px; color:#898781;">Motivo</label>
            <textarea name="motivo" rows="2" required placeholder="Motivo del recurso de revisión…"
                      style="padding:8px 10px; border:1px solid #d8d6cf; border-radius:7px; font-size:13px; font-family:inherit;"></textarea>
            <label style="font-size:12px; color:#898781;">Fecha de vencimiento (opcional)</label>
            <input type="date" name="fecha_vencimiento" min="{{ now()->toDateString() }}"
                   style="padding:8px 10px; border:1px solid #d8d6cf; border-radius:7px; font-size:13px;">
            @include('admin.partials.enviar-correo-campos', ['label' => 'el PDF del recurso'])
            <p style="font-size:11px; color:#898781; margin:0;">Asunto del correo: "Recurso Revisión No. {escrito arriba}".</p>
            <button type="submit" style="align-self:flex-start; background:#eb6834; color:#fff; border:0; border-radius:7px; padding:8px 14px; font-size:13px; font-weight:600; cursor:pointer;">Registrar recurso de revisión</button>
          </form>
        @endif

      @elseif ($tab === 'aclaracion')
        @forelse ($solicitud->aclaraciones as $a)
          <div style="border-bottom:1px solid #f0efec; padding:10px 0; font-size:13px;">
            <div style="background:#fff8e6; border:1px solid #f0dfa8; border-radius:8px; padding:8px 10px; margin-bottom:8px; font-size:12.5px; color:#8a6100;">
              Plazo real: {{ $a->plazo_dias_habiles }} días hábiles para responder (vence {{ \Illuminate\Support\Carbon::parse($a->fecha_limite_respuesta)->format('d/m/Y') }}).
            </div>
            <div>Solicitada el {{ \Illuminate\Support\Carbon::parse($a->fecha_solicitud)->format('d/m/Y') }} — estado: <strong>{{ ucfirst($a->estado) }}</strong></div>
            @if ($a->respuesta)
              <div style="color:#898781; margin-top:4px;">Respuesta: {{ $a->respuesta }}</div>
            @endif
          </div>
        @empty
          <p style="font-size:13px; color:#898781;">No hay aclaraciones en este expediente.</p>
        @endforelse

        @if (auth()->user()->hasPermission('actuaciones.aclaracion') && ! in_array($estadoClave, ['pendiente_validacion', 'finalizada', 'rechazada']))
          <form method="POST" action="{{ route('admin.solicitudes.aclaracion', $solicitud) }}" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:8px; margin-top:14px; padding-top:14px; border-top:1px solid #f0efec;">
            @csrf
            <label style="font-size:12px; color:#898781;">Qué se necesita que el interesado aclare</label>
            <textarea name="motivo" rows="3" required placeholder="ej. Precisar el período y los programas de interés…"
                      style="padding:8px 10px; border:1px solid #d8d6cf; border-radius:7px; font-size:13px; font-family:inherit;"></textarea>
            @include('admin.partials.enviar-correo-campos', ['label' => 'el PDF de la aclaración'])
            <p style="font-size:11px; color:#898781; margin:0;">Asunto del correo: "Aclaración Solicitud No. {{ \App\Support\FormatoOficial::conComas($solicitud->contrasena) }}".</p>
            <button type="submit" style="align-self:flex-start; background:#fab219; color:#3a2f00; border:0; border-radius:7px; padding:8px 14px; font-size:13px; font-weight:600; cursor:pointer;">Solicitar aclaración</button>
          </form>
        @endif

      @elseif ($tab === 'ampliacion')
        @forelse ($solicitud->ampliaciones as $a)
          <div style="border-bottom:1px solid #f0efec; padding:10px 0; font-size:13px;">
            @if ($a->estado === 'rechazada_no_regulada')
              <div style="background:#fdecea; border:1px solid #f3c6c1; border-radius:8px; padding:8px 10px; margin-bottom:8px; font-size:12.5px; color:#8a2c22;">
                La Ley de Acceso a la Información Pública no contempla ampliaciones una vez emitida la resolución de respuesta — se indicó al interesado presentar una solicitud nueva.
              </div>
            @endif
            <div>Solicitada el {{ \Illuminate\Support\Carbon::parse($a->fecha_solicitud)->format('d/m/Y') }}</div>
            <div style="color:#898781; margin-top:4px;">{{ $a->descripcion }}</div>
          </div>
        @empty
          <p style="font-size:13px; color:#898781;">No hay ampliaciones en este expediente.</p>
        @endforelse

        @if (auth()->user()->hasPermission('actuaciones.ampliacion'))
          <form method="POST" action="{{ route('admin.solicitudes.ampliacion', $solicitud) }}" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:8px; margin-top:14px; padding-top:14px; border-top:1px solid #f0efec;">
            @csrf
            @if ($estadoClave === 'finalizada')
              <p style="font-size:12px; color:#8a2c22; margin:0;">Este expediente ya está finalizado: la ampliación se registrará como no regulada por la ley, solo con fines de auditoría.</p>
            @endif
            <label style="font-size:12px; color:#898781;">Descripción de lo que pide el interesado</label>
            <textarea name="descripcion" rows="3" required placeholder="Qué información adicional solicita…"
                      style="padding:8px 10px; border:1px solid #d8d6cf; border-radius:7px; font-size:13px; font-family:inherit;"></textarea>
            @include('admin.partials.enviar-correo-campos', ['label' => 'la respuesta de la ampliación'])
            <button type="submit" style="align-self:flex-start; background:#e87ba4; color:#fff; border:0; border-radius:7px; padding:8px 14px; font-size:13px; font-weight:600; cursor:pointer;">Registrar ampliación</button>
          </form>
        @endif

      @elseif ($tab === 'documentos')
        @forelse ($solicitud->documentos as $doc)
          <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f0efec; padding:9px 0; font-size:13px; gap:10px;">
            <div>
              <div>{{ $doc->nombre }}@if ($doc->no_oficio) (No. {{ $doc->no_oficio }}) @elseif ($doc->no_providencia) (No. {{ $doc->no_providencia }}) @endif</div>
              <div style="color:#898781; font-size:12px;">{{ strtoupper($doc->tipo) }} · {{ $doc->created_at?->format('d/m/Y') }}@if ($doc->subido_por_user) · {{ $doc->subido_por_user->name }} @endif</div>
            </div>
            <div style="display:flex; align-items:center; gap:10px; flex:0 0 auto;">
              <span style="font-size:11.5px; color:{{ $doc->visible_ciudadano ? '#0ca30c' : '#898781' }};">{{ $doc->visible_ciudadano ? 'Visible al ciudadano' : 'Interno' }}</span>
              <a href="{{ route('admin.solicitudes.documentos.descargar', [$solicitud, $doc]) }}" style="font-size:12.5px; color:#2a78d6; text-decoration:none;">Descargar</a>
              @if (! $doc->visible_ciudadano && auth()->user()->hasPermission('documentos.publicar'))
                <form method="POST" action="{{ route('admin.solicitudes.documentos.publicar', [$solicitud, $doc]) }}" onsubmit="return confirm('¿Publicar este documento? Será visible para el ciudadano.');">
                  @csrf
                  <button type="submit" style="background:none; border:1px solid #0ca30c; color:#0ca30c; border-radius:6px; padding:4px 9px; font-size:11.5px; font-weight:600; cursor:pointer;">Publicar</button>
                </form>
              @endif
            </div>
          </div>
        @empty
          <p style="font-size:13px; color:#898781;">No hay documentos cargados.</p>
        @endforelse

        @if (auth()->user()->hasPermission('documentos.subir'))
          <form method="POST" action="{{ route('admin.solicitudes.documentos.store', $solicitud) }}" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:8px; margin-top:14px; padding-top:14px; border-top:1px solid #f0efec;">
            @csrf
            <label style="font-size:12px; color:#898781;">Archivo (PDF, Word, Excel o foto — máx. 10 MB)</label>
            <input type="file" name="archivo" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png" required
                   style="padding:6px 0; font-size:13px;">
            <label style="font-size:12px; color:#898781;">Nombre a mostrar (opcional)</label>
            <input name="nombre" placeholder="ej. Resolución de respuesta"
                   style="padding:8px 10px; border:1px solid #d8d6cf; border-radius:7px; font-size:13px;">
            @if (auth()->user()->hasPermission('documentos.publicar'))
              <label style="font-size:12.5px; display:flex; align-items:center; gap:6px;">
                <input type="checkbox" name="visible_ciudadano" value="1"> Publicar como visible al ciudadano
              </label>
            @endif
            <button type="submit" style="align-self:flex-start; background:#2a78d6; color:#fff; border:0; border-radius:7px; padding:8px 14px; font-size:13px; font-weight:600; cursor:pointer;">Cargar documento</button>
          </form>
        @endif

      @elseif ($tab === 'correos')
        @php $correos = $solicitud->correos_enviados->map(fn($c) => (object)['tipo'=>'enviado','asunto'=>$c->asunto,'fecha'=>$c->created_at])->concat($solicitud->correos_recibidos->map(fn($c) => (object)['tipo'=>'recibido','asunto'=>$c->asunto,'fecha'=>$c->created_at]))->sortByDesc('fecha'); @endphp
        @forelse ($correos as $c)
          <div style="display:flex; justify-content:space-between; border-bottom:1px solid #f0efec; padding:9px 0; font-size:13px;">
            <div>{{ $c->asunto }}</div>
            <div style="color:#898781; font-size:12px;">{{ ucfirst($c->tipo) }} · {{ $c->fecha?->format('d/m/Y H:i') }}</div>
          </div>
        @empty
          <p style="font-size:13px; color:#898781;">No hay correos asociados a este expediente todavía.</p>
        @endforelse

      @elseif ($tab === 'historial')
        @foreach ($solicitud->solicitud_historial as $h)
          <div style="display:flex; gap:10px; padding:9px 0; border-bottom:1px solid #f0efec; font-size:13px;">
            <span style="width:8px; height:8px; border-radius:50%; background:#2a78d6; margin-top:4px; flex:0 0 auto;"></span>
            <div>
              <div>{{ $h->descripcion }}</div>
              <div style="color:#898781; font-size:12px; margin-top:2px;">{{ ucfirst($h->tipo_actor) }} · {{ $h->created_at?->format('d/m/Y H:i') }}</div>
            </div>
          </div>
        @endforeach
      @endif
    </div>
  </div>
@endsection

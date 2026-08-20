@extends('layouts.admin')

@section('title', 'Expediente '.$solicitud->codigo_ns)

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
      <form method="POST" action="{{ route('admin.solicitudes.finalizar', $solicitud) }}" onsubmit="return confirm('¿Finalizar este expediente?');">
        @csrf
        <button type="submit" @disabled(! $solicitud->contrasena)
          style="background:{{ $solicitud->contrasena ? '#0ca30c' : '#c3c2b7' }}; color:#fff; border:0; border-radius:7px; padding:8px 16px; font-size:13px; font-weight:600; cursor:{{ $solicitud->contrasena ? 'pointer' : 'not-allowed' }};">
          Finalizar expediente
        </button>
      </form>
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
        @else
          <p style="font-size:13px; color:#898781; margin:0 0 10px;">Aún no se ha asignado dependencia.</p>
        @endif
        @if (auth()->user()->hasPermission('solicitudes.asignar_dependencia'))
          <form method="POST" action="{{ route('admin.solicitudes.dependencia', $solicitud) }}" style="display:flex; flex-direction:column; gap:8px; margin-top:10px;">
            @csrf
            <select name="dependencia_id" required style="padding:8px 10px; border:1px solid #d8d6cf; border-radius:7px; font-size:13px;">
              <option value="">Selecciona dependencia…</option>
              @foreach ($dependencias as $dep)
                <option value="{{ $dep->id }}" @selected($solicitud->dependencia_id === $dep->id)>{{ $dep->nombre }}</option>
              @endforeach
            </select>
            <select name="enlace_id" style="padding:8px 10px; border:1px solid #d8d6cf; border-radius:7px; font-size:13px;">
              <option value="">Sin enlace específico</option>
              @foreach ($dependencias as $dep)
                @foreach ($dep->enlaces as $enlace)
                  <option value="{{ $enlace->id }}" @selected($solicitud->enlace_id === $enlace->id)>{{ $enlace->nombre }} ({{ $dep->nombre }})</option>
                @endforeach
              @endforeach
            </select>
            <button type="submit" style="background:#2a78d6; color:#fff; border:0; border-radius:7px; padding:8px 14px; font-size:13px; font-weight:600; cursor:pointer;">
              {{ $solicitud->dependencia ? 'Reasignar' : 'Asignar' }}
            </button>
          </form>
        @endif
      </div>

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
            <form method="POST" action="{{ route('admin.solicitudes.prorroga', $solicitud) }}" style="display:flex; flex-direction:column; gap:8px; margin-top:14px; padding-top:14px; border-top:1px solid #f0efec;">
              @csrf
              <label style="font-size:12px; color:#898781;">Nueva fecha de vencimiento (actual: {{ $solicitud->fecha_vencimiento->format('d/m/Y') }})</label>
              <input type="date" name="fecha_nueva" min="{{ $solicitud->fecha_vencimiento->copy()->addDay()->toDateString() }}" required
                     style="padding:8px 10px; border:1px solid #d8d6cf; border-radius:7px; font-size:13px;">
              <label style="font-size:12px; color:#898781;">Motivo</label>
              <textarea name="motivo" rows="2" required placeholder="Por qué se requiere más tiempo…"
                        style="padding:8px 10px; border:1px solid #d8d6cf; border-radius:7px; font-size:13px; font-family:inherit;"></textarea>
              <button type="submit" style="align-self:flex-start; background:#4a3aa7; color:#fff; border:0; border-radius:7px; padding:8px 14px; font-size:13px; font-weight:600; cursor:pointer;">Registrar prórroga</button>
            </form>
          @else
            <p style="font-size:12px; color:#898781; margin-top:14px; padding-top:14px; border-top:1px solid #f0efec;">Debe asignarse la contraseña antes de poder registrar una prórroga.</p>
          @endif
        @endif

      @elseif ($tab === 'recurso')
        @forelse ($solicitud->recursos_revision as $r)
          <div style="border-bottom:1px solid #f0efec; padding:10px 0; font-size:13px;">
            <div>Recurso de Revisión No. <strong>{{ $r->correlativo }}</strong> — presentado {{ \Illuminate\Support\Carbon::parse($r->fecha_presentacion)->format('d/m/Y') }}</div>
            <div style="color:#898781; margin-top:4px;">{{ $r->motivo }}</div>
            <div style="margin-top:4px;">Estado: <strong>{{ ucfirst(str_replace('_', ' ', $r->estado)) }}</strong></div>
          </div>
        @empty
          <p style="font-size:13px; color:#898781;">No hay recursos de revisión en este expediente.</p>
        @endforelse

        @if (auth()->user()->hasPermission('actuaciones.recurso') && $estadoClave !== 'pendiente_validacion')
          <form method="POST" action="{{ route('admin.solicitudes.recurso', $solicitud) }}" style="display:flex; flex-direction:column; gap:8px; margin-top:14px; padding-top:14px; border-top:1px solid #f0efec;">
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
          <form method="POST" action="{{ route('admin.solicitudes.aclaracion', $solicitud) }}" style="display:flex; flex-direction:column; gap:8px; margin-top:14px; padding-top:14px; border-top:1px solid #f0efec;">
            @csrf
            <label style="font-size:12px; color:#898781;">Qué se necesita que el interesado aclare</label>
            <textarea name="motivo" rows="3" required placeholder="ej. Precisar el período y los programas de interés…"
                      style="padding:8px 10px; border:1px solid #d8d6cf; border-radius:7px; font-size:13px; font-family:inherit;"></textarea>
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
          <form method="POST" action="{{ route('admin.solicitudes.ampliacion', $solicitud) }}" style="display:flex; flex-direction:column; gap:8px; margin-top:14px; padding-top:14px; border-top:1px solid #f0efec;">
            @csrf
            @if ($estadoClave === 'finalizada')
              <p style="font-size:12px; color:#8a2c22; margin:0;">Este expediente ya está finalizado: la ampliación se registrará como no regulada por la ley, solo con fines de auditoría.</p>
            @endif
            <label style="font-size:12px; color:#898781;">Descripción de lo que pide el interesado</label>
            <textarea name="descripcion" rows="3" required placeholder="Qué información adicional solicita…"
                      style="padding:8px 10px; border:1px solid #d8d6cf; border-radius:7px; font-size:13px; font-family:inherit;"></textarea>
            <button type="submit" style="align-self:flex-start; background:#e87ba4; color:#fff; border:0; border-radius:7px; padding:8px 14px; font-size:13px; font-weight:600; cursor:pointer;">Registrar ampliación</button>
          </form>
        @endif

      @elseif ($tab === 'documentos')
        @forelse ($solicitud->documentos as $doc)
          <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f0efec; padding:9px 0; font-size:13px; gap:10px;">
            <div>
              <div>{{ $doc->nombre }}</div>
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
            <label style="font-size:12px; color:#898781;">Archivo (PDF, DOC o DOCX — máx. 10 MB)</label>
            <input type="file" name="archivo" accept=".pdf,.doc,.docx" required
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

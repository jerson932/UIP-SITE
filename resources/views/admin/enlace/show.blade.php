@extends('layouts.admin')

@section('title', 'Expediente '.$solicitud->codigo_ns)
@section('pageTitle', 'Expediente '.$solicitud->codigo_ns)
@section('pageSubtitle', 'Asignado a tu dependencia')

@section('content')
  <a href="{{ route('admin.enlace.index') }}" style="font-size:13px; color:#52514e; text-decoration:none;">← Volver al listado</a>

  @if ($errors->any())
    <div style="background:#fdecea; border:1px solid #f3c6c1; color:#8a2c22; border-radius:8px; padding:10px 14px; font-size:13.5px; margin:12px 0;">
      <ul style="margin:0; padding-left:18px;">@foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul>
    </div>
  @endif

  <div style="display:flex; justify-content:space-between; align-items:flex-start; margin:12px 0 18px; gap:16px; flex-wrap:wrap;">
    <div>
      <h2 style="margin:0 0 2px;">Expediente {{ $solicitud->codigo_ns }}</h2>
      <p style="margin:0; color:#52514e; font-size:13.5px;">{{ $solicitud->asunto }}</p>
    </div>
    @include('admin.partials.estado-badge', ['estado' => $solicitud->estado])
  </div>

  <div style="display:grid; grid-template-columns:minmax(0,340px) minmax(0,1fr); gap:18px; align-items:start;">
    <div style="display:flex; flex-direction:column; gap:16px;">
      <div class="card">
        <h4 style="margin:0 0 10px; font-size:13px; color:#898781; text-transform:uppercase; letter-spacing:.03em;">Información del interesado</h4>
        <div style="font-size:13px; display:flex; flex-direction:column; gap:6px;">
          <div><span style="color:#898781;">Nombre</span><br>{{ $solicitud->solicitante->nombre }}</div>
          <div><span style="color:#898781;">Fecha de ingreso</span><br>{{ $solicitud->fecha_ingreso?->format('d/m/Y') }}</div>
          @if ($solicitud->contrasena)
            <div><span style="color:#898781;">Contraseña</span><br>No. {{ $solicitud->contrasena }}</div>
          @endif
        </div>
      </div>

      <div class="card">
        <h4 style="margin:0 0 10px; font-size:13px; color:#898781; text-transform:uppercase; letter-spacing:.03em;">Oficio / providencia recibido</h4>
        @forelse ($documentosOficiales as $doc)
          <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f0efec; padding:7px 0; font-size:12.5px; gap:8px;">
            <div>
              <div>{{ $doc->nombre }}@if ($doc->no_oficio) (No. {{ $doc->no_oficio }}) @elseif ($doc->no_providencia) (No. {{ $doc->no_providencia }}) @endif</div>
              <div style="color:#898781; font-size:11.5px;">{{ $doc->created_at?->format('d/m/Y H:i') }}</div>
            </div>
            <a href="{{ route('admin.solicitudes.documentos.descargar', [$solicitud, $doc]) }}" style="color:#2a78d6; text-decoration:none; flex:0 0 auto;">Descargar</a>
          </div>
        @empty
          <p style="font-size:13px; color:#898781; margin:0;">No hay oficio/providencia registrado todavía.</p>
        @endforelse
      </div>

      <div class="card">
        <h4 style="margin:0 0 10px; font-size:13px; color:#898781; text-transform:uppercase; letter-spacing:.03em;">Archivos que has cargado</h4>
        @forelse ($documentosPropios as $doc)
          <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f0efec; padding:7px 0; font-size:12.5px; gap:8px;">
            <div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $doc->nombre }}</div>
            <a href="{{ route('admin.solicitudes.documentos.descargar', [$solicitud, $doc]) }}" style="color:#2a78d6; text-decoration:none; flex:0 0 auto;">Descargar</a>
          </div>
        @empty
          <p style="font-size:13px; color:#898781; margin:0;">Todavía no has cargado ningún archivo en este expediente.</p>
        @endforelse
      </div>
    </div>

    <div style="display:flex; flex-direction:column; gap:16px;">
      <div class="card">
        <h4 style="margin:0 0 10px; font-size:13px; color:#898781; text-transform:uppercase; letter-spacing:.03em;">Dejar observación</h4>
        <p style="font-size:12.5px; color:#898781; margin:0 0 10px;">
          La UIP la verá en el historial del expediente. Este panel es solo de consulta — no cambia el estado ni reasigna el expediente.
        </p>
        <form method="POST" action="{{ route('admin.enlace.observacion', $solicitud) }}" style="display:flex; flex-direction:column; gap:8px;">
          @csrf
          <textarea name="observacion" rows="4" required minlength="3" maxlength="4000" placeholder="Escribe tu observación sobre el estado de la búsqueda de información…"
                    style="width:100%; padding:10px; border:1px solid #d8d6cf; border-radius:7px; font-size:13.5px; font-family:inherit; resize:vertical;"></textarea>
          <button type="submit" style="align-self:flex-start; background:#2a78d6; color:#fff; border:0; border-radius:7px; padding:8px 16px; font-size:13px; font-weight:600; cursor:pointer;">
            Guardar observación
          </button>
        </form>
      </div>

      <div class="card">
        <h4 style="margin:0 0 10px; font-size:13px; color:#898781; text-transform:uppercase; letter-spacing:.03em;">Adjuntar documento</h4>
        <p style="font-size:12.5px; color:#898781; margin:0 0 10px;">PDF, Word, Excel o CSV — hasta 10 MB.</p>
        <form method="POST" action="{{ route('admin.solicitudes.documentos.store', $solicitud) }}" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:8px;">
          @csrf
          <input type="file" name="archivo" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv" required
                 style="width:100%; padding:8px; border:1px solid #d8d6cf; border-radius:7px; font-size:13px;">
          <input type="text" name="nombre" placeholder="Nombre para identificarlo (opcional)"
                 style="width:100%; padding:8px 10px; border:1px solid #d8d6cf; border-radius:7px; font-size:13px;">
          <button type="submit" style="align-self:flex-start; background:#4a3aa7; color:#fff; border:0; border-radius:7px; padding:8px 16px; font-size:13px; font-weight:600; cursor:pointer;">
            Adjuntar
          </button>
        </form>
      </div>

      <div class="card">
        <h4 style="margin:0 0 10px; font-size:13px; color:#898781; text-transform:uppercase; letter-spacing:.03em;">Proceso del expediente</h4>
        @if ($solicitud->solicitud_historial->isEmpty())
          <p style="font-size:13px; color:#898781; margin:0;">Todavía no hay movimientos registrados.</p>
        @else
          <div style="position:relative; padding-left:16px; border-left:2px solid #f0efec;">
            @foreach ($solicitud->solicitud_historial as $item)
              <div style="position:relative; padding-bottom:14px;">
                <span style="position:absolute; left:-21px; top:3px; width:9px; height:9px; border-radius:50%; background:#2a78d6; border:2px solid #fff;"></span>
                <div style="font-size:12px; color:#898781;">{{ $item->created_at?->format('d/m/Y H:i') }}</div>
                <div style="font-size:13px; margin-top:2px;">{{ $item->descripcion }}</div>
              </div>
            @endforeach
          </div>
        @endif
      </div>
    </div>
  </div>
@endsection

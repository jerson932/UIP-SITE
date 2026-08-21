{{--
  Campos compartidos por las actuaciones que notifican al interesado por
  correo (Prórroga, Aclaración, Recurso de Revisión, Finalizar — Fase 22):
  un adjunto PDF opcional y la opción de enviar (o no) el correo
  automático. El truco del input oculto + checkbox hace que, si se
  desmarca, igual se envíe "enviar_correo=0" en el POST (si solo estuviera
  el checkbox, un checkbox desmarcado no manda nada) — así el controlador
  puede distinguir "no enviar" de "el formulario no incluye este campo"
  (formularios/tests viejos que no lo mandan siguen enviando correo por
  defecto, vía $request->boolean('enviar_correo', true)).

  Uso: @include('admin.partials.enviar-correo-campos', ['label' => 'el PDF de la prórroga'])
--}}
<div>
  <label style="font-size:12px; color:#898781; display:block; margin-bottom:4px;">Adjuntar PDF{{ isset($label) ? ' ('.$label.')' : '' }} — opcional</label>
  <input type="file" name="documento" accept=".pdf"
         style="padding:6px 0; font-size:13px;">
</div>
<label style="font-size:12.5px; display:flex; align-items:center; gap:6px;">
  <input type="hidden" name="enviar_correo" value="0">
  <input type="checkbox" name="enviar_correo" value="1" checked> Enviar correo al interesado
</label>

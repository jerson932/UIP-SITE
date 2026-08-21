# Fase 22d — Ajustes a la publicación de documentos, correo opcional, recurso solo tras resolución, y adjuntos del ciudadano

Retroalimentación sobre Fase 22c:

> "tambien quiero poder quitar visibildad, pero mira es cuando le doy notificar y finalizar es cuando se envian los documentos ajuntos, no cuando le doy publicar, en publicar es como dando opcion de que se enviara a su correo del interesado, y tambien lo que puede ver en la pestaña de seguimiento, y tambien la solicitud del recurso solo es cuando se da por notifiado la solicitud o finalizado, ahi puede pedir el recurso de revision, y tambie la ampliacion que pueda cargar en ambos documentos el interesado"

Resultado de las pruebas automatizadas: **187 de 187 pasaron, 554 aserciones, 0 fallos** (13 pruebas nuevas específicas de esta ronda, en `Fase22dTest.php`).

---

## 1. Ahora se puede "quitar visibilidad" a un documento

En la pestaña "Documentos" de una solicitud, cualquier documento que esté marcado como "Visible al ciudadano" ahora tiene un botón **"Quitar visibilidad"** junto a "Descargar". Al usarlo:

- El documento deja de aparecer en el portal de seguimiento del ciudadano.
- Queda un registro en el historial, pero **solo interno** (no se le muestra al ciudadano en su línea de tiempo que se ocultó un documento — evita confusión).
- El sistema advierte, en el mensaje de confirmación, que si ya se había enviado un correo con ese documento adjunto, ese correo no se puede "deshacer".

## 2. "Publicar" ya NO envía correo automáticamente — ahora es opcional

Se corrigió el comportamiento de Fase 22c: publicar un documento (o subir uno ya marcado como visible) **ya no dispara un correo obligatorio**. Ahora:

- Publicar sigue haciendo lo principal: el documento pasa a ser visible en el portal de seguimiento del ciudadano.
- Al lado del botón "Publicar" aparece un pequeño formulario con la opción **"Enviar por correo al interesado"**, marcada por defecto — el usuario puede desmarcarla si solo quiere que quede visible en el portal, sin generar un correo en ese momento.
- Lo mismo aplica al subir un documento ya marcado como "Publicar como visible al ciudadano" desde el formulario de carga.
- El envío de correo **obligatorio** real de todas formas sigue ocurriendo donde ya ocurría: al usar "Finalizar y notificar" (con el documento de resolución adjunto) y en las demás actuaciones (Prórroga, Aclaración, Recurso, etc.) — eso no cambió.

## 3. El recurso de revisión solo se puede presentar después de la resolución

Antes, el ciudadano podía presentar un recurso de revisión desde su portal de seguimiento en casi cualquier estado del expediente (excepto "Pendiente de validación"). Ahora solo puede hacerlo **una vez que la solicitud fue notificada/finalizada** (es decir, cuando ya existe una resolución que recurrir) — se usa la misma marca de "estado final" que ya usa el sistema para "Finalizada" y "Rechazada". Mientras el expediente sigue en trámite, esa opción simplemente no aparece en su portal.

La opción de pedir una **ampliación** no cambió: sigue disponible en cualquier momento (antes o después de la resolución), como ya funcionaba desde Fase 22b.

## 4. El ciudadano puede adjuntar un documento de soporte

Tanto al presentar un recurso de revisión como al pedir una ampliación desde el portal público, ahora aparece un campo opcional **"Adjuntar documento de soporte"** (PDF, Word, Excel o foto — máx. 10 MB, mismos formatos que ya se aceptan en el resto del sistema). Ese documento:

- Queda vinculado al recurso/ampliación correspondiente — en el panel administrativo, dentro de esas pestañas, ahora aparece un enlace "Documento adjunto" con el nombre del archivo, para descargarlo directamente.
- Queda como documento **interno** por defecto (no visible en el portal), igual que cualquier otro documento subido: la UIP lo revisa y decide si lo publica desde la pestaña "Documentos", manteniendo un único flujo de publicación en todo el sistema.

---

## Pasos de instalación

1. Copiar los archivos de este paquete a sus rutas correspondientes (mismas rutas relativas que en el checkout). Este paquete asume que ya aplicaste la entrega anterior (Fase 22c) — solo trae los archivos que cambiaron sobre esa base.

2. No se necesitan migraciones ni seeders nuevos en esta ronda — se reutilizan columnas que ya existían (`documentos.visible_ciudadano`, `recursos_revision.documento_id`, `ampliaciones.documento_id`, `solicitud_estados.es_final`).

3. Verificar que las pruebas pasen:

   ```
   php artisan test
   ```

   Debe mostrar **187 passed**.

4. Limpiar cachés de vista si es necesario:

   ```
   php artisan view:clear
   ```

---

## Archivos incluidos en este paquete

- `app/Http/Controllers/Admin/DocumentoController.php` — nueva acción `ocultar()`; `store()`/`publicar()` con correo opcional (puntos 1 y 2)
- `app/Http/Controllers/Public/SeguimientoController.php` — recurso gateado a `es_final`; adjunto de documento en ambos formularios de autoservicio (puntos 3 y 4)
- `routes/web.php` — nueva ruta `admin.solicitudes.documentos.ocultar`
- `resources/views/admin/solicitudes/show.blade.php` — botón "Quitar visibilidad", checkbox de correo opcional en Publicar/Cargar, enlaces a documentos adjuntos por el ciudadano en las pestañas Recurso/Ampliación
- `resources/views/public/seguimiento-resultado.blade.php` — condición de visibilidad del recurso actualizada; campo de archivo agregado a ambos formularios de autoservicio
- `tests/Feature/Fase22dTest.php` (nuevo) — 13 pruebas cubriendo los 4 puntos de esta ronda

---

## Nota sobre el repositorio de GitHub

Sigo sin acceso de push al repositorio `jerson932/UIP-SITE` desde este entorno (se verificó de nuevo antes de esta entrega, mismo error de siempre: "access denied by the git proxy"). Esta entrega, como las anteriores, es por archivo comprimido.

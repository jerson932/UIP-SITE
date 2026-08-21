# Fase 22 — Enlaces, correos con adjunto y panel de resolución

## Cómo aplicar este paquete

Estos son solo los archivos nuevos/modificados de esta fase. Cópialos sobre tu proyecto respetando las rutas (algunos son nuevos, otros reemplazan un archivo existente):

```
app/Support/FormatoOficial.php                              (nuevo)
app/Http/Controllers/Admin/EnlaceAdminController.php         (nuevo)
resources/views/admin/enlaces/index.blade.php                (nuevo)
resources/views/admin/enlaces/create.blade.php                (nuevo)
resources/views/admin/enlaces/edit.blade.php                  (nuevo)
resources/views/admin/partials/enviar-correo-campos.blade.php (nuevo)
tests/Feature/Fase22ActuacionesTest.php                        (nuevo)
tests/Feature/EnlaceAdminControllerTest.php                    (nuevo)

app/Services/DocumentoOficialService.php               (modificado)
app/Mail/PlantillaCorreoMail.php                        (modificado)
app/Services/NotificacionService.php                    (modificado)
app/Http/Controllers/Admin/ActuacionController.php      (modificado)
app/Http/Controllers/Admin/SolicitudActionController.php (modificado)
database/seeders/PlantillaCorreoSeeder.php               (modificado)
routes/web.php                                            (modificado)
resources/views/admin/solicitudes/show.blade.php          (modificado)
resources/views/layouts/admin.blade.php                   (modificado)
resources/views/admin/partials/icon.blade.php              (modificado)
tests/Feature/NotificacionesTest.php                        (modificado)
```

Después de copiar los archivos:

```
php artisan db:seed --class=Database\\Seeders\\PlantillaCorreoSeeder --force
php artisan test
```

(El seeder de plantillas usa `updateOrCreate`, así que es seguro volver a correrlo — solo actualiza los asuntos de las plantillas que cambiaron, no borra nada.)

## Qué se agregó

**Se quitó el formulario manual "Asignar dependencia/enlace"** de la tarjeta "Dependencia y enlace" del expediente — ahora esa tarjeta solo muestra la dependencia/enlace actual (de solo lectura) con una nota explicando que la asignación es automática al generar el Oficio o Providencia (esto ya funcionaba desde la Fase 21; lo único que faltaba era quitar el formulario redundante de la vista, como confirmaste).

**Panel de "Gestión de enlaces"** (nuevo, en el menú lateral bajo Administración → Gestión de enlaces, mismo permiso que Usuarios): permite crear el contacto de cada dependencia (nombre, correo, teléfono) y, por separado, darle acceso de inicio de sesión con un botón "Crear acceso" — genera una contraseña temporal y te muestra la URL de acceso (`/login`), el correo y la contraseña para que se los compartas. Si el enlace ya tiene acceso, el mismo botón se convierte en "Generar nueva contraseña" para reiniciarla. Esto también corrige de raíz el error 403 que viste al crear un enlace nuevo desde el panel de Usuarios: antes, el usuario con rol "Enlace" no quedaba vinculado a su registro de dependencia (Enlace.user_id se quedaba vacío); crear el acceso desde este panel nuevo siempre deja ese vínculo bien hecho.

**Adjuntar PDF y controlar el envío de correo en cada actuación** — Prórroga, Aclaración, Recurso de Revisión (en sus pestañas respectivas del expediente) y ahora también un panel de "Notificación de resolución" dentro de la pestaña Seguimiento (reemplaza el botón simple "Finalizar expediente", que ahora te lleva directo a este panel). Cada uno de estos formularios tiene:
- Un campo para adjuntar el PDF correspondiente (opcional) — queda guardado como documento del expediente y visible al ciudadano en su portal de seguimiento, y se adjunta al correo real que se le envía.
- Una casilla "Enviar correo al interesado", marcada por defecto — si la desmarcas, la actuación se registra igual pero no se envía el correo automático.

Los asuntos de correo quedaron con el formato exacto que pediste:
- Prórroga: **"Prórroga Solicitud {contraseña}"**
- Aclaración: **"Aclaración Solicitud No. {contraseña}"**
- Recurso de Revisión: **"Recurso Revisión No. {correlativo}"**
- Notificación de resolución (Finalizar): **"RESPUESTA SOLICITUD No. {contraseña}"**

Todos con coma de miles cuando aplica (p. ej. "1,524-2026"), igual que en los oficios/providencias de la Fase 21.

**Espacio de "Enviar correo" en la pestaña Seguimiento** — un correo libre (sin plantilla fija), con destinatario (por defecto el correo del interesado, pero puedes cambiarlo), asunto y mensaje a tu gusto, más adjunto PDF opcional. Útil para cualquier caso que no encaje en una notificación automática.

## Sobre las respuestas del interesado por correo (lo que preguntaste: "¿me llega también en mi app?")

Por ahora esto queda pendiente, tal como acordamos — no hay ninguna infraestructura de correo entrante (buzón IMAP, webhook de un proveedor como Mailgun/Postmark, etc.) conectada al sistema todavía. La tabla `correos_recibidos` ya existe en el esquema desde antes como marcador de posición, así que cuando tengas listo el proveedor/infraestructura de correo entrante que vayas a usar, se puede conectar sin rediseñar nada de lo que se construyó ahora.

## Verificación

Se agregaron 15 pruebas automatizadas nuevas (`Fase22ActuacionesTest`, 10 pruebas; `EnlaceAdminControllerTest`, 5 pruebas) cubriendo: adjuntar PDF y enlazarlo a la actuación, el formato de cada asunto de correo, la casilla "enviar correo" (incluyendo que los formularios/pruebas viejos que no la mandan sigan enviando correo automático, para no romper nada existente), el correo libre, que el formulario manual de asignación ya no aparece, y el flujo completo de crear un enlace + darle acceso + que pueda iniciar sesión de verdad. Las 152 pruebas del proyecto (137 anteriores + 15 nuevas) pasan.

Además se probó en vivo con un servidor real (Postgres + `php artisan serve` + Playwright): se creó un enlace, se le dio acceso y se confirmó que puede iniciar sesión sin el error 403 que reportaste; se registró una prórroga con un PDF adjunto real (quedó enlazada al documento, visible al ciudadano, y el correo se generó con el asunto correcto); se finalizó un expediente desde el nuevo panel de notificación de resolución; y se envió un correo libre desde Seguimiento. El envío real de SMTP falla en este entorno de pruebas porque no tiene salida a internet hacia Gmail (mismo comportamiento esperado que en fases anteriores) — en tu servidor real, con el `.env` configurado, si enviará de verdad.

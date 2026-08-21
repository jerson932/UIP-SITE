# Fase 22b — Paquete completo

Este paquete contiene TODOS los archivos de los 8 cambios que pediste en tu
último mensaje. Cada archivo de este zip reemplaza (o crea) el archivo con
la MISMA ruta dentro de tu proyecto (`uip-mingob-build`). Copia cada
carpeta tal cual — no cambies ningún nombre de carpeta ni de archivo.

## Qué se hizo, uno por uno

1. **Quitar "¿Trabajas en la UIP?"** del formulario público de solicitud
   (`resources/views/public/solicitud-form.blade.php`) — antes Fase 21 solo
   lo había quitado del formulario de seguimiento, no de este.

2. **Campo "País"** agregado al formulario público, con Guatemala
   seleccionado por defecto y una lista de países comunes (más "Otro").

3. **El seguimiento del ciudadano ya NO muestra a qué dependencia/enlace se
   asignó el expediente.** Se agregó una columna nueva
   `visible_ciudadano` a la tabla `solicitud_historial`: las entradas
   internas (asignación de dependencia, generación de oficio/providencia,
   correos ad-hoc, observaciones del enlace) se marcan como no visibles;
   el portal público (`/seguimiento`) ahora solo muestra las visibles.

4. **El panel del enlace ya no muestra "todos los documentos del
   expediente"** — ahora la tarjeta se llama "Archivos que has cargado" y
   solo lista lo que el propio enlace subió. La tarjeta "Oficio /
   providencia recibido" sigue igual (ya estaba bien filtrada).

5. **Ampliación ahora puede adjuntar PDF y enviar correo**, igual que
   Prórroga/Aclaración/Recurso de Revisión — incluso si el expediente
   sigue activo (antes solo enviaba correo cuando ya estaba finalizado).
   Se agregó una plantilla de correo nueva: `ampliacion_recibida`.

6. **Aviso interno por correo cada vez que llega una solicitud por el
   formulario público.** Se envía a una dirección fija que tú configuras
   desde Configuración → "Aviso interno de nueva solicitud" (reutiliza el
   correo institucional `correo_uip` que ya existía). Si no configuras
   nada, simplemente no se envía el aviso (no da error).

7. **El ciudadano ahora puede pedir un Recurso de Revisión o una
   Ampliación él mismo**, desde su propio portal de seguimiento (con su
   código de acceso, sin iniciar sesión) — abajo de la línea de tiempo hay
   dos secciones desplegables nuevas. Estos quedan como registros REALES
   (`RecursoRevision`/`Ampliacion`), igual que si los hubiera creado un
   administrador, visibles y accionables desde el panel normal.
   - Como el ciudadano no puede inventarse un número de correlativo, su
     recurso se crea SIN correlativo. En la pestaña "Recurso de Revisión"
     del expediente vas a ver un aviso amarillo "todavía sin número de
     correlativo" con un formulario chiquito para asignárselo — al
     hacerlo se dispara el mismo correo que ya recibía un recurso creado
     por un administrador.

8. **Correo entrante (que el ciudadano responda un correo y esto se
   registre solo) sigue fuera de alcance**, tal como quedamos — no se
   construyó en este paquete.

## Pasos para instalar (en orden)

1. Copia todos los archivos de este zip a las mismas rutas dentro de
   `uip-mingob-build`. La estructura de carpetas del zip es idéntica a la
   del proyecto (`app/...`, `database/...`, `resources/...`, `routes/...`,
   `tests/...`) — simplemente arrastra todo sobre la carpeta del proyecto,
   reemplazando cuando pregunte.

2. Corre las migraciones nuevas:
   ```
   php artisan migrate
   ```
   (Vas a ver dos migraciones nuevas: `add_visible_ciudadano_...` y
   `fase22b_recurso_correlativo_nullable_...`.)

3. Vuelve a correr el seeder de plantillas de correo (agrega la plantilla
   nueva `ampliacion_recibida` sin tocar las demás):
   ```
   php artisan db:seed --class=PlantillaCorreoSeeder
   ```

4. (Opcional pero recomendado) Configura el correo de avisos internos:
   entra a **Configuración** en el panel admin y llena "Correo de avisos
   internos" con la dirección donde quieres recibir el aviso de cada
   solicitud nueva.

5. Corre los tests para confirmar que todo quedó bien:
   ```
   php artisan test
   ```
   Deberías ver **165 tests, 165 passed** (153 de antes + 12 nuevos de
   esta fase, en el archivo `tests/Feature/Fase22bTest.php`).

## Nota sobre un bug que se encontró y se corrigió de paso

La migración que hace opcional el `correlativo` de Recurso de Revisión
originalmente usaba una instrucción SQL escrita solo para PostgreSQL
(`ALTER TABLE ... ALTER COLUMN ... DROP NOT NULL`). Se comprobó que en
realidad **no hace falta** — Laravel 13 (la versión de este proyecto) ya
no depende de `doctrine/dbal` para este tipo de cambios, así que se
reescribió usando el método normal de Laravel (`->nullable()->change()`),
que funciona igual en PostgreSQL. Esto no cambia nada de lo que ves en la
aplicación, solo hace la migración más robusta.

## Archivos incluidos en este zip

```
database/migrations/2026_08_21_201756_add_visible_ciudadano_to_solicitud_historial_table.php   (nuevo)
database/migrations/2026_08_21_201811_fase22b_recurso_correlativo_nullable_y_ampliacion_documento.php   (nuevo)
tests/Feature/Fase22bTest.php   (nuevo — 12 tests)

app/Models/SolicitudHistorial.php
app/Models/Ampliacion.php
app/Http/Controllers/Admin/SolicitudActionController.php
app/Http/Controllers/Admin/ActuacionController.php
app/Http/Controllers/Public/SolicitudPublicaController.php
app/Http/Controllers/Public/SeguimientoController.php
app/Http/Controllers/Admin/EnlaceController.php
app/Http/Controllers/Admin/ConfiguracionController.php
app/Services/NotificacionService.php
app/Support/CatalogosSolicitud.php
database/seeders/PlantillaCorreoSeeder.php
routes/web.php
resources/views/public/solicitud-form.blade.php
resources/views/public/seguimiento-resultado.blade.php
resources/views/admin/solicitudes/show.blade.php
resources/views/admin/enlace/show.blade.php
resources/views/admin/configuracion/index.blade.php
tests/Feature/NotificacionesTest.php   (un test se actualizó, otro se agregó)
tests/Feature/SolicitudIntakeTest.php   (se agregó el campo "país" a los tests existentes)
```

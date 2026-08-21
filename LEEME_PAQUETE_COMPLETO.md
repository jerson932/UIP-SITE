# Paquete completo — módulo de Enlaces (Fase 21 + Fase 22)

Como hemos ido con parches sueltos y se prestó a confusión entre `enlace` (singular)
y `enlaces`/`gestion_enlaces` (plural), aquí va TODO lo relacionado a este módulo
en un solo paquete, con la estructura final correcta. Reemplaza estos archivos
tal cual, sin mezclarlos con versiones anteriores que hayas ido copiando.

## Estructura final (cópiala exactamente así)

```
app/Support/FormatoOficial.php
app/Http/Controllers/Admin/EnlaceAdminController.php      ← panel de administración (Fase 22)
app/Http/Controllers/Admin/EnlaceController.php            ← portal de autoservicio del enlace (Fase 21)
app/Http/Controllers/Admin/ActuacionController.php
app/Http/Controllers/Admin/SolicitudActionController.php
app/Mail/PlantillaCorreoMail.php
app/Services/DocumentoOficialService.php
app/Services/NotificacionService.php
database/seeders/PlantillaCorreoSeeder.php
routes/web.php
resources/views/admin/enlace/index.blade.php                ← portal del enlace (singular)
resources/views/admin/enlace/show.blade.php                  ← portal del enlace (singular)
resources/views/admin/gestion_enlaces/index.blade.php         ← panel admin (Fase 22, ya no se llama "enlaces")
resources/views/admin/gestion_enlaces/create.blade.php
resources/views/admin/gestion_enlaces/edit.blade.php
resources/views/admin/partials/enviar-correo-campos.blade.php
resources/views/admin/partials/icon.blade.php
resources/views/admin/solicitudes/show.blade.php
resources/views/layouts/admin.blade.php
tests/Feature/Fase22ActuacionesTest.php
tests/Feature/EnlaceAdminControllerTest.php
tests/Feature/EnlaceControllerTest.php
tests/Feature/NotificacionesTest.php
```

## Antes de copiar

Si en algún momento llegaste a crear una carpeta `resources\views\admin\enlaces\`
(con "s", plural) — **bórrala por completo**. Ya no se usa; el panel de
administración ahora vive en `resources\views\admin\gestion_enlaces\` para no
confundirse con `resources\views\admin\enlace\` (singular, el portal del
enlace).

## Después de copiar

```
php artisan test
```

Si sigue fallando algo relacionado a `admin.enlace.index` o
`admin.gestion_enlaces.*`, es casi seguro que alguno de los archivos de este
paquete no se copió — revisa con `dir` que las dos carpetas (`enlace` y
`gestion_enlaces`) tengan exactamente los archivos listados arriba, ni uno
más ni uno menos, y pégame la salida de `dir` si no logras ubicar la
diferencia.

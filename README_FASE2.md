# UIP-MINGOB — Fase 2: Diseño de base de datos

Este proyecto es el arranque real (Laravel 13 + PostgreSQL, tal como propone
la sección 28 del documento de especificación) del sistema de la Unidad de
Información Pública. Corresponde a la **Fase 2** del plan de 14 fases:
diseño de base de datos y relaciones.

## Qué incluye este entregable

- **26 tablas** (`database/migrations/`): las ~24 que lista la sección 23
  del documento, más `role_permission` (pivote) y las columnas de negocio
  agregadas a `users`. Nombradas y tipadas para reflejar exactamente las
  reglas reales que ya validamos en el prototipo HTML:
  - `solicitudes.contrasena` (no "número oficial") — nullable, se llena
    solo después de aceptar la solicitud.
  - `aclaraciones.plazo_dias_habiles` (default 2) y su `fecha_limite_respuesta`.
  - `prorrogas.solicitada_en` con el comentario del plazo de 8vo día hábil.
  - `recursos_revision.correlativo` — único e independiente de la contraseña
    de la solicitud (ej. "30-2026").
  - `ampliaciones.estado` — incluye `rechazada_no_regulada`, para registrar
    (con fines de auditoría) que una ampliación posterior a la resolución no
    es una actuación regulada por la Ley de Acceso a la Información Pública.
  - `feriados` — para calcular días hábiles.
- **25 modelos Eloquent** (`app/Models/`) con `$fillable`, `$casts` y las
  relaciones `belongsTo`/`hasMany`/`belongsToMany` entre ellos, más el
  modelo `User` de Laravel extendido con rol, dependencia y las relaciones
  inversas correspondientes.
- **Seeders** (`database/seeders/`) con datos de catálogo y una muestra de
  6 expedientes de prueba (de los 10 que se usaron para validar el
  prototipo HTML), cubriendo cada estado/actuación principal:
  - `RoleSeeder`, `PermissionSeeder` (con asignación por rol)
  - `SolicitudEstadoSeeder` (los 9 estados del prototipo)
  - `DependenciaSeeder` (con sus enlaces)
  - `PlantillaCorreoSeeder` — **las plantillas con el tono/formato real**
    de la UIP que confirmamos contra los correos reales que compartiste
    (recepción con Contraseña, aclaración con plazo de 2 días, prórroga,
    ampliación no procedente, recurso de revisión, resolución, finalización)
  - `FeriadoSeeder` (feriados de Guatemala 2026 — **verificar contra el
    calendario oficial antes de producción**, aquí son un punto de partida)
  - `ConfiguracionSeeder` (plazos: 10 días hábiles, notificar prórroga antes
    del día 8, 2 días hábiles para aclaraciones)
  - `UserSeeder` — crea 3 usuarios de prueba, incluyendo un Administrador
    con tu correo (`jersonmelendez123@gmail.com`) y contraseña `password`
  - `SolicitudDemoSeeder` — los 6 expedientes de ejemplo

## Cómo se validó (sin poder instalar Composer en este entorno)

Este entorno de trabajo en la nube no tiene salida de red hacia
**Packagist** (el repositorio de paquetes PHP), así que no pude ejecutar
`composer install` aquí — solo pude confirmar que PHP, Composer y
PostgreSQL 16 están disponibles, y que `github.com` sí es alcanzable (por
eso el esqueleto de `laravel/laravel` se obtuvo clonándolo directo de
GitHub en vez de vía Composer).

Para no entregarte migraciones "de fe", generé el diseño completo también
como SQL puro (`schema.sql`, incluido en este zip) y lo **ejecuté contra un
PostgreSQL 16 real en esta sesión**: las 26 tablas, llaves foráneas, checks
de los enums y los 3 usuarios/6 expedientes de prueba se crean sin errores.
Las migraciones de Laravel en `database/migrations/` están escritas
columna por columna a partir del mismo diseño ya validado, así que deberían
correr igual de limpio con `php artisan migrate` en tu máquina.

Lo único que **no** pude ejecutar aquí es el propio `php artisan migrate`
(porque necesita el framework instalado vía Composer). Es el primer
comando que debes correr en Laragon.

## Pasos para continuar en tu máquina (Laragon)

1. Instala/abre Laragon con PHP 8.2+ y PostgreSQL (o usa el PostgreSQL que
   ya tengas). Crea la base de datos:
   ```sql
   CREATE DATABASE uip_mingob;
   ```
2. Descomprime este proyecto y entra a la carpeta:
   ```bash
   composer install
   cp .env.example .env
   php artisan key:generate
   ```
3. Ajusta en `.env` el `DB_PASSWORD` (y usuario si aplica) según tu
   instalación local de PostgreSQL — ya dejé `DB_CONNECTION=pgsql` y el
   resto de valores por defecto.
4. Corre las migraciones y los datos de prueba:
   ```bash
   php artisan migrate --seed
   ```
5. Verifica que las 26 tablas existan:
   ```bash
   php artisan tinker
   >>> \App\Models\Solicitud::with('solicitante', 'estado')->get();
   ```
   Deberías ver los 6 expedientes de ejemplo (Juan Pérez, María González,
   Carlos Ramírez, Ana López, Luis Mendoza, Rosa Hernández).
6. El usuario administrador de prueba es:
   - correo: `jersonmelendez123@gmail.com`
   - contraseña: `password` (cámbiala antes de cualquier uso real)

## Siguiente fase

Con la base de datos en pie, lo natural es **Fase 3: Autenticación, roles y
permisos** — login del panel administrativo, middleware por rol/permiso
(ya existen las tablas `roles`, `permissions`, `role_permission` y las
columnas en `users`), y conectar el panel administrativo del prototipo
HTML a estas rutas reales. Cuando quieras seguimos con eso.

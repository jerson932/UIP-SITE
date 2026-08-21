{{--
  Notificaciones flotantes (Fase 22c, a pedido del usuario: "las
  notifiaciones no desaparecen en el sistema, quisiera que aparecieran y
  desaparezca en unos segundos"). Antes cada vista del panel admin repetía
  su propio bloque `@if (session('status'))` como una franja fija dentro
  del contenido, que se quedaba ahí hasta la siguiente navegación — ahora
  es un solo componente centralizado (incluido una vez desde
  layouts/admin.blade.php) que aparece flotando arriba a la derecha y se
  oculta solo después de unos segundos. session('acceso_generado') (la
  contraseña temporal que se muestra al crear el acceso de un enlace) NO
  pasa por aquí a propósito: esa sí debe quedarse visible hasta que el
  administrador la copie.
--}}
@if (session('status') || session('error'))
  <div class="toast-stack">
    @if (session('status'))
      <div class="toast toast-status">
        <span>{{ session('status') }}</span>
        <button type="button" class="toast-close" onclick="this.closest('.toast').remove()" aria-label="Cerrar">&times;</button>
      </div>
    @endif
    @if (session('error'))
      <div class="toast toast-error">
        <span>{{ session('error') }}</span>
        <button type="button" class="toast-close" onclick="this.closest('.toast').remove()" aria-label="Cerrar">&times;</button>
      </div>
    @endif
  </div>
  <script>
    (function () {
      document.querySelectorAll('.toast-stack .toast').forEach(function (el) {
        setTimeout(function () {
          el.classList.add('toast-hide');
          setTimeout(function () { el.remove(); }, 320);
        }, 4500);
      });
    })();
  </script>
@endif

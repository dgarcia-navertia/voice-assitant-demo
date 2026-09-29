<?php
// <head> comun a todas las paginas, con y sin sesion. $pageTitle es opcional.
// El ?v= cambia con cada `make css`, asi el navegador no se queda con un CSS viejo.
$cssVersion = @filemtime(__DIR__ . '/../../../public/static/app.css') ?: 0;
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#075056">
<title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : '' ?>Navertia Voice</title>
<link rel="icon" type="image/png" href="/static/favicon.png">
<link rel="apple-touch-icon" href="/static/apple-touch-icon.png">
<script>
// Tema claro/oscuro antes del primer pintado (evita el parpadeo). Sin
// preferencia guardada, sigue al sistema.
(function () {
    var t = null;
    try { t = localStorage.getItem('nv_theme'); } catch (e) {}
    if (t !== 'light' && t !== 'dark') {
        t = window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    document.documentElement.dataset.theme = t;
})();
</script>
<link rel="stylesheet" href="/static/app.css?v=<?= $cssVersion ?>">
<script defer src="/static/js/countries.js"></script>
<script defer src="/static/js/phone-picker.js"></script>
<script defer src="/static/js/alpine-3.14.9.min.js"></script>
<script defer src="/static/js/app.js"></script>

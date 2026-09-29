<!DOCTYPE html>
<html lang="es">
<head>
    <?php $pageTitle = '403'; require __DIR__ . '/../partials/head.php'; ?>
</head>
<body class="min-h-screen flex items-center justify-center">
<div class="text-center">
    <div class="text-6xl font-bold text-gray-200 mb-4">403</div>
    <h1 class="text-xl font-semibold text-gray-700 mb-2">Acceso denegado</h1>
    <p class="text-gray-500 text-sm mb-6">No tienes permisos para acceder a esta página.</p>
    <a href="/dashboard" class="text-brand-700 hover:text-brand-900 text-sm font-medium">
        Volver al dashboard
    </a>
</div>
</body>
</html>

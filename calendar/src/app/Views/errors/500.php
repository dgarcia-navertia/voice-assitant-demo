<!DOCTYPE html>
<html lang="es">
<head>
    <?php $pageTitle = '500'; require __DIR__ . '/../partials/head.php'; ?>
</head>
<body class="min-h-screen flex items-center justify-center">
<div class="text-center">
    <div class="text-6xl font-bold text-gray-200 mb-4">500</div>
    <h1 class="text-xl font-semibold text-gray-700 mb-2">Algo ha fallado</h1>
    <p class="text-gray-500 text-sm mb-6">
        No hemos podido completar la acción. Si vuelve a pasar, avisa al administrador
        con esta referencia: <code class="font-mono text-gray-700"><?= htmlspecialchars($reference ?? '') ?></code>
    </p>
    <a href="/dashboard" class="text-brand-700 hover:text-brand-900 text-sm font-medium">
        Volver al dashboard
    </a>
</div>
</body>
</html>

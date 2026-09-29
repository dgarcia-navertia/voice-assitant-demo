<!DOCTYPE html>
<html lang="es">
<head>
    <?php $pageTitle = 'Acceder'; require __DIR__ . '/../partials/head.php'; ?>
</head>
<body class="min-h-screen flex flex-col items-center justify-center px-4 py-10">

<div class="w-full max-w-sm">
    <div class="flex justify-center mb-10">
        <img src="/static/navertia-logo-onlight.png" alt="Navertia" class="logo-onlight h-9 w-auto">
        <img src="/static/navertia-logo-ondark.png" alt="Navertia" class="logo-ondark h-9 w-auto">
    </div>

    <div class="card p-8">
        <h1 class="text-2xl font-semibold text-center">Asistente de voz</h1>
        <p class="text-gray-500 text-center mt-1 mb-8">Entra con tu correo y tu contraseña</p>

        <?php if (!empty($error)): ?>
        <div class="mb-5 p-3 bg-red-50 border border-red-200 rounded-2xl text-red-800" role="alert">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="/login" class="space-y-5">
            <div>
                <label for="email" class="label">Correo electrónico</label>
                <input type="email" id="email" name="email" required autofocus autocomplete="username"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" class="input">
            </div>
            <div>
                <label for="password" class="label">Contraseña</label>
                <input type="password" id="password" name="password" required autocomplete="current-password" class="input">
            </div>
            <button type="submit" class="btn btn-primary w-full min-h-[3rem] text-base">Entrar</button>
        </form>
    </div>

    <p class="text-center mt-6">
        <button type="button" data-theme-toggle class="text-sm text-gray-500 hover:text-gray-900 underline underline-offset-4">Cambiar modo claro/oscuro</button>
    </p>
</div>

</body>
</html>

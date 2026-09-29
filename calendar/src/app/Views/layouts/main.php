<?php
use App\Icon;

$uri  = $_SERVER['REQUEST_URI'];
$role = $user['role'] ?? null;

// Seccion "Ajustes": pestañas de configuracion.
$settingsTabs = [
    '/users'       => ['label' => 'Usuarios',    'roles' => ['admin']],
    '/commercials' => ['label' => 'Comerciales', 'roles' => ['admin', 'manager']],
    '/stores'      => ['label' => 'Tiendas',     'roles' => ['admin']],
    '/holidays'    => ['label' => 'Festivos',    'roles' => ['admin']],
    '/settings'    => ['label' => 'Traspaso',    'roles' => ['admin']],
];
$inSettings = false;
foreach ($settingsTabs as $path => $_) {
    if (str_starts_with($uri, $path)) { $inSettings = true; break; }
}
$settingsHome = $role === 'admin' ? '/users' : '/commercials';

// [href, label, icon, active]. Misma lista en la barra lateral y en la barra inferior del movil.
$navItems = [
    ['/dashboard',    'Agenda',   'calendar', str_starts_with($uri, '/dashboard') || str_starts_with($uri, '/calendar')],
    ['/appointments', 'Citas',    'list',     str_starts_with($uri, '/appointments') || str_starts_with($uri, '/blocking-events')],
    ['/dial-out',     'Dial Out', 'phone',    str_starts_with($uri, '/dial-out')],
    ['/calls',        'Llamadas', 'chat',     str_starts_with($uri, '/calls')],
    ['/leads',        'Leads',    'flag',     str_starts_with($uri, '/leads')],
    ['/clients',      'Clientes', 'users',    str_starts_with($uri, '/clients')],
];
$settingsItem = in_array($role, ['admin', 'manager'], true)
    ? [$settingsHome, 'Ajustes', 'settings', $inSettings]
    : null;
$onAccount = str_starts_with($uri, '/account');
$roleLabel = ['admin' => 'Administrador', 'manager' => 'Manager', 'commercial' => 'Comercial'][$role] ?? '';

$sideLink = function (string $href, string $label, string $icon, bool $active): string {
    $cls = $active ? 'bg-brand-500/15 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-brand-500/10 hover:text-gray-900';
    return '<a href="' . $href . '" class="flex items-center gap-3 h-11 px-4 rounded-full text-[15px] transition ' . $cls . '"'
        . ($active ? ' aria-current="page"' : '') . '>'
        . '<span class="' . ($active ? 'text-brand-500' : 'text-gray-400') . '">' . Icon::svg($icon) . '</span>'
        . '<span class="flex-1">' . $label . '</span></a>';
};
$logo = '<img src="/static/navertia-logo-onlight.png" alt="Navertia" class="logo-onlight %1$s">'
      . '<img src="/static/navertia-logo-ondark.png" alt="Navertia" class="logo-ondark %1$s">';
$themeToggle = fn(string $cls = '') => '<button type="button" data-theme-toggle class="btn-icon ' . $cls . '" aria-label="Cambiar entre modo claro y oscuro" title="Modo claro/oscuro">'
    . '<span class="theme-moon">' . Icon::svg('moon') . '</span>'
    . '<span class="theme-sun">' . Icon::svg('sun') . '</span></button>';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require __DIR__ . '/../partials/head.php'; ?>
</head>
<body class="min-h-screen">

<?php /* Ordenador: barra lateral flotante de cristal, siempre a la vista. */ ?>
<aside class="hidden lg:flex fixed inset-y-3 left-3 z-30 w-64 flex-col glass-strong rounded-glass">
    <a href="/dashboard" class="flex items-center h-20 px-6 shrink-0" aria-label="Navertia - inicio">
        <?= sprintf($logo, 'h-7 w-auto') ?>
    </a>
    <nav class="flex-1 px-3 space-y-1 overflow-y-auto" aria-label="Principal">
        <?php foreach ($navItems as $item): ?>
        <?= $sideLink(...$item) ?>
        <?php endforeach; ?>
        <?php if ($settingsItem): ?>
        <?= $sideLink(...$settingsItem) ?>
        <?php endif; ?>
    </nav>
    <div class="p-3 border-t border-gray-200/70 space-y-1">
        <a href="/account" class="flex items-center gap-3 px-3 py-2 rounded-full <?= $onAccount ? 'bg-brand-500/15' : 'hover:bg-brand-500/10' ?>">
            <span class="w-9 h-9 rounded-full bg-primary text-white flex items-center justify-center text-sm font-semibold shrink-0">
                <?= htmlspecialchars(mb_strtoupper(mb_substr($user['name'] ?? '?', 0, 1))) ?>
            </span>
            <span class="min-w-0">
                <span class="block text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars($user['name'] ?? '') ?></span>
                <span class="block text-xs text-gray-500">Mi cuenta · <?= $roleLabel ?></span>
            </span>
        </a>
        <div class="flex items-center gap-2">
            <form method="POST" action="/logout" class="flex-1">
                <button type="submit" class="w-full flex items-center gap-3 h-11 px-4 rounded-full text-[15px] text-gray-600 hover:bg-brand-500/10 hover:text-gray-900">
                    <span class="text-gray-400"><?= Icon::svg('logout') ?></span>
                    Salir
                </button>
            </form>
            <?= $themeToggle('border-0 shadow-none') ?>
        </div>
    </div>
</aside>

<div class="lg:pl-72">
    <?php /* Movil y tableta: logo arriba; la navegacion va abajo, al alcance del pulgar. */ ?>
    <header class="lg:hidden sticky top-0 z-30 m-3 glass-strong rounded-full">
        <div class="flex items-center justify-between h-14 pl-5 pr-2">
            <a href="/dashboard" aria-label="Navertia - inicio"><?= sprintf($logo, 'h-5 w-auto') ?></a>
            <?= $themeToggle('border-0 shadow-none') ?>
        </div>
    </header>

    <?php if ($inSettings && $settingsItem): ?>
    <div class="px-4 sm:px-6 lg:px-8 max-w-6xl mx-auto pt-4 lg:pt-6">
        <div class="glass inline-flex max-w-full rounded-full p-1 gap-1 overflow-x-auto">
            <?php foreach ($settingsTabs as $path => $tab): ?>
            <?php if (!in_array($role, $tab['roles'], true)) { continue; } ?>
            <?php $active = str_starts_with($uri, $path); ?>
            <a href="<?= $path ?>"
               class="px-4 py-2 text-[15px] rounded-full whitespace-nowrap <?= $active ? 'bg-primary text-white font-semibold' : 'text-gray-600 hover:text-gray-900' ?>">
                <?= $tab['label'] ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 pb-32 lg:py-8">
        <?= $content ?>
    </main>
</div>

<?php /* Movil y tableta: barra inferior flotante; "Mas" abre el resto. */ ?>
<nav class="lg:hidden fixed bottom-3 inset-x-3 z-40 glass-strong rounded-full pb-[env(safe-area-inset-bottom)]"
     aria-label="Principal" x-data="{ more: false }" @keydown.escape.window="more = false">
    <div class="grid grid-cols-5">
        <?php foreach (array_slice($navItems, 0, 4) as [$href, $label, $icon, $active]): ?>
        <a href="<?= $href ?>" class="relative flex flex-col items-center justify-center gap-0.5 h-16 text-[11px] <?= $active ? 'text-brand-500 font-semibold' : 'text-gray-500' ?>"
           <?= $active ? 'aria-current="page"' : '' ?>>
            <?= Icon::svg($icon, 'w-6 h-6') ?>
            <?= $label ?>
        </a>
        <?php endforeach; ?>
        <button type="button" @click="more = true" :aria-expanded="more"
                class="relative flex flex-col items-center justify-center gap-0.5 h-16 text-[11px] text-gray-500">
            <?= Icon::svg('menu', 'w-6 h-6') ?>
            Más
        </button>
    </div>

    <div x-cloak x-show="more" x-transition.opacity class="fixed inset-0 z-50 bg-black/40" @click="more = false"></div>
    <div x-cloak x-show="more" x-transition
         class="fixed inset-x-3 bottom-3 z-50 glass-strong rounded-glass p-4 pb-[calc(1rem+env(safe-area-inset-bottom))]">
        <div class="flex items-center justify-between mb-2">
            <div>
                <p class="font-semibold"><?= htmlspecialchars($user['name'] ?? '') ?></p>
                <p class="text-sm text-gray-500"><?= $roleLabel ?></p>
            </div>
            <button type="button" @click="more = false" class="btn-icon border-0" aria-label="Cerrar"><?= Icon::svg('close', 'w-6 h-6') ?></button>
        </div>
        <?php foreach (array_slice($navItems, 4) as [$href, $label, $icon]): ?>
        <a href="<?= $href ?>" class="flex items-center gap-3 h-14 px-3 rounded-full text-base hover:bg-brand-500/10">
            <span class="text-gray-400"><?= Icon::svg($icon, 'w-6 h-6') ?></span> <?= $label ?>
        </a>
        <?php endforeach; ?>
        <?php if ($settingsItem): ?>
        <a href="<?= $settingsItem[0] ?>" class="flex items-center gap-3 h-14 px-3 rounded-full text-base hover:bg-brand-500/10">
            <span class="text-gray-400"><?= Icon::svg('settings', 'w-6 h-6') ?></span> Ajustes
        </a>
        <?php endif; ?>
        <a href="/account" class="flex items-center gap-3 h-14 px-3 rounded-full text-base hover:bg-brand-500/10">
            <span class="text-gray-400"><?= Icon::svg('user', 'w-6 h-6') ?></span> Mi cuenta
        </a>
        <form method="POST" action="/logout">
            <button type="submit" class="w-full flex items-center gap-3 h-14 px-3 rounded-full text-base hover:bg-brand-500/10">
                <span class="text-gray-400"><?= Icon::svg('logout', 'w-6 h-6') ?></span> Salir
            </button>
        </form>
    </div>
</nav>

</body>
</html>

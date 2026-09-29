<?php
use App\Icon;

$filters ??= [
    'date_from' => $date,
    'date_to' => $date,
    'status' => '',
    'type' => 'both',
    'store_id' => null,
    'commercial_id' => null,
];
$stores ??= [];
$attendants ??= [];
$today = date('Y-m-d');
$dateFrom = $filters['date_from'];
$dateTo = $filters['date_to'];
$rangeDays = max(1, (int) ((strtotime($dateTo) - strtotime($dateFrom)) / 86400) + 1);
$prevFrom = date('Y-m-d', strtotime($dateFrom . " -{$rangeDays} day"));
$prevTo = date('Y-m-d', strtotime($dateTo . " -{$rangeDays} day"));
$nextFrom = date('Y-m-d', strtotime($dateFrom . " +{$rangeDays} day"));
$nextTo = date('Y-m-d', strtotime($dateTo . " +{$rangeDays} day"));
$blocks ??= [];

$queryUrl = function (array $overrides = []) use ($filters): string {
    $params = array_merge($filters, $overrides);
    if (\App\Auth::isCommercial()) {
        unset($params['store_id'], $params['commercial_id']);
    } elseif (\App\Auth::isManager()) {
        unset($params['store_id']);
    }
    $params = array_filter($params, fn($value) => $value !== null && $value !== '' && $value !== 'both');
    return '/appointments' . ($params ? '?' . http_build_query($params) : '');
};
// El estado se elige en las pestañas de arriba; el panel "Filtros" lleva el resto.
$activeFilterCount = (int) ($dateFrom !== $dateTo)
    + (int) ($filters['type'] !== 'both')
    + (int) (\App\Auth::isAdmin() && $filters['store_id'] !== null)
    + (int) (!\App\Auth::isCommercial() && $filters['commercial_id'] !== null);
$hasActiveFilters = $activeFilterCount > 0 || $filters['status'] !== '';
// date('l')/date('F') dan los nombres en inglés, y la extensión intl no está en la imagen.
$weekdayNames = [1 => 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
$monthNames = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
    'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$fromTs = strtotime($dateFrom);
$rangeLabel = $dateFrom === $dateTo
    ? $weekdayNames[(int) date('N', $fromTs)] . ', ' . date('j', $fromTs) . ' de '
        . $monthNames[(int) date('n', $fromTs)] . ' de ' . date('Y', $fromTs)
    : date('j/m/Y', $fromTs) . ' – ' . date('j/m/Y', strtotime($dateTo));
$isToday = $dateFrom === $today && $dateTo === $today;

// Citas y bloqueos intercalados por hora de inicio.
$items = array_merge(
    array_map(fn($a) => ['type' => 'appointment', 'data' => $a], $appointments),
    array_map(fn($b) => ['type' => 'block', 'data' => $b], $blocks)
);
usort($items, fn($a, $b) => strtotime($a['data']['starts_at']) <=> strtotime($b['data']['starts_at']));

// Sin paginar, una semana con mucho volumen eran miles de tarjetas y una página de 2 MB.
$statusCounts ??= ['pending' => 0, 'confirmed' => 0, 'cancelled' => 0];
$perPage    = 50;
$totalItems = count($items);
$pages      = max(1, (int) ceil($totalItems / $perPage));
$page       = min(max(1, (int) ($page ?? 1)), $pages);
$pageItems  = array_slice($items, ($page - 1) * $perPage, $perPage);
$multiDay   = $dateFrom !== $dateTo;
$dayLabel   = function (string $datetime) use ($weekdayNames, $monthNames): string {
    $ts = strtotime($datetime);
    return $weekdayNames[(int) date('N', $ts)] . ' ' . date('j', $ts) . ' de ' . $monthNames[(int) date('n', $ts)];
};
// [color de la etiqueta, color del punto, texto]
$statusBadge = fn(string $status): array => match ($status) {
    'confirmed' => ['bg-green-50 text-green-800', 'bg-green-600', 'Confirmada'],
    'cancelled' => ['bg-gray-100 text-gray-600', 'bg-gray-400', 'Cancelada'],
    default     => ['bg-amber-50 text-amber-800', 'bg-amber-500', 'Pendiente'],
};
// Misma rejilla para cabecera y filas: en móvil hora | cliente | estado; desde md, tabla completa.
$rowGrid = 'grid grid-cols-[3.5rem_minmax(0,1fr)_auto] md:grid-cols-[4.5rem_minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1fr)_8.5rem] items-center gap-x-3 px-4';
?>

<div x-data="{ sync: false }">

<div class="flex flex-col gap-4 mb-5 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h1 class="text-2xl font-semibold">Citas</h1>
    </div>
    <div class="grid grid-cols-2 gap-2 sm:flex">
        <a href="/blocking-events/create" class="btn btn-secondary">
            <?= Icon::svg('block') ?> Bloquear horario
        </a>
        <a href="/appointments/create" class="btn btn-primary">
            <?= Icon::svg('plus') ?> Nueva cita
        </a>
    </div>
</div>

<?php /* Cambiar de día: flechas grandes, la fecha en medio y "Hoy" para volver. */ ?>
<div class="card p-2 mb-4 flex flex-wrap items-center gap-2">
    <a href="<?= $queryUrl(['date_from' => $prevFrom, 'date_to' => $prevTo]) ?>" class="btn-icon"
       aria-label="<?= $multiDay ? 'Periodo anterior' : 'Día anterior' ?>" title="<?= $multiDay ? 'Periodo anterior' : 'Día anterior' ?>">
        <?= Icon::svg('left') ?>
    </a>
    <?php /* La fecha se lee en español; al pulsarla se abre el selector del propio móvil u ordenador. */ ?>
    <div class="relative flex-1 min-w-0">
        <button type="button" onclick="const d = this.nextElementSibling; d.showPicker ? d.showPicker() : d.focus()"
                class="w-full min-h-[2.75rem] px-3 rounded-lg hover:bg-gray-50 flex items-center justify-center gap-2 font-semibold">
            <span class="text-brand-600"><?= Icon::svg('calendar') ?></span>
            <?php if ($multiDay): ?>
            <span class="truncate"><?= htmlspecialchars($rangeLabel) ?></span>
            <?php else: ?>
            <span class="truncate">
                <?= $isToday ? 'Hoy · ' : '' ?><span class="sm:hidden"><?= htmlspecialchars(mb_substr($weekdayNames[(int) date('N', $fromTs)], 0, 3) . ' ' . date('j', $fromTs) . ' ' . mb_substr($monthNames[(int) date('n', $fromTs)], 0, 3)) ?></span><span class="hidden sm:inline"><?= htmlspecialchars($rangeLabel) ?></span>
            </span>
            <?php endif; ?>
        </button>
        <input type="date" value="<?= htmlspecialchars($dateFrom) ?>" tabindex="-1" aria-label="Elegir fecha"
               onchange="if (this.value) location.href='<?= $queryUrl(['date_from' => '__DATE__', 'date_to' => '__DATE__']) ?>'.replaceAll('__DATE__', this.value)"
               class="absolute inset-x-0 bottom-0 h-0 opacity-0 pointer-events-none">
    </div>
    <a href="<?= $queryUrl(['date_from' => $nextFrom, 'date_to' => $nextTo]) ?>" class="btn-icon"
       aria-label="<?= $multiDay ? 'Periodo siguiente' : 'Día siguiente' ?>" title="<?= $multiDay ? 'Periodo siguiente' : 'Día siguiente' ?>">
        <?= Icon::svg('right') ?>
    </a>
    <a href="<?= $queryUrl(['date_from' => $today, 'date_to' => $today]) ?>"
       class="btn btn-secondary <?= $isToday ? 'hidden' : '' ?>">Hoy</a>
</div>

<?php /* Estado: un recuadro por estado con su número, que filtra al pulsarlo. Filtros: el resto, plegado. */ ?>
<?php if ($filters['type'] !== 'blocks'): ?>
<div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-3">
    <?php foreach (['' => 'Todas', 'pending' => 'Pendientes', 'confirmed' => 'Confirmadas', 'cancelled' => 'Canceladas'] as $value => $label): ?>
    <?php
    $count  = $value === '' ? array_sum($statusCounts) : $statusCounts[$value];
    $active = $filters['status'] === $value;
    $dot    = match ($value) { 'pending' => 'bg-amber-500', 'confirmed' => 'bg-green-600', 'cancelled' => 'bg-gray-400', default => 'bg-primary' };
    ?>
    <a href="<?= $queryUrl(['status' => $value]) ?>" <?= $active ? 'aria-current="true"' : '' ?>
       class="flex sm:block items-baseline gap-2 rounded-xl border px-4 py-2.5 sm:py-3 <?= $active ? 'border-primary bg-primary text-white' : 'border-gray-200 bg-surface hover:border-gray-400' ?>">
        <span class="block text-xl sm:text-2xl font-semibold tabular-nums leading-tight"><?= $count ?></span>
        <span class="flex items-center gap-1.5 text-sm truncate <?= $active ? 'text-white/80' : 'text-gray-600' ?>">
            <span class="w-2 h-2 rounded-full <?= $dot ?>"></span><?= $label ?>
        </span>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="mb-4 flex flex-wrap items-center justify-end gap-2" x-data="{ open: <?= $activeFilterCount > 0 ? 'true' : 'false' ?> }">
    <button type="button" @click="open = !open" :aria-expanded="open" aria-controls="filters-body" class="btn btn-secondary">
        <?= Icon::svg('filter') ?> Filtros
        <?php if ($activeFilterCount > 0): ?>
        <span class="min-w-[1.25rem] h-5 px-1 rounded-full bg-brand-500 text-white text-xs font-bold flex items-center justify-center"><?= $activeFilterCount ?></span>
        <?php endif; ?>
        <span class="transition-transform" :class="open && 'rotate-180'"><?= Icon::svg('down', 'w-4 h-4') ?></span>
    </button>

    <form id="filters-body" method="GET" action="/appointments" x-cloak x-show="open" x-transition class="card p-4 w-full">
        <?php if ($filters['status'] !== ''): ?>
        <input type="hidden" name="status" value="<?= htmlspecialchars($filters['status']) ?>">
        <?php endif; ?>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="date_from" class="label">Desde</label>
                <input id="date_from" name="date_from" type="date" value="<?= htmlspecialchars($dateFrom) ?>" class="input">
            </div>
            <div>
                <label for="date_to" class="label">Hasta</label>
                <input id="date_to" name="date_to" type="date" value="<?= htmlspecialchars($dateTo) ?>" class="input">
            </div>
            <div>
                <label for="type" class="label">Mostrar</label>
                <select id="type" name="type" class="input">
                    <option value="both" <?= $filters['type'] === 'both' ? 'selected' : '' ?>>Citas y bloqueos</option>
                    <option value="appointments" <?= $filters['type'] === 'appointments' ? 'selected' : '' ?>>Solo citas</option>
                    <option value="blocks" <?= $filters['type'] === 'blocks' ? 'selected' : '' ?>>Solo bloqueos</option>
                </select>
            </div>
            <?php if (\App\Auth::isAdmin()): ?>
            <div>
                <label for="store_id" class="label">Tienda</label>
                <select id="store_id" name="store_id" class="input">
                    <option value="">Todas las tiendas</option>
                    <?php foreach ($stores as $store): ?>
                    <option value="<?= (int) $store['id'] ?>" <?= (int) $filters['store_id'] === (int) $store['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($store['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <?php if (!\App\Auth::isCommercial()): ?>
            <div>
                <label for="commercial_id" class="label">Atiende</label>
                <select id="commercial_id" name="commercial_id" class="input">
                    <option value="">Todas las personas</option>
                    <?php foreach ($attendants as $attendant): ?>
                    <option value="<?= (int) $attendant['id'] ?>" <?= (int) $filters['commercial_id'] === (int) $attendant['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($attendant['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
        <div class="mt-4 grid grid-cols-2 gap-2 sm:flex sm:justify-end">
            <a href="/appointments" class="btn btn-secondary">Quitar filtros</a>
            <button type="submit" class="btn btn-primary">Aplicar</button>
        </div>
    </form>
</div>

<?php if (empty($items)): ?>
<div class="card px-6 py-16 text-center">
    <div class="mx-auto w-12 h-12 rounded-full bg-gray-100 text-gray-400 flex items-center justify-center mb-4"><?= Icon::svg('calendar', 'w-6 h-6') ?></div>
    <p class="font-medium"><?= $hasActiveFilters ? 'No hay resultados con estos filtros' : 'No hay citas este día' ?></p>
    <p class="text-gray-500 mt-1 mb-6">
        <?= $hasActiveFilters ? 'Prueba a quitar los filtros o elegir otra fecha.' : 'Cambia de día con las flechas o crea una cita nueva.' ?>
    </p>
    <?php if ($hasActiveFilters): ?>
    <a href="/appointments" class="btn btn-secondary">Quitar filtros</a>
    <?php elseif ($filters['type'] === 'blocks'): ?>
    <a href="/blocking-events/create" class="btn btn-primary"><?= Icon::svg('block') ?> Bloquear horario</a>
    <?php else: ?>
    <a href="/appointments/create" class="btn btn-primary"><?= Icon::svg('plus') ?> Nueva cita</a>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="card overflow-hidden">
    <div class="<?= $rowGrid ?> hidden md:grid py-2.5 bg-gray-50 border-b border-gray-200 text-sm font-medium text-gray-500">
        <div>Hora</div>
        <div>Cliente</div>
        <div>Servicio</div>
        <div>Atiende</div>
        <div class="text-right">Estado</div>
    </div>
    <?php $currentDay = null; ?>
    <?php foreach ($pageItems as $item): ?>
    <?php $row = $item['data']; ?>
    <?php if ($multiDay && $currentDay !== substr($row['starts_at'], 0, 10)): ?>
    <?php $currentDay = substr($row['starts_at'], 0, 10); ?>
    <div class="px-4 py-2 bg-gray-50 border-b border-gray-200 text-sm font-semibold text-gray-700">
        <?= htmlspecialchars($dayLabel($row['starts_at'])) ?>
    </div>
    <?php endif; ?>

    <?php if ($item['type'] === 'block'): ?>
    <div class="<?= $rowGrid ?> py-3 border-b border-gray-100 last:border-b-0 bg-gray-50 text-gray-600">
        <div class="tabular-nums">
            <div class="font-semibold"><?= $row['all_day'] ? '—' : date('H:i', strtotime($row['starts_at'])) ?></div>
            <div class="text-sm text-gray-500"><?= $row['all_day'] ? 'Todo el día' : 'a ' . date('H:i', strtotime($row['ends_at'])) ?></div>
        </div>
        <div class="min-w-0">
            <div class="font-medium truncate">Horario bloqueado</div>
            <div class="text-sm text-gray-500 truncate">
                <span class="md:hidden"><?= htmlspecialchars($row['commercial_name']) ?><?= !empty($row['reason']) ? ' · ' : '' ?></span><?= htmlspecialchars($row['reason'] ?? '') ?>
            </div>
        </div>
        <div class="hidden md:block"></div>
        <div class="hidden md:block truncate"><?= htmlspecialchars($row['commercial_name']) ?></div>
        <div class="flex items-center justify-end gap-1">
            <span class="hidden sm:inline-flex items-center gap-1.5 bg-gray-200 text-gray-700 text-sm px-2.5 py-0.5 rounded-full font-medium">Bloqueo</span>
            <form method="POST" action="/blocking-events/<?= $row['id'] ?>"
                  onsubmit="return confirm('¿Eliminar este bloqueo?')">
                <input type="hidden" name="_method" value="DELETE">
                <button type="submit" class="w-10 h-10 flex items-center justify-center text-gray-400 hover:text-red-700 hover:bg-red-50 rounded-lg"
                        title="Eliminar bloqueo" aria-label="Eliminar bloqueo">
                    <?= Icon::svg('trash') ?>
                </button>
            </form>
        </div>
    </div>
    <?php continue; ?>
    <?php endif; ?>

    <?php [$badgeCls, $dotCls, $badgeLabel] = $statusBadge($row['status']); ?>
    <?php $cancelled = $row['status'] === 'cancelled'; ?>
    <a href="/appointments/<?= $row['id'] ?>"
       class="<?= $rowGrid ?> py-3 border-b border-gray-100 last:border-b-0 hover:bg-brand-50/60 <?= $cancelled ? 'text-gray-400' : '' ?>">
        <div class="tabular-nums">
            <div class="font-semibold <?= $cancelled ? 'line-through' : '' ?>"><?= date('H:i', strtotime($row['starts_at'])) ?></div>
            <div class="text-sm text-gray-500"><?= (int) $row['duration_minutes'] ?> min</div>
        </div>
        <div class="min-w-0">
            <div class="font-medium truncate"><?= htmlspecialchars($row['client_name']) ?></div>
            <div class="text-sm text-gray-500 truncate">
                <span class="md:hidden"><?= htmlspecialchars($row['commercial_name']) ?><?= !empty($row['service_name']) ? ' · ' . htmlspecialchars($row['service_name']) : '' ?></span>
                <span class="hidden md:inline tabular-nums"><?= htmlspecialchars($row['client_phone']) ?></span>
            </div>
        </div>
        <div class="hidden md:block text-gray-700 truncate"><?= htmlspecialchars($row['service_name'] ?? '') ?></div>
        <div class="hidden md:block min-w-0">
            <div class="text-gray-700 truncate"><?= htmlspecialchars($row['commercial_name']) ?></div>
            <?php if (!\App\Auth::isCommercial()): ?>
            <div class="text-sm text-gray-500 truncate"><?= htmlspecialchars($row['store_name']) ?></div>
            <?php endif; ?>
        </div>
        <div class="flex flex-col md:flex-row items-end md:items-center justify-end gap-1">
            <?php if ($row['has_conflict']): ?>
            <span class="bg-red-50 text-red-700 text-sm px-2.5 py-0.5 rounded-full font-medium">Conflicto</span>
            <?php endif; ?>
            <span class="inline-flex items-center gap-1.5 <?= $badgeCls ?> text-sm px-2.5 py-0.5 rounded-full font-medium">
                <span class="w-1.5 h-1.5 rounded-full <?= $dotCls ?>"></span><?= $badgeLabel ?>
            </span>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<?php if ($pages > 1): ?>
<nav class="mt-4 flex items-center justify-between gap-2" aria-label="Páginas">
    <a href="<?= $queryUrl(['page' => $page - 1]) ?>" class="btn btn-secondary <?= $page === 1 ? 'invisible' : '' ?>">
        <?= Icon::svg('left') ?> Anterior
    </a>
    <p class="text-center text-gray-600">
        Página <?= $page ?> de <?= $pages ?>
        <span class="block text-sm text-gray-400"><?= ($page - 1) * $perPage + 1 ?>–<?= min($page * $perPage, $totalItems) ?> de <?= $totalItems ?></span>
    </p>
    <a href="<?= $queryUrl(['page' => $page + 1]) ?>" class="btn btn-secondary <?= $page === $pages ? 'invisible' : '' ?>">
        Siguiente <?= Icon::svg('right') ?>
    </a>
</nav>
<?php endif; ?>
<?php endif; ?>

</div>

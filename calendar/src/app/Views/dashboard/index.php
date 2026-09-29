<?php
use App\Icon;

$monthLabels = [
    1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may', 6 => 'jun',
    7 => 'jul', 8 => 'ago', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic',
];
$weekdayLabels = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
$fullWeekdays = [
    1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves',
    5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo',
];

$tomorrow = date('Y-m-d', strtotime("{$today} +1 day"));
$humanDay = function (string $d) use ($today, $tomorrow, $fullWeekdays, $monthLabels): string {
    if ($d === $today)    return 'Hoy';
    if ($d === $tomorrow) return 'Mañana';
    return $fullWeekdays[(int) date('N', strtotime($d))] . ' ' . date('j', strtotime($d)) . ' ' . $monthLabels[(int) date('n', strtotime($d))];
};

$statusStyles = function (string $status): array {
    return match ($status) {
        'confirmed' => ['card' => 'bg-green-50 border-green-200',  'badge' => 'bg-green-100 text-green-700',  'label' => 'Confirmada'],
        'cancelled' => ['card' => 'bg-gray-50 border-gray-200 opacity-60', 'badge' => 'bg-gray-100 text-gray-500', 'label' => 'Cancelada'],
        default     => ['card' => 'bg-yellow-50 border-yellow-200', 'badge' => 'bg-yellow-100 text-yellow-700', 'label' => 'Pendiente'],
    };
};

$listDate = $date;
$prev = date('Y-m-d', strtotime("{$listDate} -1 day"));
$next = date('Y-m-d', strtotime("{$listDate} +1 day"));
$isToday = $listDate === $today;

$w = $week;
$sunday = date('Y-m-d', strtotime("{$w['monday']} +6 days"));
$rangeLabel = (int) date('n', strtotime($w['monday'])) === (int) date('n', strtotime($sunday))
    ? date('j', strtotime($w['monday'])) . '–' . date('j', strtotime($sunday)) . ' ' . $monthLabels[(int) date('n', strtotime($sunday))] . ' ' . date('Y', strtotime($sunday))
    : date('j', strtotime($w['monday'])) . ' ' . $monthLabels[(int) date('n', strtotime($w['monday']))] . ' – ' . date('j', strtotime($sunday)) . ' ' . $monthLabels[(int) date('n', strtotime($sunday))] . ' ' . date('Y', strtotime($sunday));
$isThisWeek = $w['monday'] === $w['thisMonday'];

$keepUser = $agentId !== null ? '&user=' . $agentId : '';

// Botón con la fecha en español que abre el selector nativo, como en Citas.
$datePicker = function (string $label, string $value, string $urlPrefix, string $aria, ?string $shortLabel = null) use ($keepUser): string {
    $text = $shortLabel === null
        ? htmlspecialchars($label)
        : '<span class="sm:hidden">' . htmlspecialchars($shortLabel) . '</span><span class="hidden sm:inline">' . htmlspecialchars($label) . '</span>';
    return '<div class="relative flex-1 min-w-0">'
        . '<button type="button" onclick="const d = this.nextElementSibling; d.showPicker ? d.showPicker() : d.focus()"'
        . ' class="w-full min-h-[2.75rem] px-3 rounded-lg hover:bg-gray-50 flex items-center justify-center gap-2 font-semibold">'
        . '<span class="text-brand-600">' . Icon::svg('calendar') . '</span><span class="truncate">' . $text . '</span></button>'
        . '<input type="date" value="' . $value . '" tabindex="-1" aria-label="' . $aria . '"'
        . ' onchange="if (this.value) location.href = \'' . $urlPrefix . '\' + this.value + \'' . $keepUser . '\'"'
        . ' class="absolute inset-x-0 bottom-0 h-0 opacity-0 pointer-events-none"></div>';
};

$agentName = null;
foreach ($agents as $a) {
    if ($agentId === (int) $a['id']) { $agentName = $a['name']; break; }
}

// --- Time-grid helpers (calendar view) ---
// 80 px per hour: a 15-minute appointment gets 20 px, enough for one line.
$HOUR_PX   = 80;
$gridStart = $w['startHour'] * 60;
$gridEnd   = $w['endHour'] * 60;
$gridPx    = ($w['endHour'] - $w['startHour']) * $HOUR_PX;
$nowMin    = (int) date('G') * 60 + (int) date('i');

$toMin = fn (string $hhmm): int => (int) substr($hhmm, 0, 2) * 60 + (int) substr($hhmm, 3, 2);

$offsetPx = function (int $minsOfDay) use ($gridStart, $gridEnd, $HOUR_PX): float {
    $m = max($gridStart, min($gridEnd, $minsOfDay));
    return ($m - $gridStart) / 60 * $HOUR_PX;
};

$withMinutes = function (array $apt): array {
    $apt['_start'] = (int) date('G', strtotime($apt['starts_at'])) * 60 + (int) date('i', strtotime($apt['starts_at']));
    $apt['_end']   = $apt['_start'] + (int) $apt['duration_minutes'];
    return $apt;
};

// Places the live (non-cancelled) appointments of a day in lanes so overlapping
// ones sit side by side. Lanes are counted per cluster of mutually overlapping
// appointments, not per day: one overlap at 08:30 must not halve every card
// of the afternoon. Cancelled ones take no lane; they are drawn as markers.
$layout = function (array $appts) use ($withMinutes): array {
    $appts = array_map($withMinutes, array_values(array_filter($appts, fn ($a) => $a['status'] !== 'cancelled')));
    usort($appts, fn ($a, $b) => [$a['_start'], $a['_end']] <=> [$b['_start'], $b['_end']]);

    $placed = [];
    $cluster = [];
    $laneEnds = [];
    $clusterEnd = -1;
    $close = function () use (&$cluster, &$laneEnds, &$placed): void {
        foreach ($cluster as $apt) {
            $apt['_lanes'] = max(1, count($laneEnds));
            $placed[] = $apt;
        }
        $cluster = [];
        $laneEnds = [];
    };
    foreach ($appts as $apt) {
        if ($apt['_start'] >= $clusterEnd) { $close(); }
        $lane = null;
        foreach ($laneEnds as $i => $end) {
            if ($apt['_start'] >= $end) { $lane = $i; break; }
        }
        $lane ??= count($laneEnds);
        $laneEnds[$lane] = $apt['_end'];
        $apt['_lane'] = $lane;
        $cluster[] = $apt;
        $clusterEnd = max($clusterEnd, $apt['_end']);
    }
    $close();
    return $placed;
};

$aptClass = fn (string $status): string => match ($status) {
    'confirmed' => 'bg-brand-500 text-white',
    default     => 'bg-brand-50 text-brand-900 ring-1 ring-brand-300',
};

$statusLabel = fn (string $status): string => match ($status) {
    'confirmed' => 'Confirmada',
    'cancelled' => 'Cancelada',
    default     => 'Pendiente',
};

$aptTitle = function (array $apt) use ($statusLabel): string {
    $from = date('H:i', strtotime($apt['starts_at']));
    $to   = date('H:i', strtotime($apt['starts_at']) + (int) $apt['duration_minutes'] * 60);
    $lines = ["{$from}–{$to} · " . $statusLabel($apt['status']), $apt['client_name']];
    if (!empty($apt['service_name'])) { $lines[] = $apt['service_name']; }
    if (!empty($apt['client_phone'])) { $lines[] = $apt['client_phone']; }
    return implode("\n", $lines);
};

// On a phone the grid shows one day at a time: the ?day asked for, else today
// if it is in this week, else Monday.
$selectedDay = 0;
foreach ($w['days'] as $i => $day) {
    if ($day['date'] === $today) { $selectedDay = $i; }
}
foreach ($w['days'] as $i => $day) {
    if ($day['date'] === ($dayParam ?? null)) { $selectedDay = $i; }
}

// Tira deslizable del móvil: la semana que se ve más la anterior y la siguiente,
// y no más. Los días de esta semana cambian sin recargar; los de las otras dos
// cargan su semana con ese día abierto.
$stripDays = [];
for ($k = -7; $k < 14; $k++) {
    $d = date('Y-m-d', strtotime("{$w['monday']} {$k} days"));
    $stripDays[] = ['date' => $d, 'index' => ($k >= 0 && $k < 7) ? $k : null];
}

$cancelledCount = 0;
foreach ($w['days'] as $day) {
    foreach ($day['appointments'] as $apt) {
        if ($apt['status'] === 'cancelled') { $cancelledCount++; }
    }
}

?>

<script>
(function () {
    var m = new URLSearchParams(location.search).get('view');
    try {
        if (m === 'list' || m === 'calendar') { localStorage.setItem('agenda_view_mode', m); }
        else { m = localStorage.getItem('agenda_view_mode'); }
    } catch (e) {}
    document.documentElement.dataset.agendaView = (m === 'calendar') ? 'calendar' : 'list';
})();
</script>
<style>
    html[data-agenda-view="list"] #agenda-calendar { display: none; }
    html[data-agenda-view="calendar"] #agenda-list { display: none; }
    .agenda-cols { grid-template-columns: 3.5rem repeat(7, minmax(0, 1fr)); }
    html:not([data-show-cancelled]) .agenda-cancelled { display: none; }
    @media (max-width: 767px) {
        .agenda-cols { grid-template-columns: 3rem minmax(0, 1fr); }
        #agenda-calendar [data-day]:not(.is-selected) { display: none; }
    }
</style>

<div class="flex items-center justify-between gap-3 mb-5">
    <h1 class="text-2xl font-semibold">Agenda</h1>
    <div id="agenda-view-toggle" class="inline-flex rounded-lg border border-gray-300 bg-surface p-1">
        <?php foreach (['list' => 'Lista', 'calendar' => 'Calendario'] as $mode => $label): ?>
        <button type="button" data-view="<?= $mode ?>"
                class="min-h-[2.25rem] px-4 text-[15px] font-semibold rounded-md <?= $viewMode === $mode ? 'bg-brand-500 text-white' : 'text-gray-600' ?>"><?= $label ?></button>
        <?php endforeach; ?>
    </div>
</div>

<?php if (!empty($agents)): ?>
<div class="mb-5 flex items-center gap-3">
    <label for="agenda-agent" class="text-gray-600 shrink-0">Agenda de</label>
    <select id="agenda-agent"
            onchange="var p = new URLSearchParams(location.search); this.value ? p.set('user', this.value) : p.delete('user'); location.search = p.toString();"
            class="input sm:max-w-xs min-w-0 flex-1 sm:flex-none">
        <option value="">Todas las citas</option>
        <?php foreach ($agents as $agent): ?>
        <option value="<?= (int) $agent['id'] ?>" <?= $agentId === (int) $agent['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($agent['name']) ?><?= !empty($agent['store_name']) ? ' · ' . htmlspecialchars($agent['store_name']) : '' ?>
        </option>
        <?php endforeach; ?>
    </select>
</div>
<?php endif; ?>

<section id="agenda-list">
    <div class="flex flex-col-reverse sm:flex-row gap-2 mb-5">
        <div class="card p-2 flex items-center gap-2 flex-1">
            <a href="/dashboard?date=<?= $prev . $keepUser ?>" class="btn-icon" aria-label="Día anterior" title="Día anterior"><?= Icon::svg('left') ?></a>
            <?= $datePicker($humanDay($listDate) . ($isToday ? ', ' . date('j', strtotime($listDate)) . ' ' . $monthLabels[(int) date('n', strtotime($listDate))] : ''), $listDate, '/dashboard?date=', 'Ir a una fecha') ?>
            <a href="/dashboard?date=<?= $next . $keepUser ?>" class="btn-icon" aria-label="Día siguiente" title="Día siguiente"><?= Icon::svg('right') ?></a>
            <?php if (!$isToday): ?>
            <a href="/dashboard?date=<?= $today . $keepUser ?>" class="btn btn-secondary">Hoy</a>
            <?php endif; ?>
        </div>
        <a href="/appointments/create" class="btn btn-primary sm:self-center"><?= Icon::svg('plus') ?> Nueva cita</a>
    </div>

    <?php foreach ($listDays as $group): ?>
    <div class="mb-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-2">
            <?= htmlspecialchars($humanDay($group['date'])) ?>
            <span class="ml-1 text-xs font-normal text-gray-400"><?= date('j', strtotime($group['date'])) ?> <?= $monthLabels[(int) date('n', strtotime($group['date']))] ?></span>
        </h2>

        <?php if (empty($group['appointments'])): ?>
        <p class="text-sm text-gray-400 py-4">No hay citas para este día.</p>
        <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($group['appointments'] as $apt): ?>
            <?php $s = $statusStyles($apt['status']); ?>
            <a href="/appointments/<?= $apt['id'] ?>"
               class="flex items-center gap-4 p-4 rounded-xl border <?= $s['card'] ?> hover:shadow-sm transition-shadow">
                <div class="text-center min-w-[52px]">
                    <div class="text-lg font-semibold text-gray-900"><?= date('H:i', strtotime($apt['starts_at'])) ?></div>
                    <div class="text-xs text-gray-400"><?= $apt['duration_minutes'] ?> min</div>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-medium text-gray-900 truncate"><?= htmlspecialchars($apt['client_name']) ?></div>
                    <div class="text-sm text-gray-500 truncate"><?= htmlspecialchars($apt['client_phone']) ?></div>
                </div>
                <div class="text-right">
                    <div class="text-sm text-gray-600"><?= htmlspecialchars($apt['commercial_name']) ?></div>
                    <div class="text-xs text-gray-400"><?= htmlspecialchars($apt['store_name']) ?></div>
                </div>
                <div class="flex items-center gap-1.5">
                    <?php if ($apt['has_conflict']): ?>
                    <span class="bg-red-100 text-red-600 text-xs px-2 py-0.5 rounded-full font-medium">Conflicto</span>
                    <?php endif; ?>
                    <span class="<?= $s['badge'] ?> text-xs px-2 py-0.5 rounded-full font-medium"><?= $s['label'] ?></span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</section>

<section id="agenda-calendar">
    <div class="card p-2 mb-4 flex items-center gap-2">
        <a href="/dashboard?view=calendar&week=<?= $w['prevWeek'] . $keepUser ?>" class="btn-icon" aria-label="Semana anterior" title="Semana anterior"><?= Icon::svg('left') ?></a>
        <?= $datePicker($rangeLabel . ($isThisWeek ? ' · esta semana' : ''), $w['monday'], '/dashboard?view=calendar&week=', 'Ir a la semana de una fecha', preg_replace('/ \d{4}$/', '', $rangeLabel)) ?>
        <a href="/dashboard?view=calendar&week=<?= $w['nextWeek'] . $keepUser ?>" class="btn-icon" aria-label="Semana siguiente" title="Semana siguiente"><?= Icon::svg('right') ?></a>
        <?php if (!$isThisWeek): ?>
        <a href="/dashboard?view=calendar<?= $keepUser ?>" class="btn btn-secondary">Hoy</a>
        <?php endif; ?>
    </div>

    <?php if (!$w['isBookable']): ?>
    <div class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-sm text-yellow-800">
        <?php if ($agentName !== null): ?>
        <?= htmlspecialchars($agentName) ?> no está dado de alta como agente reservable, así que no se muestran huecos libres.
        <?php else: ?>
        No estás dado de alta como agente reservable, así que no se muestran huecos libres. Se listan solo las citas asignadas a tu usuario.
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div id="agenda-strip" class="md:hidden flex gap-1.5 overflow-x-auto snap-x -mx-4 px-4 pb-2 mb-1 [scrollbar-width:none]"
         role="tablist" aria-label="Día">
        <?php foreach ($stripDays as $sd): ?>
        <?php
        $sdTs      = strtotime($sd['date']);
        $inWeek    = $sd['index'] !== null;
        $isSel     = $inWeek && $sd['index'] === $selectedDay;
        $dayIsToday = $sd['date'] === $today;
        $live      = $inWeek ? count(array_filter($w['days'][$sd['index']]['appointments'], fn ($a) => $a['status'] !== 'cancelled')) : null;
        $cls       = 'snap-center shrink-0 w-14 py-2 rounded-xl border text-center '
                   . ($isSel ? 'bg-primary border-primary text-white' : 'bg-surface border-gray-200 ' . ($inWeek ? 'text-gray-800' : 'text-gray-400'));
        ?>
        <?php if ($inWeek): ?>
        <button type="button" role="tab" data-day-tab="<?= $sd['index'] ?>" aria-selected="<?= $isSel ? 'true' : 'false' ?>" class="<?= $cls ?>">
        <?php else: ?>
        <a href="/dashboard?view=calendar&week=<?= $sd['date'] ?>&day=<?= $sd['date'] . $keepUser ?>" data-strip-link="<?= $sd['date'] ?>" class="<?= $cls ?>">
        <?php endif; ?>
            <span class="block text-xs leading-none opacity-80"><?= $weekdayLabels[(int) date('N', $sdTs) - 1] ?></span>
            <span class="block text-lg font-semibold leading-tight mt-0.5 <?= $dayIsToday && !$isSel ? 'text-brand-700' : '' ?>"><?= date('j', $sdTs) ?></span>
            <span class="block text-[11px] leading-none <?= $isSel ? 'text-white/70' : 'text-gray-400' ?>">
                <?= $dayIsToday ? 'hoy' : ($live === null ? mb_substr($monthLabels[(int) date('n', $sdTs)], 0, 3) : ($live ?: '·')) ?>
            </span>
        <?= $inWeek ? '</button>' : '</a>' ?>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <div class="hidden md:grid agenda-cols sticky top-0 z-30 bg-surface rounded-t-xl">
            <div class="border-b border-gray-200"></div>
            <?php foreach ($w['days'] as $i => $day): ?>
            <?php $dayIsToday = $day['date'] === $today; ?>
            <div data-day="<?= $i ?>" class="<?= $i === $selectedDay ? 'is-selected' : '' ?> px-2 py-2 border-l border-b border-gray-200 <?= $dayIsToday ? 'border-t-2 border-t-brand-500' : '' ?>">
                <div class="text-lg font-semibold leading-none <?= $dayIsToday ? 'text-brand-700' : 'text-gray-800' ?>"><?= date('d', strtotime($day['date'])) ?></div>
                <div class="text-xs text-gray-500"><?= $weekdayLabels[$i] ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="grid agenda-cols isolate pt-3 pb-2">
            <div class="relative" style="height: <?= $gridPx ?>px">
                <?php for ($h = $w['startHour']; $h <= $w['endHour']; $h++): ?>
                <div class="absolute right-1 -translate-y-1/2 text-[11px] text-gray-400 tabular-nums"
                     style="top: <?= ($h - $w['startHour']) * $HOUR_PX ?>px"><?= sprintf('%02d:00', $h) ?></div>
                <?php endfor; ?>
            </div>

            <?php foreach ($w['days'] as $i => $day): ?>
            <?php
            $dayIsToday = $day['date'] === $today;
            $appts = $layout($day['appointments']);
            $cancelled = array_map($withMinutes, array_values(array_filter($day['appointments'], fn ($a) => $a['status'] === 'cancelled')));
            // Grey out the parts of the visible window that are NOT free to book
            // (outside schedule, gaps between shifts, already booked, blocked, past).
            $greyBands = [];
            $cursor = $gridStart;
            foreach ($day['freeRanges'] as $fr) {
                $f = $toMin($fr['from']);
                $tt = $toMin($fr['to']);
                if ($f > $cursor) { $greyBands[] = [$cursor, min($f, $gridEnd)]; }
                $cursor = max($cursor, $tt);
            }
            if ($cursor < $gridEnd) { $greyBands[] = [$cursor, $gridEnd]; }
            ?>
            <div data-day="<?= $i ?>" class="<?= $i === $selectedDay ? 'is-selected' : '' ?> relative border-l border-gray-200 <?= $dayIsToday ? 'bg-brand-50/40' : '' ?>" style="height: <?= $gridPx ?>px">
                <?php foreach ($greyBands as [$from, $to]): ?>
                <div class="absolute inset-x-0 bg-gray-100" style="top: <?= $offsetPx($from) ?>px; height: <?= max(0, $offsetPx($to) - $offsetPx($from)) ?>px"></div>
                <?php endforeach; ?>

                <?php for ($h = $w['startHour'] + 1; $h < $w['endHour']; $h++): ?>
                <div class="absolute inset-x-0 border-t border-gray-100" style="top: <?= ($h - $w['startHour']) * $HOUR_PX ?>px"></div>
                <div class="absolute inset-x-0 border-t border-dashed border-gray-100" style="top: <?= ($h - $w['startHour'] - 0.5) * $HOUR_PX ?>px"></div>
                <?php endfor; ?>

                <?php if ($dayIsToday && $nowMin >= $gridStart && $nowMin <= $gridEnd): ?>
                <div class="absolute inset-x-0 z-20 border-t-2 border-red-500 pointer-events-none" style="top: <?= $offsetPx($nowMin) ?>px">
                    <span class="absolute -left-0.5 -top-[3px] w-1.5 h-1.5 rounded-full bg-red-500"></span>
                </div>
                <?php endif; ?>

                <?php foreach ($cancelled as $apt): ?>
                <?php $t = $offsetPx($apt['_start']); $b = $offsetPx($apt['_end']); ?>
                <a href="/appointments/<?= $apt['id'] ?>" title="<?= htmlspecialchars($aptTitle($apt)) ?>"
                   class="agenda-cancelled absolute z-10 right-0.5 w-1.5 rounded-full bg-gray-300 hover:bg-gray-500"
                   style="top: <?= $t + 1 ?>px; height: <?= max(6, $b - $t - 2) ?>px"></a>
                <?php endforeach; ?>

                <?php foreach ($appts as $apt): ?>
                <?php
                $t = $offsetPx($apt['_start']);
                $b = $offsetPx($apt['_end']);
                $hPx = $b - $t;
                $width = 100 / $apt['_lanes'];
                $leftPct = $apt['_lane'] * $width;
                ?>
                <a href="/appointments/<?= $apt['id'] ?>" title="<?= htmlspecialchars($aptTitle($apt)) ?>"
                   class="absolute z-10 rounded px-1.5 overflow-hidden text-[11px] leading-[14px] hover:z-20 hover:shadow-md <?= $aptClass($apt['status']) ?> <?= $apt['has_conflict'] ? 'ring-2 ring-red-500' : '' ?>"
                   style="top: <?= $t + 1 ?>px; height: <?= max(16, $hPx - 2) ?>px; left: calc(<?= $leftPct ?>% + 2px); width: calc(<?= $width ?>% - 10px); padding-top: <?= $hPx >= 30 ? 2 : 1 ?>px">
                    <?php if ($hPx >= 30): ?>
                    <span class="block font-semibold tabular-nums truncate"><?= date('H:i', strtotime($apt['starts_at'])) ?></span>
                    <span class="block truncate"><?= htmlspecialchars($apt['client_name']) ?></span>
                    <?php else: ?>
                    <span class="block truncate"><span class="font-semibold tabular-nums"><?= date('H:i', strtotime($apt['starts_at'])) ?></span> <?= htmlspecialchars($apt['client_name']) ?></span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500">
        <span><span class="inline-block w-3 h-3 rounded-sm bg-brand-50 ring-1 ring-brand-300 align-middle"></span> Pendiente</span>
        <span><span class="inline-block w-3 h-3 rounded-sm bg-brand-500 align-middle"></span> Confirmada</span>
        <?php if ($w['isBookable']): ?>
        <span><span class="inline-block w-3 h-3 rounded-sm bg-gray-100 align-middle"></span> Sin hueco para reservar</span>
        <?php endif; ?>
        <?php if ($cancelledCount > 0): ?>
        <label class="inline-flex items-center gap-1.5 ml-auto cursor-pointer select-none">
            <input type="checkbox" id="agenda-show-cancelled" class="rounded border-gray-300 text-brand-600">
            Mostrar canceladas (<?= $cancelledCount ?>)
            <span class="inline-block w-1.5 h-3 rounded-full bg-gray-300 align-middle"></span>
        </label>
        <?php endif; ?>
    </div>
</section>

<script>
(function () {
    var toggle = document.getElementById('agenda-view-toggle');
    var buttons = toggle.querySelectorAll('[data-view]');

    function current() {
        return document.documentElement.dataset.agendaView === 'calendar' ? 'calendar' : 'list';
    }
    function paint() {
        var mode = current();
        buttons.forEach(function (btn) {
            var on = btn.dataset.view === mode;
            btn.classList.toggle('bg-brand-500', on);
            btn.classList.toggle('text-gray-900', on);
            btn.classList.toggle('text-gray-600', !on);
        });
    }
    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var mode = btn.dataset.view;
            try { localStorage.setItem('agenda_view_mode', mode); } catch (e) {}
            document.documentElement.dataset.agendaView = mode;
            paint();
        });
    });
    paint();

    // Phone: one day at a time, chosen from the swipeable strip or by swiping the grid.
    var strip = document.getElementById('agenda-strip');
    var tabs = strip.querySelectorAll('[data-day-tab]');
    var selected = <?= $selectedDay ?>;
    function selectDay(idx) {
        selected = idx;
        document.querySelectorAll('#agenda-calendar [data-day]').forEach(function (el) {
            el.classList.toggle('is-selected', +el.dataset.day === idx);
        });
        tabs.forEach(function (t) {
            var on = +t.dataset.dayTab === idx;
            t.setAttribute('aria-selected', on ? 'true' : 'false');
            t.classList.toggle('bg-primary', on);
            t.classList.toggle('border-primary', on);
            t.classList.toggle('text-white', on);
            t.classList.toggle('bg-surface', !on);
            t.classList.toggle('border-gray-200', !on);
            t.classList.toggle('text-gray-800', !on);
            if (on) { t.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' }); }
        });
    }
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () { selectDay(+tab.dataset.dayTab); });
    });
    var sel = strip.querySelector('[data-day-tab="' + selected + '"]');
    if (sel) { strip.scrollLeft = sel.offsetLeft - (strip.clientWidth - sel.offsetWidth) / 2; }

    // Deslizar el dedo sobre el calendario pasa al día anterior o siguiente. Al
    // salir de la semana se carga la vecina; más allá de esas tres semanas, nada.
    var weekDays = <?= json_encode(array_column($w['days'], 'date')) ?>;
    var shift = function (date, n) {
        var d = new Date(date + 'T12:00:00'); d.setDate(d.getDate() + n);
        return d.toISOString().slice(0, 10);
    };
    var grid = document.querySelector('#agenda-calendar .agenda-cols.isolate');
    var x0 = null, y0 = 0;
    grid.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; y0 = e.touches[0].clientY; }, { passive: true });
    grid.addEventListener('touchend', function (e) {
        if (x0 === null || window.innerWidth >= 768) { return; }
        var dx = e.changedTouches[0].clientX - x0, dy = e.changedTouches[0].clientY - y0;
        x0 = null;
        if (Math.abs(dx) < 60 || Math.abs(dx) < Math.abs(dy) * 1.5) { return; }
        var step = dx < 0 ? 1 : -1, next = selected + step;
        if (next >= 0 && next < 7) { selectDay(next); return; }
        var link = strip.querySelector('[data-strip-link="' + shift(weekDays[selected], step) + '"]');
        if (link) { location.href = link.href; }
    }, { passive: true });

    // Cancelled appointments are hidden unless asked for; the choice sticks.
    var showCancelled = document.getElementById('agenda-show-cancelled');
    if (showCancelled) {
        var apply = function (on) {
            if (on) { document.documentElement.dataset.showCancelled = '1'; }
            else { delete document.documentElement.dataset.showCancelled; }
        };
        try { showCancelled.checked = localStorage.getItem('agenda_show_cancelled') === '1'; } catch (e) {}
        apply(showCancelled.checked);
        showCancelled.addEventListener('change', function () {
            try { localStorage.setItem('agenda_show_cancelled', showCancelled.checked ? '1' : '0'); } catch (e) {}
            apply(showCancelled.checked);
        });
    }
})();
</script>

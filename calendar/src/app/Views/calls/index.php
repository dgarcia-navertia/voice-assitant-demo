<?php
$statusLabels = [
    'queued' => 'En cola', 'ringing' => 'Sonando', 'in-progress' => 'En curso',
    'completed' => 'Completada', 'failed' => 'Fallida', 'busy' => 'Ocupado',
    'no-answer' => 'Sin respuesta', 'canceled' => 'Cancelada',
];
$dirLabels = ['outbound' => 'Saliente', 'inbound' => 'Entrante', 'web' => 'Web'];
?>
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-semibold text-gray-900">Llamadas</h1>
    <a href="/dial-out" class="btn btn-accent">Nueva llamada</a>
</div>

<?php if (empty($calls)): ?>
<div class="card text-center py-16 text-gray-500" data-testid="calls-empty">
    <p class="text-sm">Todavía no hay llamadas. Lanza la primera desde Dial Out.</p>
</div>
<?php else: ?>
<div class="card overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200" data-testid="calls-table">
        <thead>
            <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wide">
                <th class="px-6 py-3">Fecha</th><th class="px-6 py-3">Tipo</th><th class="px-6 py-3">Número</th>
                <th class="px-6 py-3">Estado</th><th class="px-6 py-3">Duración</th><th class="px-6 py-3">Turnos</th><th class="px-6 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($calls as $c): ?>
            <tr class="hover:bg-brand-500/5">
                <td class="px-6 py-4 whitespace-nowrap"><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></td>
                <td class="px-6 py-4"><?= htmlspecialchars($dirLabels[$c['direction']] ?? $c['direction']) ?></td>
                <td class="px-6 py-4 tabular-nums"><?= htmlspecialchars((string) ($c['to_number'] ?? $c['from_number'] ?? '—')) ?></td>
                <td class="px-6 py-4"><span class="pill bg-gray-100 text-gray-700"><?= htmlspecialchars($statusLabels[$c['status']] ?? $c['status']) ?></span></td>
                <td class="px-6 py-4 tabular-nums"><?= $c['duration_seconds'] !== null ? (int) $c['duration_seconds'] . ' s' : '—' ?></td>
                <td class="px-6 py-4 tabular-nums"><?= (int) $c['turns'] ?></td>
                <td class="px-6 py-4 text-right"><a class="text-brand-700 hover:underline" href="/calls/<?= rawurlencode($c['call_sid']) ?>">Ver</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

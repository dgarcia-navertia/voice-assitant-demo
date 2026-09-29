<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-semibold text-gray-900">Leads</h1>
</div>
<p class="text-gray-500 -mt-3 mb-6">Contactos que el asistente ha derivado a un comercial.</p>

<?php if (empty($leads)): ?>
<div class="card text-center py-16 text-gray-500" data-testid="leads-empty">
    <p class="text-sm">Todavía no hay leads.</p>
</div>
<?php else: ?>
<div class="card overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200" data-testid="leads-table">
        <thead>
            <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wide">
                <th class="px-6 py-3">Fecha</th><th class="px-6 py-3">Nombre</th><th class="px-6 py-3">Teléfono</th>
                <th class="px-6 py-3">Tienda</th><th class="px-6 py-3">Motivo</th><th class="px-6 py-3">Transferido</th><th class="px-6 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($leads as $l): ?>
            <tr class="hover:bg-brand-500/5">
                <td class="px-6 py-4 whitespace-nowrap"><?= date('d/m/Y H:i', strtotime($l['created_at'])) ?></td>
                <td class="px-6 py-4"><?= htmlspecialchars((string) ($l['name'] ?? '—')) ?></td>
                <td class="px-6 py-4 tabular-nums"><?= htmlspecialchars($l['phone']) ?></td>
                <td class="px-6 py-4"><?= htmlspecialchars((string) ($l['store_name'] ?? '—')) ?></td>
                <td class="px-6 py-4 max-w-xs truncate" title="<?= htmlspecialchars((string) $l['reason']) ?>"><?= htmlspecialchars((string) ($l['reason'] ?? '—')) ?></td>
                <td class="px-6 py-4"><?= $l['transferred'] ? '<span class="pill bg-green-100 text-green-800">Sí</span>' : '<span class="pill bg-gray-100 text-gray-700">No</span>' ?></td>
                <td class="px-6 py-4 text-right"><?php if ($l['call_sid']): ?><a class="text-brand-700 hover:underline" href="/calls/<?= rawurlencode($l['call_sid']) ?>">Llamada</a><?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

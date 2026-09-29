<div class="flex items-center justify-between mb-6">
    <h1 class="text-xl font-semibold text-gray-900">Tiendas</h1>
    <a href="/stores/create"
       class="btn btn-primary">
        Nueva tienda
    </a>
</div>

<?php if (empty($stores)): ?>
<div class="text-center py-16 text-gray-400">
    <p class="text-sm">No hay tiendas creadas todavía.</p>
</div>
<?php else: ?>
<div class="card overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead>
            <tr class="bg-gray-50">
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Nombre</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Horario</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Comerciales</th>
                <th class="px-6 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($stores as $store): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <div class="font-medium text-gray-900"><?= htmlspecialchars($store['name']) ?></div>
                    <div class="text-xs text-gray-400 mt-0.5"><?= htmlspecialchars($store['address'] ?? '') ?></div>
                </td>
                <td class="px-6 py-4 text-sm text-gray-600">
                    <div>L-V: <?= htmlspecialchars($store['mon_to_friday'] ?? '—') ?></div>
                    <div>Sáb: <?= htmlspecialchars($store['saturday'] ?? '—') ?></div>
                </td>
                <td class="px-6 py-4 text-sm text-gray-600"><?= (int)$store['commercial_count'] ?></td>
                <td class="px-6 py-4 text-right">
                    <div class="flex justify-end gap-2">
                        <a href="/stores/<?= $store['id'] ?>/edit"
                           class="text-sm text-brand-700 hover:text-brand-900 font-medium">Editar</a>
                        <form method="POST" action="/stores/<?= $store['id'] ?>"
                              onsubmit="return confirm('¿Eliminar esta tienda?')">
                            <input type="hidden" name="_method" value="DELETE">
                            <button class="text-sm text-red-500 hover:text-red-700 font-medium">Eliminar</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

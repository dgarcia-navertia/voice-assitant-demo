<div class="flex items-center justify-between mb-6">
    <h1 class="text-xl font-semibold text-gray-900">Comerciales</h1>
    <a href="/commercials/create"
       class="btn btn-primary">
        Nuevo comercial
    </a>
</div>

<?php if (empty($commercials)): ?>
<div class="text-center py-16 text-gray-400">
    <p class="text-sm">No hay comerciales creados todavía.</p>
</div>
<?php else: ?>
<div class="card overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead>
            <tr class="bg-gray-50">
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Nombre</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Rol</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Email</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Teléfono</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Tienda</th>
                <th class="px-6 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($commercials as $commercial): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 font-medium text-gray-900"><?= htmlspecialchars($commercial['name']) ?></td>
                <td class="px-6 py-4">
                    <?php if (($commercial['role'] ?? 'commercial') === 'admin'): ?>
                    <span class="inline-block px-2 py-0.5 text-xs font-medium rounded-full bg-amber-50 text-amber-700">Admin</span>
                    <?php elseif (($commercial['role'] ?? 'commercial') === 'manager'): ?>
                    <span class="inline-block px-2 py-0.5 text-xs font-medium rounded-full bg-purple-50 text-purple-700">Manager</span>
                    <?php else: ?>
                    <span class="inline-block px-2 py-0.5 text-xs font-medium rounded-full bg-brand-50 text-brand-800">Comercial</span>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($commercial['email']) ?></td>
                <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($commercial['phone'] ?? '—') ?></td>
                <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($commercial['store_name'] ?? '—') ?></td>
                <td class="px-6 py-4 text-right">
                    <?php /* Manager/admin rows are admin-managed; managers only edit commercials. */ ?>
                    <?php if (\App\Auth::isAdmin() || ($commercial['role'] ?? 'commercial') === 'commercial'): ?>
                    <div class="flex justify-end gap-2">
                        <a href="/commercials/<?= $commercial['id'] ?>/edit"
                           class="text-sm text-brand-700 hover:text-brand-900 font-medium">Editar</a>
                        <form method="POST" action="/commercials/<?= $commercial['id'] ?>"
                              onsubmit="return confirm('¿Eliminar este comercial?')">
                            <input type="hidden" name="_method" value="DELETE">
                            <button class="text-sm text-red-500 hover:text-red-700 font-medium">Eliminar</button>
                        </form>
                    </div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-xl font-semibold text-gray-900">Usuarios</h1>
    <a href="/users/create"
       class="btn btn-primary">
        Nuevo usuario
    </a>
</div>

<?php if (empty($users)): ?>
<div class="text-center py-16 text-gray-400">
    <p class="text-sm">No hay usuarios creados todavía.</p>
</div>
<?php else: ?>
<div class="card overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead>
            <tr class="bg-gray-50">
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Nombre</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Rol</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Email</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Tienda</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Reservable</th>
                <th class="px-6 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($users as $u): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 font-medium text-gray-900"><?= htmlspecialchars($u['name']) ?></td>
                <td class="px-6 py-4">
                    <?php if ($u['role'] === 'admin'): ?>
                    <span class="inline-block px-2 py-0.5 text-xs font-medium rounded-full bg-amber-50 text-amber-700">Admin</span>
                    <?php elseif ($u['role'] === 'manager'): ?>
                    <span class="inline-block px-2 py-0.5 text-xs font-medium rounded-full bg-purple-50 text-purple-700">Manager</span>
                    <?php else: ?>
                    <span class="inline-block px-2 py-0.5 text-xs font-medium rounded-full bg-brand-50 text-brand-800">Comercial</span>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($u['email']) ?></td>
                <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($u['store_name'] ?? '—') ?></td>
                <td class="px-6 py-4">
                    <?php if ($u['bookable']): ?>
                    <a href="/commercials/<?= $u['id'] ?>/edit" class="text-xs font-medium text-brand-700 hover:underline">Sí</a>
                    <?php else: ?>
                    <span class="text-xs text-gray-400">No</span>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 text-right">
                    <a href="/users/<?= $u['id'] ?>/edit" class="text-sm font-medium text-brand-700 hover:text-brand-800">Editar</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

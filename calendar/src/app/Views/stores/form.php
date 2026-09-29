<div class="<?= $store ? 'max-w-3xl' : 'max-w-lg' ?>">
    <div class="mb-6">
        <a href="/stores" class="text-sm text-gray-500 hover:text-gray-700">← Volver a tiendas</a>
        <h1 class="text-xl font-semibold text-gray-900 mt-2">
            <?= $store ? 'Editar tienda' : 'Nueva tienda' ?>
        </h1>
    </div>

    <div class="card p-6 max-w-lg">
        <form method="POST" action="<?= $action ?>" class="space-y-4">
            <?php if ($method !== 'POST'): ?>
            <input type="hidden" name="_method" value="<?= $method ?>">
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
            <div class="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 space-y-1">
                <?php foreach ($errors as $msg): ?>
                <div><?= htmlspecialchars($msg) ?></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                <input type="text" name="name" required
                       value="<?= htmlspecialchars($store['name'] ?? $_POST['name'] ?? '') ?>"
                       class="input">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Dirección</label>
                <input type="text" name="address" required
                       value="<?= htmlspecialchars($store['address'] ?? $_POST['address'] ?? '') ?>"
                       class="input">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                    <select name="type" required
                            class="input">
                        <?php $currentType = $store['type'] ?? $_POST['type'] ?? ''; ?>
                        <?php foreach (['showroom', 'oficina', 'sede (ventas, logistica, administracion)'] as $type): ?>
                        <option value="<?= $type ?>" <?= $currentType === $type ? 'selected' : '' ?>>
                            <?= htmlspecialchars($type) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                    <input type="text" name="phone_number" required maxlength="9" pattern="[0-9]{9}"
                           value="<?= htmlspecialchars($store['phone_number'] ?? $_POST['phone_number'] ?? '') ?>"
                           class="input">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Horario lunes a viernes</label>
                <input type="text" name="mon_to_friday" placeholder="09:30-14:00 y 16:00-21:00"
                       value="<?= htmlspecialchars($store['mon_to_friday'] ?? $_POST['mon_to_friday'] ?? '') ?>"
                       class="input">
                <p class="text-xs text-gray-400 mt-1">Franjas separadas por « y ». Vacío = cerrado.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Horario sábado</label>
                <input type="text" name="saturday" placeholder="09:30-14:00"
                       value="<?= htmlspecialchars($store['saturday'] ?? $_POST['saturday'] ?? '') ?>"
                       class="input">
                <p class="text-xs text-gray-400 mt-1">Vacío = cerrado. Domingo siempre cerrado.</p>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit"
                        class="btn btn-primary">
                    <?= $store ? 'Guardar cambios' : 'Crear tienda' ?>
                </button>
                <a href="/stores" class="btn btn-secondary">
                    Cancelar
                </a>
            </div>
        </form>
    </div>

    <?php if ($store): ?>
    <div class="mt-8">
        <h2 class="text-sm font-semibold text-gray-900 mb-3">Comerciales de esta tienda</h2>
        <?php if (empty($commercials)): ?>
        <p class="text-sm text-gray-400">No hay comerciales asignados a esta tienda.</p>
        <?php else: ?>
        <div class="card overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Nombre</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Rol</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Email</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($commercials as $commercial): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3 font-medium text-gray-900"><?= htmlspecialchars($commercial['name']) ?></td>
                        <td class="px-6 py-3">
                            <?php if (($commercial['role'] ?? 'commercial') === 'admin'): ?>
                            <span class="inline-block px-2 py-0.5 text-xs font-medium rounded-full bg-amber-50 text-amber-700">Admin</span>
                            <?php elseif (($commercial['role'] ?? 'commercial') === 'manager'): ?>
                            <span class="inline-block px-2 py-0.5 text-xs font-medium rounded-full bg-purple-50 text-purple-700">Manager</span>
                            <?php else: ?>
                            <span class="inline-block px-2 py-0.5 text-xs font-medium rounded-full bg-brand-50 text-brand-800">Comercial</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-3 text-sm text-gray-600"><?= htmlspecialchars($commercial['email'] ?? '—') ?></td>
                        <td class="px-6 py-3 text-right">
                            <a href="/commercials/<?= $commercial['id'] ?>/edit"
                               class="text-sm text-brand-700 hover:text-brand-900 font-medium">Editar</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
    <h1 class="text-2xl font-semibold text-gray-900">Clientes</h1>
    <form method="GET" action="/clients" class="flex gap-2">
        <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Buscar nombre, teléfono, correo" class="input w-72" aria-label="Buscar clientes">
        <button class="btn btn-secondary" type="submit">Buscar</button>
    </form>
</div>

<div class="grid lg:grid-cols-3 gap-6 items-start">
    <div class="lg:col-span-2 card overflow-x-auto">
        <?php if (empty($clients)): ?>
        <p class="text-center py-12 text-sm text-gray-500">No hay clientes.</p>
        <?php else: ?>
        <table class="min-w-full divide-y divide-gray-200" data-testid="clients-table">
            <thead>
                <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wide">
                    <th class="px-6 py-3">Nombre</th><th class="px-6 py-3">Teléfono</th><th class="px-6 py-3">Correo</th><th class="px-6 py-3">Tipo</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($clients as $c): ?>
                <tr class="hover:bg-brand-500/5">
                    <td class="px-6 py-4 font-medium"><?= htmlspecialchars($c['client_name']) ?></td>
                    <td class="px-6 py-4 tabular-nums"><?= htmlspecialchars($c['client_phone']) ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($c['client_email'] ?: '—') ?></td>
                    <td class="px-6 py-4 capitalize"><?= htmlspecialchars($c['client_type']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <form method="POST" action="/clients" class="card p-6 space-y-4" data-testid="client-form">
        <h2 class="text-lg font-semibold">Nuevo cliente</h2>
        <?php foreach ([
            'client_name'  => ['Nombre', 'text', 'name'],
            'client_phone' => ['Teléfono (+34…)', 'tel', 'tel'],
            'client_email' => ['Correo (opcional)', 'email', 'email'],
        ] as $field => [$label, $type, $auto]): ?>
        <div>
            <label class="label" for="<?= $field ?>"><?= $label ?></label>
            <input class="input" id="<?= $field ?>" name="<?= $field ?>" type="<?= $type ?>" autocomplete="<?= $auto ?>"
                   value="<?= htmlspecialchars($old[$field] ?? '') ?>">
            <?php if (!empty($errors[$field])): ?><p class="text-sm text-red-700 mt-1"><?= htmlspecialchars($errors[$field]) ?></p><?php endif; ?>
        </div>
        <?php endforeach; ?>
        <div>
            <label class="label" for="client_type">Tipo</label>
            <select class="input" id="client_type" name="client_type">
                <option value="particular" <?= ($old['client_type'] ?? '') === 'particular' ? 'selected' : '' ?>>Particular</option>
                <option value="empresa" <?= ($old['client_type'] ?? '') === 'empresa' ? 'selected' : '' ?>>Empresa</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary w-full">Guardar cliente</button>
    </form>
</div>

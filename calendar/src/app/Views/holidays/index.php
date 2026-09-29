<?php
$errors      ??= [];
$overlapping ??= [];
$holidays    ??= [];
$stores      ??= [];
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-gray-900">Festivos</h1>
    <p class="text-sm text-gray-500 mt-1">Marca días en los que una tienda (o todas) no acepta nuevas citas.</p>
</div>

<?php if (!empty($success)): ?>
<div class="mb-6 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
    Festivo registrado correctamente.
</div>
<?php endif; ?>

<div class="w-full mb-8">
    <div class="card p-6">
        <?php if (!empty($errors)): ?>
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 space-y-1">
            <?php foreach ($errors as $msg): ?>
            <div><?= htmlspecialchars($msg) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($overlapping)): ?>
        <div class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-sm text-yellow-800 space-y-2">
            <div class="font-medium">Este festivo coincide con citas confirmadas:</div>
            <ul class="list-disc list-inside space-y-0.5">
                <?php foreach ($overlapping as $apt): ?>
                <li>
                    <a href="/appointments/<?= $apt['id'] ?>" class="underline hover:text-yellow-900">
                        <?= htmlspecialchars($apt['store_name']) ?> —
                        <?= date('d/m/Y H:i', strtotime($apt['starts_at'])) ?> (<?= $apt['duration_minutes'] ?> min)
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <div>No se cancelan automáticamente — pulsa «Registrar de todos modos» para continuar.</div>
        </div>
        <?php endif; ?>

        <form method="POST" action="/holidays" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tienda</label>
                    <select name="store_id"
                            class="input">
                        <option value="" <?= ($_POST['store_id'] ?? '') === '' ? 'selected' : '' ?>>Todas las tiendas</option>
                        <?php foreach ($stores as $store): ?>
                        <option value="<?= $store['id'] ?>"
                            <?= ($_POST['store_id'] ?? '') == $store['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($store['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Desde el día</label>
                    <input type="date" name="date_from" id="date_from" required
                           onchange="syncEndDateMin(this.value)"
                           value="<?= htmlspecialchars($_POST['date_from'] ?? date('Y-m-d')) ?>"
                           class="input">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Hasta el día <span class="text-gray-400 font-normal">— opcional</span>
                    </label>
                    <input type="date" name="date_to" id="date_to"
                           value="<?= htmlspecialchars($_POST['date_to'] ?? '') ?>"
                           class="input">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Motivo <span class="text-gray-400 font-normal">— opcional</span>
                    </label>
                    <input type="text" name="reason" maxlength="255"
                           value="<?= htmlspecialchars($_POST['reason'] ?? '') ?>"
                           placeholder="Navidad, cierre por reforma…"
                           class="input">
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <?php if (!empty($overlapping)): ?>
                <input type="hidden" name="force" value="1">
                <button type="submit"
                        class="btn bg-amber-600 hover:bg-amber-700 text-white">
                    Registrar de todos modos
                </button>
                <?php else: ?>
                <button type="submit"
                        class="btn btn-primary">
                    Registrar festivo
                </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card overflow-hidden w-full">
    <div class="px-6 py-4 border-b border-gray-100">
        <h2 class="text-sm font-semibold text-gray-900">Próximos festivos</h2>
    </div>
    <?php if (empty($holidays)): ?>
    <p class="px-6 py-4 text-sm text-gray-400">No hay festivos registrados.</p>
    <?php else: ?>
    <ul class="divide-y divide-gray-100">
        <?php foreach ($holidays as $h): ?>
        <li class="px-6 py-3 flex items-center justify-between">
            <div>
                <div class="text-sm text-gray-900"><?= date('d/m/Y', strtotime($h['date'])) ?></div>
                <div class="text-xs text-gray-500">
                    <?= htmlspecialchars($h['store_name'] ?? 'Todas las tiendas') ?>
                    <?php if (!empty($h['reason'])): ?>
                    — <?= htmlspecialchars($h['reason']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <form method="POST" action="/holidays/<?= $h['id'] ?>" onsubmit="return confirm('¿Eliminar este festivo?')">
                <input type="hidden" name="_method" value="DELETE">
                <button type="submit" class="text-sm text-red-500 hover:text-red-700 font-medium">Eliminar</button>
            </form>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>

<script>
function syncEndDateMin(startDate) {
    const end = document.getElementById('date_to');
    end.min = startDate;
    if (end.value && end.value < startDate) end.value = startDate;
}
syncEndDateMin(document.getElementById('date_from').value);
</script>

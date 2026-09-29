<?php
$errors    ??= [];
$overrides ??= [];
?>

<div class="mb-6">
    <a href="/commercials/<?= $commercial['id'] ?>/edit" class="text-sm text-gray-500 hover:text-gray-700">← Volver a <?= htmlspecialchars($commercial['name']) ?></a>
    <h1 class="text-xl font-semibold text-gray-900 mt-2">Excepciones de horario</h1>
    <p class="text-sm text-gray-500 mt-1">Excepciones puntuales al horario habitual de <?= htmlspecialchars($commercial['name']) ?> para un día concreto.</p>
</div>

<?php if (!empty($success)): ?>
<div class="mb-6 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
    Excepción guardada correctamente.
</div>
<?php endif; ?>

<div class="max-w-lg mb-8">
    <div class="card p-6">
        <?php if (!empty($errors)): ?>
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 space-y-1">
            <?php foreach ($errors as $msg): ?>
            <div><?= htmlspecialchars($msg) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="/commercials/<?= $commercial['id'] ?>/overrides" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fecha</label>
                <input type="date" name="date" required
                       value="<?= htmlspecialchars($_POST['date'] ?? '') ?>"
                       class="input">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">¿Trabaja ese día?</label>
                <?php $works = $_POST['works'] ?? '1'; ?>
                <select name="works" id="works" onchange="onWorksChange()"
                        class="input">
                    <option value="1" <?= $works === '1' ? 'selected' : '' ?>>Trabaja</option>
                    <option value="0" <?= $works === '0' ? 'selected' : '' ?>>Libra</option>
                </select>
            </div>

            <div id="schedule_wrapper" style="<?= $works === '0' ? 'display:none' : '' ?>">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Horario <span class="text-gray-400 font-normal">— opcional, hereda el de la plantilla si se deja vacío</span>
                </label>
                <input type="text" name="schedule"
                       value="<?= htmlspecialchars($_POST['schedule'] ?? '') ?>"
                       placeholder="09:30-14:00 y 16:00-21:00"
                       class="input">
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit"
                        class="btn btn-primary">
                    Guardar excepción
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card overflow-hidden max-w-2xl">
    <div class="px-6 py-4 border-b border-gray-100">
        <h2 class="text-sm font-semibold text-gray-900">Próximas excepciones</h2>
    </div>
    <?php if (empty($overrides)): ?>
    <p class="px-6 py-4 text-sm text-gray-400">No hay excepciones registradas.</p>
    <?php else: ?>
    <ul class="divide-y divide-gray-100">
        <?php foreach ($overrides as $o): ?>
        <li class="px-6 py-3 flex items-center justify-between">
            <div>
                <div class="text-sm text-gray-900"><?= date('d/m/Y', strtotime($o['date'])) ?></div>
                <div class="text-xs text-gray-500">
                    <?php if (!$o['works']): ?>
                    Libra
                    <?php else: ?>
                    Trabaja<?= $o['schedule'] ? ' — ' . htmlspecialchars($o['schedule']) : ' (horario de la plantilla)' ?>
                    <?php endif; ?>
                </div>
            </div>
            <form method="POST" action="/commercials/<?= $commercial['id'] ?>/overrides/<?= $o['id'] ?>" onsubmit="return confirm('¿Eliminar esta excepción?')">
                <input type="hidden" name="_method" value="DELETE">
                <button type="submit" class="text-sm text-red-500 hover:text-red-700 font-medium">Eliminar</button>
            </form>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>

<script>
function onWorksChange() {
    const works = document.getElementById('works').value;
    document.getElementById('schedule_wrapper').style.display = works === '0' ? 'none' : '';
}
</script>

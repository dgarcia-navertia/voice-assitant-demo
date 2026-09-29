<?php
use App\Auth;

$commercials ??= [];
$errors      ??= [];
$overlapping ??= [];
?>

<div class="max-w-lg">
    <div class="mb-6">
        <a href="/appointments" class="text-sm text-gray-500 hover:text-gray-700">← Volver a citas</a>
        <h1 class="text-xl font-semibold text-gray-900 mt-2">Bloquear horario</h1>
        <p class="text-sm text-gray-500 mt-0.5">El tramo bloqueado dejará de ofrecerse como disponible.</p>
    </div>

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
            <div class="font-medium">Este bloqueo se solapa con citas confirmadas:</div>
            <ul class="list-disc list-inside space-y-0.5">
                <?php foreach ($overlapping as $apt): ?>
                <li>
                    <a href="/appointments/<?= $apt['id'] ?>" class="underline hover:text-yellow-900">
                        <?= date('d/m/Y H:i', strtotime($apt['starts_at'])) ?> (<?= $apt['duration_minutes'] ?> min)
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <div>Las citas no se modifican: tendrás que reubicarlas o cancelarlas manualmente. Pulsa «Bloquear de todos modos» para continuar.</div>
        </div>
        <?php endif; ?>

        <form method="POST" action="/blocking-events" class="space-y-4">
            <?php if (!Auth::isCommercial()): ?>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Comercial</label>
                <select name="commercial_id" required
                        class="input">
                    <option value="">Seleccionar comercial</option>
                    <?php foreach ($commercials as $commercial): ?>
                    <option value="<?= $commercial['id'] ?>"
                        <?= ($_POST['commercial_id'] ?? '') == $commercial['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($commercial['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Desde el día</label>
                    <input type="date" name="start_date" id="start_date" required
                           onchange="syncEndDateMin(this.value)"
                           value="<?= htmlspecialchars($_POST['start_date'] ?? date('Y-m-d')) ?>"
                           class="input">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Hasta el día <span class="text-gray-400 font-normal">— opcional</span>
                    </label>
                    <input type="date" name="end_date" id="end_date"
                           value="<?= htmlspecialchars($_POST['end_date'] ?? '') ?>"
                           class="input">
                </div>
            </div>

            <div>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="all_day" value="1" id="all_day"
                           onchange="toggleAllDay(this.checked)"
                           <?= ($_POST['all_day'] ?? '') === '1' ? 'checked' : '' ?>
                           class="rounded border-gray-300 text-brand-700 focus:ring-brand-500">
                    Todo el día
                </label>
            </div>

            <div class="grid grid-cols-2 gap-3" id="time_fields">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Desde las</label>
                    <input type="time" name="start_time" id="start_time"
                           value="<?= htmlspecialchars($_POST['start_time'] ?? '') ?>"
                           class="input">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Hasta las</label>
                    <input type="time" name="end_time" id="end_time"
                           value="<?= htmlspecialchars($_POST['end_time'] ?? '') ?>"
                           class="input">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Motivo <span class="text-gray-400 font-normal">— opcional</span>
                </label>
                <input type="text" name="reason" maxlength="255"
                       value="<?= htmlspecialchars($_POST['reason'] ?? '') ?>"
                       placeholder="Reunión, gestión personal…"
                       class="input">
            </div>

            <div class="flex gap-3 pt-2">
                <?php if (!empty($overlapping)): ?>
                <button type="submit" name="force" value="1"
                        class="btn bg-amber-600 hover:bg-amber-700 text-white">
                    Bloquear de todos modos
                </button>
                <?php else: ?>
                <button type="submit"
                        class="btn bg-primary hover:bg-primary-hover text-white">
                    Bloquear horario
                </button>
                <?php endif; ?>
                <a href="/appointments" class="btn btn-secondary">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<script>
function toggleAllDay(checked) {
    document.getElementById('start_time').disabled = checked;
    document.getElementById('end_time').disabled = checked;
    document.getElementById('time_fields').style.opacity = checked ? '0.4' : '1';
}
toggleAllDay(document.getElementById('all_day').checked);

function syncEndDateMin(startDate) {
    const end = document.getElementById('end_date');
    end.min = startDate;
    if (end.value && end.value < startDate) end.value = startDate;
}
syncEndDateMin(document.getElementById('start_date').value);
</script>

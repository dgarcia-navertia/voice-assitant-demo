<div class="max-w-lg">
    <div class="mb-6">
        <a href="/commercials" class="text-sm text-gray-500 hover:text-gray-700">← Volver a comerciales</a>
        <h1 class="text-xl font-semibold text-gray-900 mt-2">
            <?= $commercial ? 'Editar comercial' : 'Nuevo comercial' ?>
        </h1>
    </div>

    <div class="card p-6">
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
                       value="<?= htmlspecialchars($commercial['name'] ?? $_POST['name'] ?? '') ?>"
                       class="input">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" required
                       value="<?= htmlspecialchars($commercial['email'] ?? $_POST['email'] ?? '') ?>"
                       class="input">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                <input type="tel" name="phone"
                       value="<?= htmlspecialchars($commercial['phone'] ?? $_POST['phone'] ?? '') ?>"
                       placeholder="Ej: 600123456"
                       class="input">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Contraseña <?= $commercial ? '(dejar en blanco para no cambiar)' : '' ?>
                </label>
                <input type="password" name="password" <?= $commercial ? '' : 'required' ?>
                       class="input">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tienda</label>
                <select name="store_id" id="store_id" required
                        class="input">
                    <option value="">Seleccionar tienda</option>
                    <?php foreach ($stores as $store): ?>
                    <option value="<?= $store['id'] ?>"
                        <?= ($commercial['store_id'] ?? $_POST['store_id'] ?? '') == $store['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($store['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php
            $hasSubmittedForm = !empty($_POST);
            $activeChecked = $hasSubmittedForm
                ? isset($_POST['active'])
                : (bool) ($commercial['active'] ?? true);
            $transferChecked = $hasSubmittedForm
                ? isset($_POST['voice_transfer_enabled'])
                : (bool) ($commercial['voice_transfer_enabled'] ?? false);
            ?>
            <div class="space-y-2">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="active" value="1" <?= $activeChecked ? 'checked' : '' ?>>
                    Comercial activo
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="voice_transfer_enabled" value="1" <?= $transferChecked ? 'checked' : '' ?>>
                    Permitir transferencias de voz
                </label>
                <p class="text-xs text-gray-400">El horario indica elegibilidad, no garantiza que la persona responda.</p>
            </div>
            <?php if (\App\Auth::isAdmin()): ?>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Rol</label>
                <?php $currentRole = $commercial['role'] ?? $_POST['role'] ?? 'commercial'; ?>
                <select name="role"
                        class="input">
                    <option value="commercial" <?= $currentRole === 'commercial' ? 'selected' : '' ?>>Comercial</option>
                    <option value="manager" <?= $currentRole === 'manager' ? 'selected' : '' ?>>Manager</option>
                    <option value="admin" <?= $currentRole === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
                <p class="text-xs text-gray-400 mt-1">
                    Cualquier rol puede atender citas. Los managers además gestionan su tienda y los admins, toda la aplicación.
                    Al asignar citas se prioriza: comerciales primero, luego managers y por último admins.
                </p>
            </div>
            <?php endif; ?>

            <?php
            $services ??= [];
            // Al re-renderizar tras un error de validación manda lo enviado, no lo
            // guardado: si no, desmarcar todo y fallar la validación resucitaría
            // las especialidades viejas en pantalla.
            $selectedSpecialties = $hasSubmittedForm
                ? array_map('intval', (array) ($_POST['specialties'] ?? []))
                : array_map('intval', json_decode((string) ($commercial['specialties'] ?? ''), true) ?? []);
            ?>
            <?php if ($services): ?>
            <div class="pt-2 border-t border-gray-100">
                <label class="block text-sm font-medium text-gray-700 mb-1">Especialidades</label>
                <p class="text-xs text-gray-400 mb-3">
                    Servicios que atiende esta persona. Al buscar hueco para un servicio concreto solo
                    se ofrecen los comerciales que lo tengan marcado. Sin ninguna marcada solo recibe
                    citas genéricas, sin servicio asignado.
                </p>
                <div class="grid grid-cols-2 gap-2">
                    <?php foreach ($services as $service): ?>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="specialties[]" value="<?= (int) $service['id'] ?>"
                               <?= in_array((int) $service['id'], $selectedSpecialties, true) ? 'checked' : '' ?>>
                        <?= htmlspecialchars($service['name']) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php
            $weeks ??= [];
            $weekdays = [
                'monday'    => 'Lunes',
                'tuesday'   => 'Martes',
                'wednesday' => 'Miércoles',
                'thursday'  => 'Jueves',
                'friday'    => 'Viernes',
                'saturday'  => 'Sábado',
                'sunday'    => 'Domingo',
            ];
            $maxWeeks       = 6;
            $rotationLength = (int) ($_POST['rotation_length'] ?? $commercial['rotation_length'] ?? 1);
            $rotationAnchor = $_POST['rotation_anchor'] ?? $commercial['rotation_anchor'] ?? '';
            ?>
            <div class="pt-2 border-t border-gray-100">
                <label class="block text-sm font-medium text-gray-700 mb-1">Rotación de horario</label>
                <p class="text-xs text-gray-400 mb-3">
                    Número de semanas que dura el ciclo antes de repetirse. 1 = horario semanal fijo (sin rotación).
                </p>
                <div class="flex items-end gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Semanas de rotación</label>
                        <select name="rotation_length" id="rotation_length" onchange="onRotationLengthChange()"
                                class="input w-auto">
                            <?php for ($n = 1; $n <= $maxWeeks; $n++): ?>
                            <option value="<?= $n ?>" <?= $rotationLength === $n ? 'selected' : '' ?>><?= $n ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div id="rotation_anchor_wrapper" style="<?= $rotationLength > 1 ? '' : 'display:none' ?>">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Semana de referencia (ancla)</label>
                        <input type="date" name="rotation_anchor" id="rotation_anchor"
                               value="<?= htmlspecialchars($rotationAnchor ?? '') ?>"
                               class="input w-auto">
                        <p class="text-xs text-gray-400 mt-1">Se normaliza automáticamente al lunes de esa semana.</p>
                    </div>
                </div>

                <p class="text-xs text-gray-400 mb-3">
                    Horario de cada día. Formato: <code>09:30-14:00 y 16:00-21:00</code>. Dejar vacío = no trabaja ese día.
                </p>

                <div id="store-schedule-ref"
                     class="mb-4 text-xs bg-brand-50 border border-brand-200 rounded-lg px-3 py-2 text-brand-900"></div>

                <div id="week_blocks">
                    <?php for ($w = 0; $w < $maxWeeks; $w++): ?>
                    <div class="week-block mb-4 p-3 border border-gray-100 rounded-lg" data-week-index="<?= $w ?>"
                         style="<?= $w < $rotationLength ? '' : 'display:none' ?>">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-600">Semana <?= $w + 1 ?></span>
                            <?php if ($w >= 1): ?>
                            <button type="button" onclick="copyPreviousWeek(<?= $w ?>)"
                                    class="text-xs text-brand-700 hover:text-brand-900 font-medium">
                                Copiar semana anterior
                            </button>
                            <?php endif; ?>
                        </div>
                        <div class="space-y-2">
                            <?php foreach ($weekdays as $key => $label):
                                $value = $_POST['weeks'][$w][$key] ?? ($weeks[$w][$key] ?? '');
                            ?>
                            <div class="flex items-center gap-3">
                                <span class="w-24 text-sm text-gray-600"><?= $label ?></span>
                                <input type="text" name="weeks[<?= $w ?>][<?= $key ?>]"
                                       value="<?= htmlspecialchars($value ?? '') ?>"
                                       placeholder="09:30-14:00 y 16:00-21:00"
                                       class="input flex-1 w-auto">
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit"
                        class="btn btn-primary">
                    <?= $commercial ? 'Guardar cambios' : 'Crear comercial' ?>
                </button>
                <a href="/commercials" class="btn btn-secondary">
                    Cancelar
                </a>
            </div>
        </form>
    </div>

    <?php if ($commercial): ?>
    <div class="mt-4">
        <a href="/commercials/<?= $commercial['id'] ?>/overrides" class="text-sm text-brand-700 hover:text-brand-900 font-medium">
            Gestionar excepciones de horario →
        </a>
    </div>
    <?php endif; ?>
</div>

<script>
const SCHEDULE_WEEKDAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
const initialRotationLength = <?= $rotationLength ?>;

const STORE_SCHEDULES = <?= json_encode($storeSchedules ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

function renderStoreScheduleRef() {
    const box = document.getElementById('store-schedule-ref');
    if (!box) return;
    const value = document.getElementById('store_id').value;
    if (!value) {
        box.textContent = 'Elige una tienda para ver su horario como referencia.';
        return;
    }
    const sch = STORE_SCHEDULES[value];
    if (!sch || (!sch.mon_to_friday && !sch.saturday)) {
        box.textContent = 'Esta tienda no tiene horario configurado. El horario efectivo del comercial es el que se guarda abajo.';
        return;
    }
    box.innerHTML =
        '<span class="font-semibold">Horario de la tienda (referencia):</span> ' +
        'L–V ' + (sch.mon_to_friday || '—') +
        ' · Sáb ' + (sch.saturday || '—') +
        ' · Dom cerrado';
}

document.getElementById('store_id').addEventListener('change', renderStoreScheduleRef);
renderStoreScheduleRef();

function onRotationLengthChange() {
    const length = parseInt(document.getElementById('rotation_length').value, 10);
    document.getElementById('rotation_anchor_wrapper').style.display = length > 1 ? '' : 'none';

    document.querySelectorAll('.week-block').forEach(function (block) {
        const idx = parseInt(block.dataset.weekIndex, 10);
        if (idx < length) {
            block.style.display = '';
            if (idx > 0 && isWeekEmpty(idx)) {
                copyPreviousWeek(idx);
            }
        } else {
            block.style.display = 'none';
        }
    });
}

function isWeekEmpty(weekIndex) {
    return SCHEDULE_WEEKDAYS.every(function (day) {
        const input = document.querySelector('[name="weeks[' + weekIndex + '][' + day + ']"]');
        return !input || input.value.trim() === '';
    });
}

function copyPreviousWeek(weekIndex) {
    SCHEDULE_WEEKDAYS.forEach(function (day) {
        const source = document.querySelector('[name="weeks[' + (weekIndex - 1) + '][' + day + ']"]');
        const target = document.querySelector('[name="weeks[' + weekIndex + '][' + day + ']"]');
        if (source && target) target.value = source.value;
    });
}

document.querySelector('form').addEventListener('submit', function (e) {
    const length = parseInt(document.getElementById('rotation_length').value, 10);
    if (length < initialRotationLength) {
        const confirmed = confirm(
            'Vas a reducir el número de semanas de rotación de ' + initialRotationLength + ' a ' + length +
            '. Se perderá el horario de las semanas que queden fuera del nuevo rango. ¿Continuar?'
        );
        if (!confirmed) {
            e.preventDefault();
        }
    }
});
</script>

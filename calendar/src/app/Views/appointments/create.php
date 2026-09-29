<?php
// $storeCommercials is injected by AppointmentController
$storeCommercials ??= [];
?>

<div class="max-w-lg">
    <div class="mb-6">
        <a href="/appointments" class="text-sm text-gray-500 hover:text-gray-700">← Volver a citas</a>
        <h1 class="text-xl font-semibold text-gray-900 mt-2">Nueva cita</h1>
    </div>

    <div class="card p-6">
        <?php if (!empty($errors)): ?>
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 space-y-1">
            <?php foreach ($errors as $msg): ?>
            <div><?= htmlspecialchars($msg) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="/appointments" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tienda</label>
                <select name="store_id" id="store_id" required onchange="filterCommercials(this.value)"
                        class="input">
                    <option value="">Seleccionar tienda</option>
                    <?php foreach ($stores as $store): ?>
                    <option value="<?= $store['id'] ?>"
                        <?= ($_POST['store_id'] ?? '') == $store['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($store['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Comercial</label>
                <select name="commercial_id" id="commercial_id" required onchange="loadSlots()"
                        class="input">
                    <option value="">Seleccionar comercial</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Servicio</label>
                <select name="service_id" id="service_id" onchange="onServiceChange()"
                        class="input">
                    <option value="">Sin servicio concreto</option>
                    <?php foreach ($services as $service): ?>
                    <option value="<?= (int) $service['id'] ?>"
                            data-duration="<?= (int) $service['appointment_time'] ?>"
                        <?= ($_POST['service_id'] ?? '') == $service['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($service['name']) ?> (<?= (int) $service['appointment_time'] ?> min)
                    </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-400 mt-1">
                    Al elegirlo se aplica su duración y se recalculan las horas libres. Queda registrado
                    en la cita para que el comercial sepa de qué va la visita.
                </p>
            </div>

            <div class="grid sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha</label>
                    <input type="date" name="date" id="date" required min="<?= date('Y-m-d') ?>"
                           value="<?= htmlspecialchars($_POST['date'] ?? date('Y-m-d')) ?>"
                           onchange="loadSlots()"
                           class="input">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Hora</label>
                    <select name="time" id="time" required
                            class="input">
                        <option value="">Selecciona tienda y comercial</option>
                    </select>
                    <p id="slots_hint" class="mt-1 text-xs text-gray-500"></p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Cliente</label>
                <select name="client_id" required
                        class="input">
                    <option value="">Seleccionar cliente</option>
                    <?php foreach ($clients as $client): ?>
                    <option value="<?= $client['id'] ?>"
                        <?= ($_POST['client_id'] ?? '') == $client['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($client['client_name']) ?> (<?= htmlspecialchars($client['client_phone']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Duración (min)
                    <span class="text-gray-400 font-normal">— la del servicio: <span id="service_duration_hint"><?= (int) $defaultDuration ?></span> min. Puedes ajustarla.</span>
                </label>
                <input type="number" name="duration_minutes" id="duration_minutes" min="15" step="15" max="480"
                       value="<?= htmlspecialchars($_POST['duration_minutes'] ?? $defaultDuration) ?>"
                       onchange="loadSlots()"
                       class="input">
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit"
                        class="btn btn-primary">
                    Crear cita
                </button>
                <a href="/appointments" class="btn btn-secondary">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<script>
const storeCommercials = <?= json_encode($storeCommercials) ?>;
const preselectedStore = <?= json_encode($_POST['store_id'] ?? '') ?>;
const preselectedCommercial = <?= json_encode($_POST['commercial_id'] ?? '') ?>;
const preselectedTime = <?= json_encode($_POST['time'] ?? '') ?>;

function filterCommercials(storeId) {
    const select = document.getElementById('commercial_id');
    select.innerHTML = '<option value="">Seleccionar comercial</option>';
    const commercials = storeCommercials[storeId] || [];
    commercials.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = c.name;
        if (c.id == preselectedCommercial) opt.selected = true;
        select.appendChild(opt);
    });
    loadSlots();
}

// Elegir servicio aplica SU duración y recalcula la rejilla: una cita de
// "Vivienda completa" ocupa 90 minutos, no los 30 del servicio genérico, y los
// huecos que caben son distintos. Solo se dispara en el evento change, nunca al
// cargar: en el re-render tras un error de validación manda la duración que el
// usuario había enviado, incluso si la había ajustado a mano.
const DEFAULT_SERVICE_DURATION = <?= (int) $genericDuration ?>;

function onServiceChange() {
    const select = document.getElementById('service_id');
    const option = select.options[select.selectedIndex];
    const duration = option && option.dataset.duration
        ? parseInt(option.dataset.duration, 10)
        : DEFAULT_SERVICE_DURATION;

    document.getElementById('duration_minutes').value = duration;
    document.getElementById('service_duration_hint').textContent = duration;
    loadSlots();
}

// El desplegable de horas solo ofrece los huecos que el servidor considera
// libres, así que no se puede teclear una hora fuera de la rejilla ni pisar
// otra cita. Cada cambio de tienda/comercial/fecha/duración lo recalcula.
let slotsRequest = 0;

async function loadSlots() {
    const storeId = document.getElementById('store_id').value;
    const commercialId = document.getElementById('commercial_id').value;
    const date = document.getElementById('date').value;
    const duration = document.getElementById('duration_minutes').value;
    const select = document.getElementById('time');
    const hint = document.getElementById('slots_hint');

    if (!storeId || !commercialId || !date) {
        select.innerHTML = '<option value="">Selecciona tienda y comercial</option>';
        hint.textContent = '';
        return;
    }

    const selected = select.value || preselectedTime;
    const ticket = ++slotsRequest;
    select.innerHTML = '<option value="">Cargando…</option>';
    hint.textContent = '';

    const params = new URLSearchParams({ store_id: storeId, commercial_id: commercialId, date: date });
    if (duration) params.set('duration_minutes', duration);

    let slots = null;
    try {
        const response = await fetch('/appointments/slots?' + params.toString(), {
            headers: { 'Accept': 'application/json' },
        });
        if (response.ok) {
            slots = (await response.json()).slots || [];
        }
    } catch (error) {
        slots = null;
    }

    // Una respuesta obsoleta no debe pisar la de una selección más reciente.
    if (ticket !== slotsRequest) return;

    if (slots === null) {
        select.innerHTML = '<option value="">Error al cargar horas</option>';
        hint.textContent = 'No se pudo consultar la disponibilidad. Vuelve a intentarlo.';
        return;
    }

    if (slots.length === 0) {
        select.innerHTML = '<option value="">Sin horas disponibles</option>';
        hint.textContent = 'Ese día no queda ningún hueco para este comercial.';
        return;
    }

    select.innerHTML = '<option value="">Seleccionar hora</option>';
    slots.forEach(time => {
        const opt = document.createElement('option');
        opt.value = time;
        opt.textContent = time;
        if (time === selected) opt.selected = true;
        select.appendChild(opt);
    });
    hint.textContent = slots.length + (slots.length === 1 ? ' hueco libre' : ' huecos libres');
}

if (preselectedStore) {
    document.getElementById('store_id').value = preselectedStore;
    filterCommercials(preselectedStore);
}
</script>

<?php
use App\Auth;

$statusColor = match ($appointment['status']) {
    'confirmed' => 'bg-green-100 text-green-800',
    'cancelled' => 'bg-gray-100 text-gray-600',
    default     => 'bg-yellow-100 text-yellow-800',
};
$statusLabel = match ($appointment['status']) {
    'confirmed' => 'Confirmada',
    'cancelled' => 'Cancelada',
    default     => 'Pendiente',
};
$mine       = Auth::isAdmin() || Auth::isManager() || (int) $appointment['commercial_id'] === Auth::id();
$canConfirm = $appointment['status'] === 'pending' && $mine;
$canCancel  = $appointment['status'] !== 'cancelled' && $mine;
$label = fn(string $text): string => '<div class="text-xs text-gray-500 uppercase tracking-wide mb-1">' . $text . '</div>';
?>

<div class="max-w-2xl">
    <div class="mb-6">
        <a href="/appointments" class="text-sm text-gray-500 hover:text-gray-700">← Volver a citas</a>
        <div class="flex items-center gap-3 mt-2">
            <h1 class="text-xl font-semibold text-gray-900">Cita #<?= (int) $appointment['id'] ?></h1>
            <span class="<?= $statusColor ?> text-sm px-2.5 py-0.5 rounded-full font-medium"><?= $statusLabel ?></span>
            <?php if ($appointment['has_conflict']): ?>
            <span class="bg-red-100 text-red-700 text-sm px-2.5 py-0.5 rounded-full font-medium">Conflicto</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($appointment['has_conflict']): ?>
    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-2xl text-sm text-red-700">
        Esta cita está en conflicto con otra cita confirmada en el mismo horario. Es necesario mover una de las citas.
    </div>
    <?php endif; ?>

    <?php if (!empty($cancelError)): ?>
    <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-2xl text-sm text-red-700" role="alert">
        <?= htmlspecialchars($cancelError) ?>
    </div>
    <?php endif; ?>

    <div class="card p-6 space-y-4">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <?= $label('Cliente') ?>
                <div class="font-medium text-gray-900"><?= htmlspecialchars($appointment['client_name']) ?></div>
                <div class="text-sm text-gray-500"><?= htmlspecialchars($appointment['client_phone']) ?></div>
            </div>
            <div>
                <?= $label('Fecha y hora') ?>
                <div class="font-medium text-gray-900"><?= date('d/m/Y', strtotime($appointment['starts_at'])) ?></div>
                <div class="text-sm text-gray-500">
                    <?= date('H:i', strtotime($appointment['starts_at'])) ?>
                    — <?= date('H:i', strtotime($appointment['starts_at']) + (int) $appointment['duration_minutes'] * 60) ?>
                    (<?= (int) $appointment['duration_minutes'] ?> min)
                </div>
            </div>
            <div>
                <?= $label('Tienda') ?>
                <div class="font-medium text-gray-900"><?= htmlspecialchars($appointment['store_name']) ?></div>
                <div class="text-sm text-gray-500"><?= htmlspecialchars($appointment['store_address']) ?></div>
            </div>
            <div>
                <?= $label('Comercial') ?>
                <div class="font-medium text-gray-900"><?= htmlspecialchars($appointment['commercial_name']) ?></div>
            </div>
            <div>
                <?= $label('Servicio') ?>
                <div class="font-medium text-gray-900">
                    <?= $appointment['service_name'] === null ? 'Cita genérica' : htmlspecialchars($appointment['service_name']) ?>
                </div>
            </div>
            <?php if ($appointment['status'] === 'cancelled' && $appointment['cancellation_reason']): ?>
            <div class="col-span-2">
                <?= $label('Motivo de cancelación') ?>
                <div class="font-medium text-gray-900"><?= htmlspecialchars((string) $appointment['cancellation_reason']) ?></div>
            </div>
            <?php endif; ?>
        </div>

        <div class="text-xs text-gray-500 pt-3 border-t border-gray-200">
            Creada<?= $appointment['created_by_name'] !== null ? ' por ' . htmlspecialchars($appointment['created_by_name']) : ' por el asistente de voz' ?>
            el <?= date('d/m/Y H:i', strtotime($appointment['created_at'])) ?>
        </div>
    </div>

    <?php if ($canConfirm || $canCancel): ?>
    <div class="mt-5 flex items-start gap-4 flex-wrap">
        <?php if ($canConfirm): ?>
        <form method="POST" action="/appointments/<?= (int) $appointment['id'] ?>/confirm">
            <button type="submit" class="btn btn-primary">Confirmar cita</button>
        </form>
        <?php endif; ?>

        <?php if ($canCancel): ?>
        <form method="POST" action="/appointments/<?= (int) $appointment['id'] ?>/cancel" class="flex flex-wrap items-center gap-3">
            <input type="text" name="reason" required placeholder="Motivo de la cancelación" class="input w-72" aria-label="Motivo de la cancelación">
            <button type="submit" class="btn btn-secondary text-red-700">Cancelar cita</button>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

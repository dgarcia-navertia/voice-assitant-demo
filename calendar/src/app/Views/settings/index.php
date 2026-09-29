<?php
use App\Icon;
$who = $row && $row['updated_at']
    ? date('d/m/Y H:i', strtotime($row['updated_at'])) . ($row['updated_by_name'] ? ' por ' . $row['updated_by_name'] : '')
    : null;
?>
<div class="max-w-3xl mx-auto"
     x-data="phonePicker()"
     x-init="initPicker('<?= htmlspecialchars($attempted ?? $number, ENT_QUOTES) ?>')">

    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">Traspaso a comercial</h1>
        <p class="text-gray-500 mt-1">Número al que el asistente transfiere la llamada cuando alguien pide hablar con una persona.</p>
    </div>

    <?php if (!empty($saved)): ?>
    <div class="mb-5 p-3 bg-green-50 border border-green-200 rounded-2xl text-green-800" role="status" data-testid="settings-saved">
        Número de traspaso guardado. El asistente lo usará desde la próxima transferencia.
    </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
    <div class="mb-5 p-3 bg-red-50 border border-red-200 rounded-2xl text-red-800" role="alert" data-testid="settings-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/settings/handoff" class="card relative z-20 p-6 sm:p-8" data-testid="handoff-form" novalidate
          @submit="if (!valid) { $event.preventDefault(); }">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="handoff_phone_number" :value="e164" data-testid="handoff-e164">
        <label for="handoff-phone" class="label">Teléfono de traspaso</label>

        <div class="flex flex-col sm:flex-row gap-3 sm:items-stretch">
            <?php $phoneInputId = 'handoff-phone'; $phoneTestId = 'handoff-phone'; require __DIR__ . '/../partials/phone-pill.php'; ?>
            <button type="submit" data-testid="handoff-save" :disabled="!valid"
                    class="btn btn-primary min-h-[3.25rem] px-8 text-base disabled:opacity-50 disabled:cursor-not-allowed">
                Guardar
            </button>
        </div>

        <p class="mt-3 text-sm" :class="phone && !valid ? 'text-red-600' : 'text-gray-500'" data-testid="handoff-hint">
            <template x-if="phone && !valid"><span>Número no válido (E.164): <span class="tabular-nums" x-text="e164"></span></span></template>
            <template x-if="!phone || valid"><span>Se transferirá a <strong class="tabular-nums text-gray-900" x-text="e164"></strong></span></template>
        </p>

        <dl class="mt-6 pt-4 border-t border-gray-200/70 text-sm text-gray-500 space-y-1">
            <div>Valor actual: <span class="tabular-nums text-gray-900" data-testid="handoff-current"><?= htmlspecialchars($number ?: '—') ?></span></div>
            <div>Origen: <?= $source === 'db' ? 'guardado desde el panel' : 'variable de entorno HANDOFF_PHONE_NUMBER (aún no editado)' ?></div>
            <?php if ($who): ?><div>Último cambio: <span data-testid="handoff-audit"><?= htmlspecialchars($who) ?></span></div><?php endif; ?>
        </dl>
    </form>
</div>

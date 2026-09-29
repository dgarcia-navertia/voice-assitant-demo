<?php
use App\Icon;

$statusLabels = [
    'queued' => 'En cola', 'ringing' => 'Sonando', 'in-progress' => 'En curso',
    'completed' => 'Completada', 'failed' => 'Fallida', 'busy' => 'Ocupado',
    'no-answer' => 'Sin respuesta', 'canceled' => 'Cancelada',
];
?>

<div class="max-w-3xl mx-auto"
     x-data="dialOut('<?= htmlspecialchars($csrf, ENT_QUOTES) ?>')" x-init="init()">

    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">Dial Out</h1>
        <p class="text-gray-500 mt-1">El asistente de voz llama a este número y atiende la reserva de cita.</p>
    </div>

    <form class="card relative z-20 p-6 sm:p-8" @submit.prevent="dial()" data-testid="dial-form" novalidate>
        <label for="dial-phone" class="label">Teléfono a llamar</label>

        <div class="flex flex-col sm:flex-row gap-3 sm:items-stretch">
            <?php $phoneInputId = 'dial-phone'; $phoneTestId = 'dial-phone'; require __DIR__ . '/../partials/phone-pill.php'; ?>

            <button type="submit" data-testid="dial-submit" :disabled="!valid || busy"
                    class="btn btn-accent min-h-[3.25rem] px-8 text-base disabled:opacity-50 disabled:cursor-not-allowed">
                <?= Icon::svg('phone', 'w-5 h-5') ?>
                <span x-text="busy ? 'Llamando…' : 'Dial'"></span>
            </button>
        </div>

        <p class="mt-3 text-sm" :class="phone && !valid ? 'text-red-600' : 'text-gray-500'" data-testid="dial-hint">
            <template x-if="!phone"><span>Número que se marcará, en formato internacional.</span></template>
            <template x-if="phone && !valid"><span>Número no válido (E.164): <span class="tabular-nums" x-text="e164"></span></span></template>
            <template x-if="phone && valid"><span>Se llamará a <strong class="tabular-nums text-gray-900" x-text="e164"></strong></span></template>
        </p>

        <p x-cloak x-show="error" class="mt-4 p-3 bg-red-50 border border-red-200 rounded-2xl text-red-800 text-sm" role="alert" x-text="error" data-testid="dial-error"></p>
    </form>

    <?php /* Estado en vivo */ ?>
    <section x-cloak x-show="call" class="card p-6 sm:p-8 mt-6" aria-live="polite" data-testid="dial-status-card">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500">Llamada a</p>
                <p class="text-lg font-semibold tabular-nums" x-text="call && call.to"></p>
            </div>
            <span class="pill text-sm" :class="pillClass" data-testid="dial-status" :data-status="call && call.status">
                <span class="w-2 h-2 rounded-full bg-current" :class="live ? 'animate-pulse' : ''"></span>
                <span x-text="statusLabel"></span>
            </span>
        </div>

        <ol class="mt-6 grid grid-cols-4 gap-2 text-center text-xs text-gray-500" aria-hidden="true">
            <template x-for="(step, i) in steps" :key="step.key">
                <li>
                    <div class="h-1.5 rounded-full" :class="i <= stepIndex ? (failed ? 'bg-red-500' : 'bg-primary') : 'bg-gray-200'"></div>
                    <span class="block mt-1.5" :class="i <= stepIndex ? 'text-gray-900 font-medium' : ''" x-text="step.label"></span>
                </li>
            </template>
        </ol>

        <p x-show="call && call.duration_seconds" class="mt-4 text-sm text-gray-500">
            Duración: <span class="tabular-nums" x-text="call && call.duration_seconds"></span> s
        </p>
        <p x-show="call && call.error" class="mt-4 text-sm text-red-700" x-text="call && call.error"></p>

        <div x-show="transcriptUrl" class="mt-5">
            <a :href="transcriptUrl" class="btn btn-primary" data-testid="dial-transcript-link">
                <?= Icon::svg('chat') ?> Ver transcripción
            </a>
        </div>
        <p x-show="terminal && !transcriptUrl && waitingTranscript" class="mt-4 text-sm text-gray-500">Guardando la transcripción…</p>
    </section>

    <?php if ($calls): ?>
    <section class="mt-8">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-semibold">Últimas llamadas</h2>
            <a href="/calls" class="text-sm text-brand-700 hover:underline">Ver todas</a>
        </div>
        <div class="card divide-y divide-gray-200/70 overflow-hidden">
            <?php foreach ($calls as $c): ?>
            <a href="/calls/<?= rawurlencode($c['call_sid']) ?>" class="flex items-center gap-4 px-5 py-3 hover:bg-brand-500/10">
                <span class="tabular-nums font-medium"><?= htmlspecialchars((string) ($c['to_number'] ?? $c['from_number'] ?? '—')) ?></span>
                <span class="text-sm text-gray-500 flex-1"><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></span>
                <span class="pill bg-gray-100 text-gray-700"><?= htmlspecialchars($statusLabels[$c['status']] ?? $c['status']) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</div>

<script>
document.addEventListener('alpine:init', () => {
    const STATUS = {
        'queued': 'En cola', 'ringing': 'Sonando', 'in-progress': 'En curso',
        'completed': 'Completada', 'failed': 'Fallida', 'busy': 'Ocupado',
        'no-answer': 'Sin respuesta', 'canceled': 'Cancelada',
    };
    // Mezcla el estado del selector compartido (con sus getters) en el componente.
    const withPicker = (obj) => Object.defineProperties(obj, Object.getOwnPropertyDescriptors(nvPhonePicker()));
    const BAD = ['failed', 'busy', 'no-answer', 'canceled'];
    const norm = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

    Alpine.data('dialOut', (csrf) => withPicker({
        csrf,
        busy: false, error: '', call: null, timer: null, polls: 0,
        steps: [
            { key: 'queued', label: 'En cola' }, { key: 'ringing', label: 'Sonando' },
            { key: 'in-progress', label: 'En curso' }, { key: 'completed', label: 'Finalizada' },
        ],

        init() { this.initPicker(); },

        get terminal() { return !!(this.call && this.call.terminal); },
        get live() { return !!this.call && !this.terminal; },
        get failed() { return !!this.call && BAD.includes(this.call.status); },
        get statusLabel() { return this.call ? (STATUS[this.call.status] || this.call.status) : ''; },
        get stepIndex() {
            if (!this.call) { return -1; }
            const i = this.steps.findIndex((s) => s.key === this.call.status);
            return i >= 0 ? i : (this.terminal ? 3 : 0);
        },
        get pillClass() {
            if (this.failed) { return 'bg-red-100 text-red-800'; }
            if (this.terminal) { return 'bg-green-100 text-green-800'; }
            return 'bg-brand-100 text-brand-800';
        },
        get transcriptUrl() { return this.call && this.call.transcript_url; },
        get waitingTranscript() { return this.polls < 30; },

        async dial() {
            if (!this.valid || this.busy) { return; }
            this.busy = true; this.error = ''; this.call = null; this.polls = 0;
            clearTimeout(this.timer);
            try {
                const res = await fetch('/dial-out', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.csrf, 'Accept': 'application/json' },
                    body: JSON.stringify({ to: this.e164 }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) { this.error = data.error || 'No se pudo iniciar la llamada.'; return; }
                this.call = { call_sid: data.call_sid, status: data.status || 'queued', to: data.to, terminal: false };
                this.poll();
            } catch (e) {
                this.error = 'No se pudo contactar con el servidor.';
            } finally {
                this.busy = false;
            }
        },
        poll() {
            this.timer = setTimeout(async () => {
                this.polls++;
                try {
                    const res = await fetch('/dial-out/status/' + encodeURIComponent(this.call.call_sid), { headers: { 'Accept': 'application/json' } });
                    if (res.ok) { this.call = Object.assign({}, this.call, await res.json()); }
                } catch (e) { /* reintenta en el siguiente ciclo */ }
                // Sigue hasta estado final y, despues, unos ciclos mas a la espera de la transcripcion.
                if (!this.terminal || (!this.transcriptUrl && this.polls < 30)) { this.poll(); }
            }, 1500);
        },
    }));
});
</script>

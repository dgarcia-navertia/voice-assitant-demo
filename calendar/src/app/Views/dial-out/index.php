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
            <?php /* Pildora de cristal: selector de prefijo + numero */ ?>
            <div class="glass-strong rounded-full flex items-center flex-1 min-h-[3.25rem] relative"
                 :class="phone && !valid ? 'ring-2 ring-red-500/60' : ''">

                <div class="relative" @keydown.escape.window="open = false" @click.outside="open = false">
                    <button type="button" @click="toggle()" data-testid="dial-country-toggle"
                            class="flex items-center gap-2 h-[3.25rem] pl-4 pr-3 rounded-l-full hover:bg-brand-500/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                            :aria-expanded="open" aria-haspopup="listbox" aria-label="Prefijo del país">
                        <img :src="flagUrl(country.iso)" alt="" class="w-6 h-[18px] rounded-[3px] object-cover shadow-sm">
                        <span class="font-semibold text-gray-900" x-text="country.dial" data-testid="dial-country-code"></span>
                        <?= Icon::svg('down', 'w-4 h-4 text-gray-400') ?>
                    </button>

                    <div x-cloak x-show="open" x-transition.opacity
                         class="absolute left-0 top-full mt-2 w-80 max-w-[calc(100vw-3rem)] glass-pop rounded-2xl z-30 overflow-hidden">
                        <div class="p-2 border-b border-gray-200/70">
                            <input type="search" x-model="query" x-ref="search" data-testid="dial-country-search"
                                   placeholder="Buscar país o prefijo…" autocomplete="off"
                                   class="input !min-h-[2.5rem] !text-[15px]" aria-label="Buscar país">
                        </div>
                        <ul class="max-h-72 overflow-y-auto py-1" role="listbox" data-testid="dial-country-list">
                            <template x-for="c in filtered" :key="c.iso">
                                <li role="option" :aria-selected="c.iso === country.iso">
                                    <button type="button" @click="select(c)"
                                            :data-iso="c.iso"
                                            class="w-full flex items-center gap-3 px-4 py-2 text-left hover:bg-brand-500/10"
                                            :class="c.iso === country.iso ? 'bg-brand-500/15' : ''">
                                        <img :src="flagUrl(c.iso)" alt="" loading="lazy" class="w-6 h-[18px] rounded-[3px] object-cover shadow-sm">
                                        <span class="flex-1 truncate text-gray-900" x-text="c.name"></span>
                                        <span class="text-gray-500 text-sm tabular-nums" x-text="c.dial"></span>
                                    </button>
                                </li>
                            </template>
                            <li x-show="filtered.length === 0" class="px-4 py-3 text-sm text-gray-500">Sin resultados</li>
                        </ul>
                    </div>
                </div>

                <span class="w-px h-6 bg-gray-300/70" aria-hidden="true"></span>

                <input id="dial-phone" type="tel" inputmode="tel" autocomplete="tel-national"
                       x-model="phone" data-testid="dial-phone" placeholder="612 345 678"
                       class="flex-1 min-w-0 h-[3.25rem] bg-transparent border-0 text-lg text-gray-900 placeholder:text-gray-400 px-4 rounded-r-full focus:ring-0">
            </div>

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
    const BAD = ['failed', 'busy', 'no-answer', 'canceled'];
    const norm = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

    Alpine.data('dialOut', (csrf) => ({
        csrf,
        countries: [],
        country: { iso: 'ES', name: 'España', dial: '+34' },
        open: false, query: '', phone: '',
        busy: false, error: '', call: null, timer: null, polls: 0,
        steps: [
            { key: 'queued', label: 'En cola' }, { key: 'ringing', label: 'Sonando' },
            { key: 'in-progress', label: 'En curso' }, { key: 'completed', label: 'Finalizada' },
        ],

        init() {
            const names = new Intl.DisplayNames(['es'], { type: 'region' });
            const list = (window.NV_COUNTRIES || []).map(([iso, dial]) => ({ iso, dial, name: names.of(iso) || iso }));
            list.sort((a, b) => a.name.localeCompare(b.name, 'es'));
            this.countries = list;
            this.country = list.find((c) => c.iso === 'ES') || this.country;
        },
        flagUrl(iso) { return '/static/flags/' + iso.toLowerCase() + '.svg'; },
        toggle() {
            this.open = !this.open;
            if (this.open) { this.query = ''; this.$nextTick(() => this.$refs.search && this.$refs.search.focus()); }
        },
        select(c) { this.country = c; this.open = false; },
        get filtered() {
            const q = norm(this.query.trim());
            if (!q) { return this.countries; }
            return this.countries.filter((c) =>
                norm(c.name).includes(q) || c.dial.includes(q) || c.dial.replace('+', '').startsWith(q.replace('+', '')) || c.iso.toLowerCase() === q);
        },
        get e164() {
            const raw = this.phone.replace(/[\s\-().]/g, '');
            if (raw.startsWith('+')) { return raw; }
            return this.country.dial + raw.replace(/^0+/, '');
        },
        get valid() { return /^\+[1-9]\d{6,14}$/.test(this.e164); },

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

// Selector de prefijo + telefono compartido (Dial Out y Ajustes). Sin CDN:
// banderas SVG locales, nombres con Intl.DisplayNames('es'). Uso:
//   x-data="phonePicker()"  (o Object.defineProperties sobre nvPhonePicker(), ver dial-out)
(function () {
    const norm = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

    window.nvPhonePicker = function () {
        return {
            countries: [],
            country: { iso: 'ES', name: 'España', dial: '+34' },
            open: false, query: '', phone: '',

            initPicker(e164) {
                const names = new Intl.DisplayNames(['es'], { type: 'region' });
                const list = (window.NV_COUNTRIES || []).map(([iso, dial]) => ({ iso, dial, name: names.of(iso) || iso }));
                list.sort((a, b) => a.name.localeCompare(b.name, 'es'));
                this.countries = list;
                this.country = list.find((c) => c.iso === 'ES') || this.country;
                if (e164) { this.setFromE164(e164); }
            },
            // Reparte "+34612345678" en prefijo (el mas largo que case; ES gana los empates) y resto.
            setFromE164(e164) {
                let best = null;
                for (const c of this.countries) {
                    if (e164.startsWith(c.dial) && (!best || c.dial.length > best.dial.length || (c.dial.length === best.dial.length && c.iso === 'ES'))) { best = c; }
                }
                if (best) { this.country = best; this.phone = e164.slice(best.dial.length); } else { this.phone = e164; }
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
        };
    };
})();

document.addEventListener('alpine:init', function () {
    Alpine.data('phonePicker', function () { return window.nvPhonePicker(); });
});

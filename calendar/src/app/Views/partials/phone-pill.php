<?php
// Pildora de cristal: selector de prefijo (todas las banderas, buscable) + telefono.
// Requiere un scope Alpine con nvPhonePicker() (ver static/js/phone-picker.js).
// $phoneInputId / $phoneInputName / $phoneTestId opcionales.
use App\Icon;
$phoneInputId = $phoneInputId ?? 'dial-phone';
$phoneTestId  = $phoneTestId ?? 'dial-phone';
?>
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

        <input id="<?= $phoneInputId ?>" type="tel" inputmode="tel" autocomplete="tel-national"
               x-model="phone" data-testid="<?= $phoneTestId ?>" placeholder="612 345 678"
               class="flex-1 min-w-0 h-[3.25rem] bg-transparent border-0 text-lg text-gray-900 placeholder:text-gray-400 px-4 rounded-r-full focus:ring-0">
    </div>


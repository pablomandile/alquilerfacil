<script setup lang="ts">
import { computed, ref } from "vue";
import { pesos, pesosRedondos } from "@/lib/formato";

const props = withDefaults(
    defineProps<{
        /** Ya vienen de mayor a menor. Los montos viajan como string. */
        items: Array<{ id: number | string; etiqueta: string; valor: string }>;
        /** Lo que dice el centro cuando no hay nada señalado. */
        etiquetaTotal?: string;
    }>(),
    { etiquetaTotal: "Total" },
);

// Los colores de la paleta son cinco: el resto se junta en «Otras».
const MAXIMO = 5;

// Un anillo de radio 38 en un lienzo de 100: la circunferencia es lo que se
// reparte entre las porciones.
const RADIO = 38;
const CIRCUNFERENCIA = 2 * Math.PI * RADIO;
// Separación entre porciones, para que no se pisen los colores.
const HUECO = 1.2;

const porcentajeFmt = new Intl.NumberFormat("es-AR", {
    style: "percent",
    maximumFractionDigits: 1,
});

const activo = ref<number | null>(null);

const porciones = computed(() => {
    const sueltos = props.items.slice(0, MAXIMO);
    const resto = props.items.slice(MAXIMO);

    const lista = sueltos.map((i, n) => ({
        clave: String(i.id),
        etiqueta: i.etiqueta,
        valor: Number(i.valor),
        color: `var(--torta-${n + 1})`,
    }));

    if (resto.length) {
        lista.push({
            clave: "otras",
            etiqueta: `Otras (${resto.length})`,
            valor: resto.reduce((acc, i) => acc + Number(i.valor), 0),
            color: "var(--torta-resto)",
        });
    }

    const total = lista.reduce((acc, p) => acc + p.valor, 0);
    let acumulado = 0;

    return lista.map((p) => {
        const parte = total > 0 ? p.valor / total : 0;
        const largo = parte * CIRCUNFERENCIA;
        const arco = {
            ...p,
            parte,
            // Con una sola porción el anillo cierra solo, sin hueco.
            largo: lista.length > 1 ? Math.max(largo - HUECO, 0) : largo,
            desde: acumulado,
        };
        acumulado += largo;

        return arco;
    });
});

const total = computed(() =>
    porciones.value.reduce((acc, p) => acc + p.valor, 0),
);

const seleccionada = computed(() =>
    activo.value === null ? null : (porciones.value[activo.value] ?? null),
);

function alternar(indice: number) {
    activo.value = activo.value === indice ? null : indice;
}
</script>

<template>
    <div
        class="grafico-torta flex flex-col items-center gap-5 p-4 sm:flex-row sm:gap-6"
        @mouseleave="activo = null"
    >
        <div class="relative size-44 shrink-0">
            <svg
                viewBox="0 0 100 100"
                class="size-full -rotate-90"
                role="img"
                :aria-label="`${etiquetaTotal}: ${pesosRedondos(total)}`"
            >
                <circle
                    v-for="(p, n) in porciones"
                    :key="p.clave"
                    cx="50"
                    cy="50"
                    :r="RADIO"
                    fill="none"
                    :stroke="p.color"
                    :stroke-width="activo === n ? 15 : 12"
                    :stroke-dasharray="`${p.largo} ${CIRCUNFERENCIA - p.largo}`"
                    :stroke-dashoffset="-p.desde"
                    :opacity="activo === null || activo === n ? 1 : 0.35"
                    class="cursor-pointer transition-[stroke-width,opacity]"
                    @mouseenter="activo = n"
                    @click="alternar(n)"
                />
            </svg>

            <!-- El centro dice el total, o lo de la porción señalada. -->
            <div
                class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center px-8 text-center"
            >
                <span
                    class="text-muted-foreground line-clamp-1 text-xs"
                    :title="seleccionada?.etiqueta"
                >
                    {{ seleccionada?.etiqueta ?? etiquetaTotal }}
                </span>
                <span class="text-base font-semibold tabular-nums">
                    {{ pesosRedondos(seleccionada?.valor ?? total) }}
                </span>
                <span
                    v-if="seleccionada"
                    class="text-muted-foreground text-xs tabular-nums"
                >
                    {{ porcentajeFmt.format(seleccionada.parte) }}
                </span>
            </div>
        </div>

        <!-- La leyenda es también la tabla: nombre, monto y porcentaje. -->
        <ul class="w-full min-w-0 flex-1 space-y-1 text-sm">
            <li
                v-for="(p, n) in porciones"
                :key="p.clave"
                class="hover:bg-foreground/5 flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 transition-opacity"
                :class="activo !== null && activo !== n ? 'opacity-50' : ''"
                @mouseenter="activo = n"
                @click="alternar(n)"
            >
                <span
                    class="size-2.5 shrink-0 rounded-full"
                    :style="{ background: p.color }"
                    aria-hidden="true"
                />
                <span class="min-w-0 flex-1 truncate">{{ p.etiqueta }}</span>
                <span class="text-muted-foreground text-xs tabular-nums">
                    {{ porcentajeFmt.format(p.parte) }}
                </span>
                <span class="w-28 text-right tabular-nums">
                    {{ pesos(p.valor) }}
                </span>
            </li>
        </ul>
    </div>
</template>

<style scoped>
/* Paleta categórica en orden fijo (azul, naranja, aqua, amarillo, magenta) y
   un gris neutro para «Otras». En oscuro son los mismos matices con los pasos
   pensados para ese fondo. */
.grafico-torta {
    --torta-1: #2a78d6;
    --torta-2: #eb6834;
    --torta-3: #1baf7a;
    --torta-4: #eda100;
    --torta-5: #e87ba4;
    --torta-resto: #898884;
}

:global(.dark) .grafico-torta {
    --torta-1: #3987e5;
    --torta-2: #d95926;
    --torta-3: #199e70;
    --torta-4: #c98500;
    --torta-5: #d55181;
}
</style>

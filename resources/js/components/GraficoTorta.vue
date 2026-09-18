<script setup lang="ts">
import { computed, ref } from 'vue';
import { pesos, pesosRedondos } from '@/lib/formato';

const props = defineProps<{
    /** Ya vienen en el orden fijo de la leyenda. Los montos, como string, y el
     *  color es el número de slot de la paleta: lo fija la categoría, no el
     *  ranking, así que es el mismo en todas las tortas. */
    porciones: Array<{
        clave: string;
        etiqueta: string;
        monto: string;
        color: number;
    }>;
    /** El nombre de la propiedad: va abajo del anillo. */
    titulo: string;
}>();

// Un anillo de radio 38 en un lienzo de 100: la circunferencia es lo que se
// reparte entre las porciones.
const RADIO = 38;
const CIRCUNFERENCIA = 2 * Math.PI * RADIO;
// Separación entre porciones, para que no se toquen los colores.
const HUECO = 1.5;

const porcentajeFmt = new Intl.NumberFormat('es-AR', {
    style: 'percent',
    maximumFractionDigits: 1,
});

const activa = ref<number | null>(null);

const total = computed(() =>
    props.porciones.reduce((acc, p) => acc + Number(p.monto), 0),
);

const arcos = computed(() => {
    let acumulado = 0;

    return props.porciones.map((p) => {
        const valor = Number(p.monto);
        const parte = total.value > 0 ? valor / total.value : 0;
        const largo = parte * CIRCUNFERENCIA;
        const arco = {
            ...p,
            valor,
            parte,
            color: `var(--torta-${p.color})`,
            // Con una sola porción el anillo cierra solo, sin hueco.
            largo:
                props.porciones.length > 1 ? Math.max(largo - HUECO, 0) : largo,
            desde: acumulado,
        };
        acumulado += largo;

        return arco;
    });
});

const senalada = computed(() =>
    activa.value === null ? null : (arcos.value[activa.value] ?? null),
);

/** Para quien no ve el gráfico: el desglose entero en una frase. */
const descripcion = computed(
    () =>
        `${props.titulo}: ${pesosRedondos(total.value)} en gastos. ` +
        arcos.value
            .map(
                (a) =>
                    `${a.etiqueta} ${pesos(a.valor)} (${porcentajeFmt.format(a.parte)})`,
            )
            .join(', '),
);

function alternar(indice: number) {
    activa.value = activa.value === indice ? null : indice;
}
</script>

<template>
    <figure
        class="flex flex-col items-center gap-2"
        @mouseleave="activa = null"
    >
        <div class="relative size-32">
            <svg
                viewBox="0 0 100 100"
                class="size-full -rotate-90"
                role="img"
                :aria-label="descripcion"
            >
                <circle
                    v-for="(a, n) in arcos"
                    :key="a.clave"
                    cx="50"
                    cy="50"
                    :r="RADIO"
                    fill="none"
                    :stroke="a.color"
                    :stroke-width="activa === n ? 16 : 13"
                    :stroke-dasharray="`${a.largo} ${CIRCUNFERENCIA - a.largo}`"
                    :stroke-dashoffset="-a.desde"
                    :opacity="activa === null || activa === n ? 1 : 0.35"
                    class="cursor-pointer transition-[stroke-width,opacity]"
                    @mouseenter="activa = n"
                    @click="alternar(n)"
                />
            </svg>

            <!-- El centro dice siempre el total: no se mueve al señalar. -->
            <div
                class="pointer-events-none absolute inset-0 flex items-center justify-center px-6 text-center"
            >
                <span class="text-sm font-semibold tabular-nums">
                    {{ pesosRedondos(total) }}
                </span>
            </div>
        </div>

        <!-- Abajo el nombre de la propiedad; al señalar una porción, su detalle. -->
        <figcaption class="w-full min-w-0 text-center text-xs">
            <template v-if="senalada">
                <span class="block truncate font-medium">
                    {{ senalada.etiqueta }}
                </span>
                <span class="text-muted-foreground tabular-nums">
                    {{ pesos(senalada.valor) }} ·
                    {{ porcentajeFmt.format(senalada.parte) }}
                </span>
            </template>
            <template v-else>
                <span class="block truncate font-medium">{{ titulo }}</span>
                <span class="text-muted-foreground">
                    {{ porciones.length }}
                    {{ porciones.length === 1 ? 'categoría' : 'categorías' }}
                </span>
            </template>
        </figcaption>
    </figure>
</template>

<script setup lang="ts">
import { useElementSize } from '@vueuse/core';
import { computed, ref, useTemplateRef } from 'vue';
import { pesos, pesosCompactos } from '@/lib/formato';

const props = defineProps<{
    /** Un punto por mes, en orden. Los montos, como string; null es «ese mes
     *  no existe» y corta la línea. */
    meses: Array<{
        clave: string;
        alquileres: string | null;
        gastos: string | null;
    }>;
}>();

// Se dibuja en píxeles reales, midiendo el contenedor, y no con un viewBox que
// escale: así el texto de los ejes se lee igual en el celular que en el monitor.
const ALTO = 200;
const MARGEN = { arriba: 14, derecha: 16, abajo: 24, izquierda: 56 };

const contenedor = useTemplateRef<HTMLElement>('contenedor');
const { width } = useElementSize(contenedor);

const ancho = computed(() => Math.max(280, Math.round(width.value)));
const areaAncho = computed(
    () => ancho.value - MARGEN.izquierda - MARGEN.derecha,
);
const areaAlto = ALTO - MARGEN.arriba - MARGEN.abajo;

const SERIES = [
    { clave: 'alquileres', etiqueta: 'Alquileres', color: 'var(--linea-1)' },
    { clave: 'gastos', etiqueta: 'Gastos', color: 'var(--linea-2)' },
] as const;

const mesFmt = new Intl.DateTimeFormat('es-AR', {
    month: 'short',
    year: '2-digit',
});
const mesLargoFmt = new Intl.DateTimeFormat('es-AR', {
    month: 'long',
    year: 'numeric',
});

/** 'Y-m' a fecha local, sin que el huso corra el mes. */
function aFecha(clave: string): Date {
    const [anio, mes] = clave.split('-').map(Number);

    return new Date(anio, mes - 1, 1);
}

const activo = ref<number | null>(null);

/** El techo del eje, redondeado a un número limpio. */
const tope = computed(() => {
    const max = Math.max(
        1,
        ...props.meses.flatMap((m) =>
            [m.alquileres, m.gastos]
                .filter((v): v is string => v !== null)
                .map(Number),
        ),
    );
    const escala = 10 ** Math.floor(Math.log10(max));

    return Math.ceil(max / (escala / 2)) * (escala / 2);
});

const x = (n: number) =>
    MARGEN.izquierda +
    (props.meses.length === 1
        ? areaAncho.value / 2
        : (n / (props.meses.length - 1)) * areaAncho.value);

const y = (valor: number) =>
    MARGEN.arriba + areaAlto - (valor / tope.value) * areaAlto;

/** Los puntos de cada serie, salteando los meses sin dato. */
const puntos = computed(() =>
    SERIES.map((serie) => {
        const suyos = props.meses
            .map((m, n) => ({ n, valor: m[serie.clave] }))
            .filter((p): p is { n: number; valor: string } => p.valor !== null)
            .map((p) => ({ x: x(p.n), y: y(Number(p.valor)) }));

        return {
            ...serie,
            d: suyos
                .map(
                    (p, i) =>
                        `${i === 0 ? 'M' : 'L'} ${p.x.toFixed(1)} ${p.y.toFixed(1)}`,
                )
                .join(' '),
            ultimo: suyos.at(-1),
        };
    }),
);

/** Cuatro marcas: 0, un tercio, dos tercios y el tope. */
const marcas = computed(() =>
    [0, 1, 2, 3].map((n) => {
        const valor = (tope.value / 3) * n;

        return { valor, y: y(valor) };
    }),
);

/** Los meses que se rotulan: los que entren sin pisarse al ancho que haya. */
const etiquetas = computed(() => {
    const cuantas = Math.max(2, Math.floor(areaAncho.value / 64));
    const salto = Math.ceil(props.meses.length / cuantas);

    return props.meses
        .map((mes, n) => ({ clave: mes.clave, n }))
        .filter(({ n }) => n % salto === 0 || n === props.meses.length - 1)
        .map(({ clave, n }) => ({
            clave,
            n,
            x: x(n),
            texto: mesFmt.format(aFecha(clave)),
            // Los de las puntas se anclan para adentro, si no se salen del SVG.
            anclaje:
                n === 0
                    ? 'start'
                    : n === props.meses.length - 1
                      ? 'end'
                      : 'middle',
        }));
});

const detalle = computed(() => {
    if (activo.value === null) return null;
    const mes = props.meses[activo.value];
    if (!mes) return null;

    return {
        mes: mesLargoFmt.format(aFecha(mes.clave)),
        x: x(activo.value),
        valores: SERIES.map((s) => ({
            ...s,
            valor: mes[s.clave],
            y: mes[s.clave] === null ? null : y(Number(mes[s.clave])),
        })),
    };
});

/** El puntero apunta a un mes, no a un píxel: se busca el más cercano. */
function seguir(evento: PointerEvent) {
    const caja = (
        evento.currentTarget as SVGSVGElement
    ).getBoundingClientRect();
    if (props.meses.length === 0) return;

    const enLienzo = evento.clientX - caja.left - MARGEN.izquierda;
    const paso =
        props.meses.length === 1
            ? 1
            : areaAncho.value / (props.meses.length - 1);

    activo.value = Math.max(
        0,
        Math.min(props.meses.length - 1, Math.round(enLienzo / paso)),
    );
}
</script>

<template>
    <figure ref="contenedor" class="flex flex-col gap-3">
        <!-- Leyenda: dos series, así que va siempre. -->
        <ul class="flex flex-wrap gap-x-4 gap-y-1 text-xs">
            <li
                v-for="s in SERIES"
                :key="s.clave"
                class="flex items-center gap-1.5"
            >
                <span
                    class="h-0.5 w-4 shrink-0 rounded-full"
                    :style="{ background: s.color }"
                    aria-hidden="true"
                />
                {{ s.etiqueta }}
            </li>
        </ul>

        <svg
            :width="ancho"
            :height="ALTO"
            class="touch-pan-y"
            role="img"
            :aria-label="`Alquileres facturados y gastos de los últimos ${meses.length} meses.`"
            @pointermove="seguir"
            @pointerleave="activo = null"
        >
            <!-- Grilla y ejes: finos, sólidos y discretos. -->
            <g class="text-muted-foreground">
                <line
                    v-for="m in marcas"
                    :key="m.valor"
                    :x1="MARGEN.izquierda"
                    :x2="ancho - MARGEN.derecha"
                    :y1="m.y"
                    :y2="m.y"
                    stroke="currentColor"
                    stroke-width="1"
                    opacity="0.18"
                />
                <text
                    v-for="m in marcas"
                    :key="`t-${m.valor}`"
                    :x="MARGEN.izquierda - 8"
                    :y="m.y + 3.5"
                    text-anchor="end"
                    fill="currentColor"
                    class="text-[10px] tabular-nums"
                >
                    {{ pesosCompactos(m.valor) }}
                </text>

                <text
                    v-for="e in etiquetas"
                    :key="e.clave"
                    :x="e.x"
                    :y="ALTO - 6"
                    :text-anchor="e.anclaje"
                    fill="currentColor"
                    class="text-[10px]"
                >
                    {{ e.texto }}
                </text>
            </g>

            <!-- El mes señalado, detrás de las líneas. -->
            <line
                v-if="detalle"
                :x1="detalle.x"
                :x2="detalle.x"
                :y1="MARGEN.arriba"
                :y2="MARGEN.arriba + areaAlto"
                class="text-muted-foreground"
                stroke="currentColor"
                stroke-width="1"
                opacity="0.45"
            />

            <path
                v-for="serie in puntos"
                :key="serie.clave"
                :d="serie.d"
                fill="none"
                :stroke="serie.color"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            />

            <!-- Punto final de cada serie, con aro del color de la tarjeta. -->
            <template v-for="serie in puntos" :key="`fin-${serie.clave}`">
                <circle
                    v-if="serie.ultimo"
                    :cx="serie.ultimo.x"
                    :cy="serie.ultimo.y"
                    r="4"
                    :fill="serie.color"
                    stroke="var(--card-desde)"
                    stroke-width="2"
                />
            </template>

            <!-- Los puntos del mes señalado. -->
            <template v-if="detalle">
                <template v-for="v in detalle.valores" :key="`sel-${v.clave}`">
                    <circle
                        v-if="v.y !== null"
                        :cx="detalle.x"
                        :cy="v.y"
                        r="4.5"
                        :fill="v.color"
                        stroke="var(--card-desde)"
                        stroke-width="2"
                    />
                </template>
            </template>
        </svg>

        <!-- El detalle va en texto abajo y no flotando: en el celular un globo
             encima del dedo tapa justo lo que se quiere leer. -->
        <figcaption class="min-h-14 text-xs">
            <template v-if="detalle">
                <p class="font-medium first-letter:uppercase">
                    {{ detalle.mes }}
                </p>
                <ul class="mt-1 space-y-0.5">
                    <li
                        v-for="v in detalle.valores"
                        :key="v.clave"
                        class="flex items-center gap-2"
                    >
                        <span
                            class="h-0.5 w-4 shrink-0 rounded-full"
                            :style="{ background: v.color }"
                            aria-hidden="true"
                        />
                        <span class="font-semibold tabular-nums">
                            {{ v.valor === null ? '—' : pesos(v.valor) }}
                        </span>
                        <span class="text-muted-foreground">
                            {{ v.etiqueta.toLowerCase() }}
                        </span>
                    </li>
                </ul>
            </template>
            <p v-else class="text-muted-foreground">
                Pasá el mouse o tocá el gráfico para ver cada mes.
            </p>
        </figcaption>
    </figure>
</template>

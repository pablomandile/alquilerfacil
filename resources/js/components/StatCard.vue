<script setup lang="ts">
import { computed, type Component } from 'vue';

type Tinte =
    | 'indigo'
    | 'esmeralda'
    | 'ambar'
    | 'violeta'
    | 'rosa'
    | 'cielo'
    | 'turquesa';

const props = defineProps<{
    etiqueta: string;
    valor: string | number;
    detalle?: string;
    icono?: Component;
    /** Color del número. Es semántico: depende del dato, no de la tarjeta. */
    acento?: 'normal' | 'positivo' | 'atencion' | 'alerta';
    /** Color de la tarjeta. Es decorativo: fijo para cada una del panel. */
    tinte?: Tinte;
}>();

const acentos = {
    normal: 'text-foreground',
    positivo: 'text-emerald-600 dark:text-emerald-400',
    atencion: 'text-amber-600 dark:text-amber-400',
    alerta: 'text-rose-600 dark:text-rose-400',
};

/* Los nombres van enteros y no armados con template string: Tailwind busca las
   clases como texto literal en el archivo y no generaría `tinte-${...}`. */
const tintes: Record<Tinte, string> = {
    indigo: 'tinte-indigo',
    esmeralda: 'tinte-esmeralda',
    ambar: 'tinte-ambar',
    violeta: 'tinte-violeta',
    rosa: 'tinte-rosa',
    cielo: 'tinte-cielo',
    turquesa: 'tinte-turquesa',
};

// El `tinte-*` ya trae su propio borde; sin tinte va el neutro de siempre.
const borde = computed(() =>
    props.tinte
        ? tintes[props.tinte]
        : 'border-sidebar-border/70 dark:border-sidebar-border',
);
</script>

<template>
    <div :class="['tarjeta rounded-xl border p-4', borde]">
        <div class="flex items-start justify-between gap-2">
            <p class="text-muted-foreground text-sm">{{ etiqueta }}</p>
            <component
                :is="icono"
                v-if="icono"
                class="icono-tarjeta size-4 shrink-0"
            />
        </div>
        <p
            :class="[
                'mt-2 text-2xl font-semibold tracking-tight tabular-nums',
                acentos[acento ?? 'normal'],
            ]"
        >
            {{ valor }}
        </p>
        <p v-if="detalle" class="text-muted-foreground mt-1 text-xs">
            {{ detalle }}
        </p>
    </div>
</template>

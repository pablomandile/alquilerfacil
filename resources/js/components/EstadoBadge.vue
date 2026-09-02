<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

/**
 * Badge de estado. Los colores los decide el enum en PHP (`color()`), así que
 * agregar un estado nuevo no obliga a tocar esta lista.
 */
const props = defineProps<{
    estado: string;
    label: string;
}>();

const colores: Record<string, string> = {
    // Cobranzas
    pendiente:
        'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    parcial: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    pagado: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    vencido: 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300',
    // Ajustes
    propuesto: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    aplicado:
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    rechazado:
        'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
    // Propiedades y contratos
    disponible: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    alquilada:
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    en_refaccion:
        'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    activo: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    finalizado:
        'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
    rescindido: 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300',
};

const clase = computed(
    () =>
        colores[props.estado] ??
        'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
);
</script>

<template>
    <span
        :class="
            cn(
                'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                clase,
            )
        "
    >
        {{ label }}
    </span>
</template>

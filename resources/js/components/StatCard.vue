<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    etiqueta: string;
    valor: string | number;
    detalle?: string;
    icono?: Component;
    acento?: 'normal' | 'positivo' | 'atencion' | 'alerta';
}>();

const acentos = {
    normal: 'text-foreground',
    positivo: 'text-emerald-600 dark:text-emerald-400',
    atencion: 'text-amber-600 dark:text-amber-400',
    alerta: 'text-rose-600 dark:text-rose-400',
};
</script>

<template>
    <div
        class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
    >
        <div class="flex items-start justify-between gap-2">
            <p class="text-muted-foreground text-sm">{{ etiqueta }}</p>
            <component
                :is="icono"
                v-if="icono"
                class="text-muted-foreground/60 size-4 shrink-0"
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

<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    titulo: string;
    descripcion?: string;
    icono?: Component;
}>();
</script>

<template>
    <div
        class="border-sidebar-border/70 dark:border-sidebar-border flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed px-6 py-12 text-center"
    >
        <component
            :is="icono"
            v-if="icono"
            class="text-muted-foreground/60 size-8"
        />
        <p class="font-medium">{{ titulo }}</p>
        <p v-if="descripcion" class="text-muted-foreground max-w-md text-sm">
            {{ descripcion }}
        </p>
        <div class="mt-2">
            <slot />
        </div>
    </div>
</template>

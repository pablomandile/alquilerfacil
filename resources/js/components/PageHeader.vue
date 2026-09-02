<script setup lang="ts">
defineProps<{
    titulo: string;
    descripcion?: string;
}>();
</script>

<template>
    <div
        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
    >
        <div class="min-w-0">
            <h1 class="truncate text-xl font-semibold tracking-tight">
                {{ titulo }}
            </h1>
            <!-- first-letter y no capitalize: varias descripciones arrancan con
                 un mes ("septiembre de 2026") y capitalize pondría "De". -->
            <p
                v-if="descripcion"
                class="text-muted-foreground mt-1 text-sm first-letter:uppercase"
            >
                {{ descripcion }}
            </p>
        </div>

        <div class="flex shrink-0 flex-wrap items-center gap-2">
            <slot name="acciones" />
        </div>
    </div>
</template>

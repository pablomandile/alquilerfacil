<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { SidebarProvider } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';

type Props = {
    variant?: AppVariant;
};

withDefaults(defineProps<Props>(), {
    variant: 'sidebar',
});

const isOpen = usePage().props.sidebarOpen;
</script>

<template>
    <div v-if="variant === 'header'" class="flex min-h-screen w-full flex-col">
        <slot />
    </div>
    <!-- bg-transparent: el marco de la variante "inset" deja ver el degradado
         azul del body en vez de taparlo con un color plano. -->
    <SidebarProvider
        v-else
        :default-open="isOpen"
        class="has-data-[variant=inset]:bg-transparent"
    >
        <slot />
    </SidebarProvider>
</template>

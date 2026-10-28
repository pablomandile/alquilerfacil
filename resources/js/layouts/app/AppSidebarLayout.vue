<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <!-- Panel traslúcido: deja pasar el degradado azul del fondo, sin
             perder legibilidad porque el contenido va sobre fichas opacas. -->
        <AppContent
            variant="sidebar"
            class="bg-background/50 dark:bg-background/70 min-w-0 overflow-x-clip"
        >
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <!-- Solo en la demo: que nadie cargue datos reales en una cuenta compartida. -->
            <p
                v-if="$page.props.demo"
                class="border-b bg-amber-50 px-4 py-2 text-sm text-amber-900 md:px-6 dark:bg-amber-950 dark:text-amber-200"
            >
                Estás en la demo: los datos son inventados y se borran cada
                noche. No cargues datos reales.
            </p>
            <slot />
        </AppContent>
        <Toaster />
    </AppShell>
</template>

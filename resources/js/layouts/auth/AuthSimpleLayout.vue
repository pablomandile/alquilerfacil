<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10"
    >
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <Link
                        :href="home()"
                        class="flex flex-col items-center gap-2 font-medium"
                    >
                        <!-- Fondo blanco fijo (no `bg-card`): el logo lleva el
                             texto "alquiler" en azul oscuro, que en modo oscuro
                             se pierde contra el degradado de fondo si no tiene
                             una superficie clara detrás. -->
                        <span class="rounded-[28px] bg-white p-4 shadow-sm">
                            <img
                                src="/img/logo.webp"
                                alt="Alquiler Fácil"
                                class="size-28"
                            />
                        </span>
                        <span class="sr-only">{{ title }}</span>
                    </Link>
                    <div class="space-y-2 text-center">
                        <h1 class="text-xl font-medium">{{ title }}</h1>
                        <p class="text-muted-foreground text-center text-sm">
                            {{ description }}
                        </p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Download, FileText, X } from '@lucide/vue';
import {
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';
import { computed, onUnmounted } from 'vue';

/**
 * Visor a pantalla completa para los adjuntos: imágenes y PDF se ven en el
 * momento, el resto (Word) ofrece la descarga. `verUrl` sirve el archivo inline;
 * `descargarUrl` fuerza la descarga.
 */
export type ArchivoVisible = {
    nombre: string;
    mime: string;
    verUrl: string;
    descargarUrl: string;
};

const archivo = defineModel<ArchivoVisible | null>('archivo', {
    default: null,
});

const abierto = computed({
    get: () => archivo.value !== null,
    set: (v) => {
        if (!v) archivo.value = null;
    },
});

const tipo = computed(() => {
    const m = archivo.value?.mime ?? '';
    if (m.startsWith('image/')) return 'imagen';
    if (m === 'application/pdf') return 'pdf';
    return 'otro';
});

// Si el usuario navega (incluido "atrás" del browser) con el visor abierto,
// que no quede tapando la pantalla nueva.
onUnmounted(router.on('navigate', () => (archivo.value = null)));
</script>

<template>
    <DialogRoot v-model:open="abierto">
        <DialogPortal>
            <DialogOverlay
                class="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 fixed inset-0 z-50 bg-black/90 backdrop-blur-sm"
            />
            <DialogContent
                class="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 fixed inset-0 z-50 flex flex-col focus:outline-none"
                @open-auto-focus="(e: Event) => e.preventDefault()"
            >
                <DialogTitle class="sr-only">
                    {{ archivo?.nombre }}
                </DialogTitle>
                <DialogDescription class="sr-only">
                    Vista del archivo adjunto
                </DialogDescription>

                <!-- Barra superior -->
                <div
                    class="flex shrink-0 items-center justify-between gap-3 bg-black/40 px-4 py-2 text-white"
                >
                    <p class="truncate text-sm">{{ archivo?.nombre }}</p>
                    <div class="flex shrink-0 items-center gap-1">
                        <a
                            v-if="archivo"
                            :href="archivo.descargarUrl"
                            class="cursor-pointer rounded-md p-2 hover:bg-white/15"
                        >
                            <Download class="size-5" />
                            <span class="sr-only">Descargar</span>
                        </a>
                        <DialogClose
                            class="cursor-pointer rounded-md p-2 hover:bg-white/15"
                            aria-label="Cerrar"
                        >
                            <X class="size-5" />
                        </DialogClose>
                    </div>
                </div>

                <!-- Contenido -->
                <div
                    class="flex flex-1 items-center justify-center overflow-auto p-2 sm:p-6"
                    @click.self="abierto = false"
                >
                    <img
                        v-if="tipo === 'imagen' && archivo"
                        :src="archivo.verUrl"
                        :alt="archivo.nombre"
                        class="max-h-full max-w-full object-contain"
                    />
                    <iframe
                        v-else-if="tipo === 'pdf' && archivo"
                        :src="archivo.verUrl"
                        :title="archivo.nombre"
                        class="h-full w-full rounded-md bg-white"
                    />
                    <div
                        v-else-if="archivo"
                        class="rounded-xl bg-white/10 p-8 text-center text-white"
                    >
                        <FileText class="mx-auto size-12 opacity-80" />
                        <p class="mt-3 text-sm">
                            Este archivo no se puede previsualizar acá.
                        </p>
                        <a
                            :href="archivo.descargarUrl"
                            class="mt-4 inline-flex items-center gap-2 rounded-md bg-white/15 px-3 py-1.5 text-sm hover:bg-white/25"
                        >
                            <Download class="size-4" />
                            Descargar {{ archivo.nombre }}
                        </a>
                    </div>
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>

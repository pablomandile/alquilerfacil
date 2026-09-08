<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Download, Share, X } from '@lucide/vue';
import { onUnmounted, ref } from 'vue';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { usePwaInstall } from '@/composables/usePwaInstall';

const { sePuedeInstalar, esIos, instalar } = usePwaInstall();

const ayuda = ref(false);
const ocupado = ref(false);

// Que el instructivo no quede tapando la pantalla si el usuario navega.
onUnmounted(router.on('navigate', () => (ayuda.value = false)));

async function alTocar() {
    if (ocupado.value) return;
    ocupado.value = true;
    try {
        // 'manual' = iOS, o el prompt nativo ya se usó: mostramos los pasos.
        if ((await instalar()) === 'manual') ayuda.value = true;
    } finally {
        ocupado.value = false;
    }
}
</script>

<template>
    <SidebarMenu v-if="sePuedeInstalar">
        <SidebarMenuItem>
            <SidebarMenuButton
                tooltip="Instalar app"
                :disabled="ocupado"
                @click="alTocar"
            >
                <Download />
                <span>Instalar app</span>
            </SidebarMenuButton>
        </SidebarMenuItem>
    </SidebarMenu>

    <Teleport to="body">
        <div
            v-if="ayuda"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
            @click.self="ayuda = false"
        >
            <div
                class="bg-background w-full max-w-sm rounded-xl border p-5 shadow-lg"
            >
                <div class="flex items-start justify-between gap-3">
                    <h2 class="text-sm font-medium">Instalar Alquiler Fácil</h2>
                    <button
                        type="button"
                        class="text-muted-foreground hover:text-foreground -m-1 cursor-pointer p-1"
                        aria-label="Cerrar"
                        @click="ayuda = false"
                    >
                        <X class="size-4" />
                    </button>
                </div>

                <ol
                    v-if="esIos"
                    class="text-muted-foreground mt-4 space-y-2 text-sm"
                >
                    <li class="flex gap-2">
                        <Share class="mt-0.5 size-4 shrink-0" />
                        <span>
                            Tocá
                            <strong class="text-foreground">Compartir</strong>
                            en la barra de Safari.
                        </span>
                    </li>
                    <li>
                        Elegí
                        <strong class="text-foreground">Agregar a inicio</strong
                        >.
                    </li>
                </ol>
                <ol v-else class="text-muted-foreground mt-4 space-y-2 text-sm">
                    <li>Abrí el menú del navegador (⋮).</li>
                    <li>
                        Elegí
                        <strong class="text-foreground">
                            Instalar aplicación</strong
                        >
                        o
                        <strong class="text-foreground">
                            Agregar a la pantalla de inicio</strong
                        >.
                    </li>
                </ol>
            </div>
        </div>
    </Teleport>
</template>

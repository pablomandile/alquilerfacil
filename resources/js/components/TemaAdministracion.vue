<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import {
    ChevronDown,
    Download,
    Eye,
    Paperclip,
    RotateCcw,
    Trash2,
} from '@lucide/vue';
import { ref } from 'vue';
import EstadoBadge from '@/components/EstadoBadge.vue';
import InputError from '@/components/InputError.vue';
import VisorArchivo, {
    type ArchivoVisible,
} from '@/components/VisorArchivo.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { tamano } from '@/lib/formato';
import rutasAdjuntos from '@/routes/administracion/adjuntos';
import rutasEntradas from '@/routes/administracion/entradas';
import rutasTemas from '@/routes/administracion/temas';

type Adjunto = { id: number; nombre: string; tamano: number; mime: string };
type Entrada = {
    id: number;
    fecha: string;
    fecha_iso: string;
    detalle: string;
    registrado_por: string | null;
    adjuntos: Adjunto[];
};

const props = defineProps<{
    tema: {
        id: number;
        titulo: string;
        categoria: string;
        categoria_label: string;
        estado: string;
        estado_label: string;
        creado: string | null;
        entradas: Entrada[];
    };
    puedeGestionar: boolean;
}>();

const abierto = ref(props.tema.estado === 'abierto');

const hoy = new Date().toISOString().slice(0, 10);
const formEntrada = useForm<{
    fecha: string;
    detalle: string;
    archivos: File[];
}>({
    fecha: hoy,
    detalle: '',
    archivos: [],
});

const archivosInput = ref<HTMLInputElement | null>(null);

function elegirArchivos(evento: Event) {
    formEntrada.archivos = Array.from(
        (evento.target as HTMLInputElement).files ?? [],
    );
}

function agregarEntrada() {
    formEntrada.post(rutasEntradas.store(props.tema.id).url, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            formEntrada.reset();
            formEntrada.fecha = hoy;
            if (archivosInput.value) archivosInput.value.value = '';
        },
    });
}

function cambiarEstado(estado: 'abierto' | 'resuelto') {
    router.patch(
        rutasTemas.update(props.tema.id).url,
        { estado },
        { preserveScroll: true },
    );
}

function borrarTema() {
    if (
        !window.confirm(
            'Se borra el tema con todas sus entradas y archivos adjuntos. ¿Seguro?',
        )
    ) {
        return;
    }
    router.delete(rutasTemas.destroy(props.tema.id).url, {
        preserveScroll: true,
    });
}

function borrarEntrada(id: number) {
    router.delete(rutasEntradas.destroy(id).url, { preserveScroll: true });
}

function borrarAdjunto(id: number) {
    router.delete(rutasAdjuntos.destroy(id).url, { preserveScroll: true });
}

const visor = ref<ArchivoVisible | null>(null);

function verAdjunto(a: Adjunto) {
    visor.value = {
        nombre: a.nombre,
        mime: a.mime,
        verUrl: rutasAdjuntos.show(a.id).url,
        descargarUrl: rutasAdjuntos.show(a.id, { query: { descarga: 1 } }).url,
    };
}
</script>

<template>
    <div class="overflow-hidden">
        <!-- Cabecera: siempre visible, clic para desplegar -->
        <button
            type="button"
            class="hover:bg-accent/40 flex w-full cursor-pointer items-center gap-3 px-4 py-3 text-left"
            @click="abierto = !abierto"
        >
            <ChevronDown
                class="text-muted-foreground size-4 shrink-0 transition-transform"
                :class="abierto ? '' : '-rotate-90'"
            />
            <div class="min-w-0 flex-1">
                <p class="truncate font-medium">{{ tema.titulo }}</p>
                <p class="text-muted-foreground text-xs">
                    {{ tema.categoria_label }} ·
                    {{ tema.entradas.length }}
                    {{ tema.entradas.length === 1 ? 'entrada' : 'entradas' }}
                    <span v-if="tema.creado"> · desde {{ tema.creado }}</span>
                </p>
            </div>
            <EstadoBadge :estado="tema.estado" :label="tema.estado_label" />
        </button>

        <div v-if="abierto" class="space-y-4 px-4 pt-1 pb-4">
            <!-- Línea de tiempo de entradas -->
            <ol class="border-border/60 space-y-3 border-l pl-4">
                <li v-for="e in tema.entradas" :key="e.id" class="relative">
                    <span
                        class="bg-border absolute top-1.5 -left-[21px] size-2 rounded-full"
                    />
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-muted-foreground text-xs font-medium">
                            {{ e.fecha }}
                            <span v-if="e.registrado_por">
                                · {{ e.registrado_por }}</span
                            >
                        </p>
                        <Button
                            v-if="puedeGestionar && tema.entradas.length > 1"
                            size="icon"
                            variant="ghost"
                            class="size-6 shrink-0"
                            @click="borrarEntrada(e.id)"
                        >
                            <Trash2 class="size-3.5" />
                            <span class="sr-only">Eliminar entrada</span>
                        </Button>
                    </div>
                    <p class="mt-0.5 text-sm whitespace-pre-line">
                        {{ e.detalle }}
                    </p>
                    <ul v-if="e.adjuntos.length" class="mt-2 space-y-1">
                        <li
                            v-for="a in e.adjuntos"
                            :key="a.id"
                            class="flex items-center gap-2 text-xs"
                        >
                            <Paperclip
                                class="text-muted-foreground size-3.5 shrink-0"
                            />
                            <button
                                type="button"
                                class="cursor-pointer truncate hover:underline"
                                @click="verAdjunto(a)"
                            >
                                {{ a.nombre }}
                            </button>
                            <span class="text-muted-foreground shrink-0">
                                {{ tamano(a.tamano) }}
                            </span>
                            <button
                                type="button"
                                class="text-muted-foreground hover:text-foreground shrink-0 cursor-pointer"
                                @click="verAdjunto(a)"
                            >
                                <Eye class="size-3.5" />
                                <span class="sr-only">Ver</span>
                            </button>
                            <a
                                :href="
                                    rutasAdjuntos.show(a.id, {
                                        query: { descarga: 1 },
                                    }).url
                                "
                                class="text-muted-foreground hover:text-foreground shrink-0"
                            >
                                <Download class="size-3.5" />
                                <span class="sr-only">Descargar</span>
                            </a>
                            <button
                                v-if="puedeGestionar"
                                type="button"
                                class="text-muted-foreground hover:text-foreground shrink-0 cursor-pointer"
                                @click="borrarAdjunto(a.id)"
                            >
                                <Trash2 class="size-3.5" />
                                <span class="sr-only">Eliminar adjunto</span>
                            </button>
                        </li>
                    </ul>
                </li>
            </ol>

            <!-- Alta de entrada -->
            <form
                v-if="puedeGestionar"
                class="border-border/60 space-y-3 rounded-lg border border-dashed p-3"
                @submit.prevent="agregarEntrada"
            >
                <div class="grid gap-3 sm:grid-cols-[10rem_1fr]">
                    <div class="grid gap-1.5">
                        <Label :for="`entrada-fecha-${tema.id}`">Fecha</Label>
                        <Input
                            :id="`entrada-fecha-${tema.id}`"
                            v-model="formEntrada.fecha"
                            type="date"
                        />
                        <InputError :message="formEntrada.errors.fecha" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label :for="`entrada-detalle-${tema.id}`">
                            Qué pasó
                        </Label>
                        <textarea
                            :id="`entrada-detalle-${tema.id}`"
                            v-model="formEntrada.detalle"
                            rows="2"
                            class="border-input bg-background rounded-md border px-3 py-2 text-sm"
                            placeholder="Ej: mandé la nota por mail, sin respuesta todavía"
                        />
                        <InputError :message="formEntrada.errors.detalle" />
                    </div>
                </div>
                <div class="grid gap-1.5">
                    <Label :for="`entrada-archivos-${tema.id}`">
                        Adjuntos (opcional)
                    </Label>
                    <input
                        :id="`entrada-archivos-${tema.id}`"
                        ref="archivosInput"
                        type="file"
                        multiple
                        accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx"
                        class="file:bg-secondary text-sm file:mr-3 file:rounded-md file:border-0 file:px-3 file:py-1.5 file:text-sm file:font-medium"
                        @change="elegirArchivos"
                    />
                    <p class="text-muted-foreground text-xs">
                        PDF, imágenes o Word. Hasta 10 MB cada uno.
                    </p>
                    <InputError :message="formEntrada.errors.archivos" />
                </div>
                <Button
                    type="submit"
                    size="sm"
                    :disabled="formEntrada.processing || !formEntrada.detalle"
                >
                    Agregar entrada
                </Button>
            </form>

            <!-- Acciones del tema -->
            <div v-if="puedeGestionar" class="flex flex-wrap gap-2">
                <Button
                    v-if="tema.estado === 'abierto'"
                    size="sm"
                    variant="outline"
                    @click="cambiarEstado('resuelto')"
                >
                    Marcar como resuelto
                </Button>
                <Button
                    v-else
                    size="sm"
                    variant="outline"
                    @click="cambiarEstado('abierto')"
                >
                    <RotateCcw class="size-4" />
                    Reabrir
                </Button>
                <Button
                    size="sm"
                    variant="ghost"
                    class="text-muted-foreground"
                    @click="borrarTema"
                >
                    <Trash2 class="size-4" />
                    Borrar tema
                </Button>
            </div>
        </div>

        <VisorArchivo v-model:archivo="visor" />
    </div>
</template>

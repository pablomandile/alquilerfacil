<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import TemaAdministracion from '@/components/TemaAdministracion.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
type Tema = {
    id: number;
    titulo: string;
    categoria: string;
    categoria_label: string;
    estado: string;
    estado_label: string;
    creado: string | null;
    entradas: Entrada[];
};

const props = defineProps<{
    propiedadId: number;
    temas: Tema[];
    categorias: Array<{ value: string; label: string }>;
    puedeGestionar: boolean;
}>();

const dialogo = ref(false);

const hoy = new Date().toISOString().slice(0, 10);
const form = useForm<{
    titulo: string;
    categoria: string;
    fecha: string;
    detalle: string;
    archivos: File[];
}>({
    titulo: '',
    categoria: props.categorias[0]?.value ?? 'otro',
    fecha: hoy,
    detalle: '',
    archivos: [],
});

const archivosInput = ref<HTMLInputElement | null>(null);

function elegirArchivos(evento: Event) {
    form.archivos = Array.from((evento.target as HTMLInputElement).files ?? []);
}

function abrir() {
    form.reset();
    form.categoria = props.categorias[0]?.value ?? 'otro';
    form.fecha = hoy;
    if (archivosInput.value) archivosInput.value.value = '';
    dialogo.value = true;
}

function crearTema() {
    form.post(rutasTemas.store(props.propiedadId).url, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            dialogo.value = false;
            form.reset();
        },
    });
}
</script>

<template>
    <section class="space-y-3">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-sm font-medium">
                Seguimiento con la administración
            </h2>
            <Button
                v-if="puedeGestionar"
                size="sm"
                variant="outline"
                @click="abrir"
            >
                <Plus class="size-4" />
                Nuevo tema
            </Button>
        </div>

        <div
            class="border-sidebar-border/70 dark:border-sidebar-border tarjeta divide-y overflow-hidden rounded-xl border"
        >
            <p
                v-if="!temas.length"
                class="text-muted-foreground px-4 py-3 text-sm"
            >
                Todavía no hay temas registrados. Usá «Nuevo tema» para anotar
                un reclamo, un problema o una consulta con la administración y
                hacerle seguimiento.
            </p>

            <TemaAdministracion
                v-for="t in temas"
                :key="t.id"
                :tema="t"
                :puede-gestionar="puedeGestionar"
            />
        </div>
    </section>

    <Dialog v-model:open="dialogo">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Nuevo tema con la administración</DialogTitle>
                <DialogDescription>
                    Anotá el asunto y la primera entrada del seguimiento.
                    Después vas sumando entradas con lo que va pasando.
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="crearTema">
                <div class="grid gap-1.5">
                    <Label for="tema-titulo">Asunto</Label>
                    <Input
                        id="tema-titulo"
                        v-model="form.titulo"
                        placeholder="Ej: filtración en el baño desde el 3° piso"
                    />
                    <InputError :message="form.errors.titulo" />
                </div>

                <div class="grid gap-3 sm:grid-cols-[1fr_10rem]">
                    <div class="grid gap-1.5">
                        <Label for="tema-categoria">Categoría</Label>
                        <select
                            id="tema-categoria"
                            v-model="form.categoria"
                            class="border-input bg-background h-9 rounded-md border px-3 text-sm"
                        >
                            <option
                                v-for="c in categorias"
                                :key="c.value"
                                :value="c.value"
                            >
                                {{ c.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.categoria" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="tema-fecha">Fecha</Label>
                        <Input
                            id="tema-fecha"
                            v-model="form.fecha"
                            type="date"
                        />
                        <InputError :message="form.errors.fecha" />
                    </div>
                </div>

                <div class="grid gap-1.5">
                    <Label for="tema-detalle">Primera entrada</Label>
                    <textarea
                        id="tema-detalle"
                        v-model="form.detalle"
                        rows="3"
                        class="border-input bg-background rounded-md border px-3 py-2 text-sm"
                        placeholder="Qué pasó, con quién lo hablaste, qué te dijeron…"
                    />
                    <InputError :message="form.errors.detalle" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="tema-archivos">Adjuntos (opcional)</Label>
                    <input
                        id="tema-archivos"
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
                    <InputError :message="form.errors.archivos" />
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="dialogo = false"
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="submit"
                        :disabled="
                            form.processing || !form.titulo || !form.detalle
                        "
                    >
                        Registrar tema
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>

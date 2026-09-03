<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import rutasPropiedades from '@/routes/propiedades';

type Opcion = { value: string; label: string };
type FilaPropietario = { owner_id: number | null; porcentaje: number | null };

const props = defineProps<{
    tipos: Opcion[];
    estados: Opcion[];
    propietariosDisponibles: Array<{ id: number; nombre: string }>;
    propiedad?: {
        id: number;
        alias: string;
        tipo: string;
        estado: string;
        calle: string | null;
        numero: string | null;
        piso: string | null;
        depto: string | null;
        localidad: string | null;
        provincia: string | null;
        codigo_postal: string | null;
        ambientes: number | null;
        superficie_m2: string | null;
        partida_inmobiliaria: string | null;
        notas: string | null;
        propietarios: FilaPropietario[];
    };
}>();

const editando = computed(() => props.propiedad !== undefined);

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Propiedades', href: rutasPropiedades.index() }],
    },
});

const form = useForm<{
    alias: string;
    tipo: string;
    estado: string;
    calle: string;
    numero: string;
    piso: string;
    depto: string;
    localidad: string;
    provincia: string;
    codigo_postal: string;
    ambientes: number | null;
    superficie_m2: number | null;
    partida_inmobiliaria: string;
    notas: string;
    propietarios: FilaPropietario[];
}>({
    alias: props.propiedad?.alias ?? '',
    tipo: props.propiedad?.tipo ?? 'departamento',
    estado: props.propiedad?.estado ?? 'disponible',
    calle: props.propiedad?.calle ?? '',
    numero: props.propiedad?.numero ?? '',
    piso: props.propiedad?.piso ?? '',
    depto: props.propiedad?.depto ?? '',
    localidad: props.propiedad?.localidad ?? 'Ciudad Autónoma de Buenos Aires',
    provincia: props.propiedad?.provincia ?? 'CABA',
    codigo_postal: props.propiedad?.codigo_postal ?? '',
    ambientes: props.propiedad?.ambientes ?? null,
    superficie_m2: props.propiedad?.superficie_m2
        ? Number(props.propiedad.superficie_m2)
        : null,
    partida_inmobiliaria: props.propiedad?.partida_inmobiliaria ?? '',
    notas: props.propiedad?.notas ?? '',
    propietarios: props.propiedad?.propietarios ?? [],
});

/* El total se muestra en vivo: es el error más fácil de cometer y el más caro,
   porque rompe el reparto del alquiler y de los gastos. */
const totalPorcentaje = computed(() =>
    form.propietarios.reduce((suma, p) => suma + Number(p.porcentaje || 0), 0),
);

const totalValido = computed(
    () =>
        form.propietarios.length === 0 ||
        Math.abs(totalPorcentaje.value - 100) < 0.005,
);

function agregarPropietario() {
    form.propietarios.push({ owner_id: null, porcentaje: null });
}

function quitarPropietario(indice: number) {
    form.propietarios.splice(indice, 1);
}

/** Reparte 100 % en partes iguales, ajustando el resto en la primera fila. */
function repartirEnPartesIguales() {
    const n = form.propietarios.length;
    if (n === 0) return;

    const parte = Math.floor((100 / n) * 100) / 100;
    form.propietarios.forEach((p, i) => {
        p.porcentaje =
            i === 0 ? Number((100 - parte * (n - 1)).toFixed(2)) : parte;
    });
}

function enviar() {
    if (editando.value && props.propiedad) {
        form.put(rutasPropiedades.update(props.propiedad.id).url);
        return;
    }

    form.post(rutasPropiedades.store().url);
}
</script>

<template>
    <Head :title="editando ? 'Editar propiedad' : 'Nueva propiedad'" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <PageHeader
            :titulo="editando ? 'Editar propiedad' : 'Nueva propiedad'"
        />

        <form class="grid max-w-3xl gap-6" @submit.prevent="enviar">
            <!-- Identificación -->
            <section
                class="border-sidebar-border/70 dark:border-sidebar-border tarjeta grid gap-4 rounded-xl border p-4"
            >
                <div class="grid gap-2">
                    <Label for="alias">Nombre</Label>
                    <Input
                        id="alias"
                        v-model="form.alias"
                        placeholder="Cabildo 2300 4°B"
                        required
                    />
                    <p class="text-muted-foreground text-xs">
                        Con esto vas a identificar la propiedad en toda la app.
                    </p>
                    <InputError :message="form.errors.alias" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="tipo">Tipo</Label>
                        <select
                            id="tipo"
                            v-model="form.tipo"
                            class="border-input bg-background h-9 rounded-md border px-3 text-sm"
                        >
                            <option
                                v-for="t in tipos"
                                :key="t.value"
                                :value="t.value"
                            >
                                {{ t.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.tipo" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="estado">Estado</Label>
                        <select
                            id="estado"
                            v-model="form.estado"
                            class="border-input bg-background h-9 rounded-md border px-3 text-sm"
                        >
                            <option
                                v-for="e in estados"
                                :key="e.value"
                                :value="e.value"
                            >
                                {{ e.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.estado" />
                    </div>
                </div>
            </section>

            <!-- Dirección -->
            <section
                class="border-sidebar-border/70 dark:border-sidebar-border tarjeta grid gap-4 rounded-xl border p-4"
            >
                <h2 class="text-sm font-medium">Dirección</h2>

                <div class="grid gap-4 sm:grid-cols-[1fr_auto_auto_auto]">
                    <div class="grid gap-2">
                        <Label for="calle">Calle</Label>
                        <Input id="calle" v-model="form.calle" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="numero">Número</Label>
                        <Input
                            id="numero"
                            v-model="form.numero"
                            class="sm:w-24"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="piso">Piso</Label>
                        <Input id="piso" v-model="form.piso" class="sm:w-20" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="depto">Depto</Label>
                        <Input
                            id="depto"
                            v-model="form.depto"
                            class="sm:w-20"
                        />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="localidad">Localidad</Label>
                        <Input id="localidad" v-model="form.localidad" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="provincia">Provincia</Label>
                        <Input id="provincia" v-model="form.provincia" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="codigo_postal">Código postal</Label>
                        <Input
                            id="codigo_postal"
                            v-model="form.codigo_postal"
                        />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="ambientes">Ambientes</Label>
                        <Input
                            id="ambientes"
                            v-model.number="form.ambientes"
                            type="number"
                            min="0"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="superficie_m2">Superficie (m²)</Label>
                        <Input
                            id="superficie_m2"
                            v-model.number="form.superficie_m2"
                            type="number"
                            step="0.01"
                            min="0"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="partida_inmobiliaria">Partida</Label>
                        <Input
                            id="partida_inmobiliaria"
                            v-model="form.partida_inmobiliaria"
                        />
                    </div>
                </div>
            </section>

            <!-- Propietarios y porcentajes -->
            <section
                class="border-sidebar-border/70 dark:border-sidebar-border tarjeta grid gap-4 rounded-xl border p-4"
            >
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <h2 class="text-sm font-medium">Propietarios</h2>
                        <p class="text-muted-foreground text-xs">
                            Con estos porcentajes se reparten el alquiler y los
                            gastos extraordinarios.
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <Button
                            v-if="form.propietarios.length > 1"
                            type="button"
                            size="sm"
                            variant="ghost"
                            @click="repartirEnPartesIguales"
                        >
                            Partes iguales
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="agregarPropietario"
                        >
                            <Plus class="size-4" />
                            Agregar
                        </Button>
                    </div>
                </div>

                <div
                    v-for="(fila, i) in form.propietarios"
                    :key="i"
                    class="flex items-end gap-2"
                >
                    <div class="grid flex-1 gap-2">
                        <Label :for="`owner-${i}`" class="sr-only">
                            Propietario
                        </Label>
                        <select
                            :id="`owner-${i}`"
                            v-model="fila.owner_id"
                            class="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                        >
                            <option :value="null" disabled>
                                Elegí un propietario
                            </option>
                            <option
                                v-for="o in propietariosDisponibles"
                                :key="o.id"
                                :value="o.id"
                            >
                                {{ o.nombre }}
                            </option>
                        </select>
                    </div>

                    <div class="grid w-28 gap-2">
                        <Label :for="`pct-${i}`" class="sr-only">
                            Porcentaje
                        </Label>
                        <div class="relative">
                            <Input
                                :id="`pct-${i}`"
                                v-model.number="fila.porcentaje"
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                class="pr-7 tabular-nums"
                            />
                            <span
                                class="text-muted-foreground absolute top-1/2 right-3 -translate-y-1/2 text-sm"
                            >
                                %
                            </span>
                        </div>
                    </div>

                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        @click="quitarPropietario(i)"
                    >
                        <Trash2 class="size-4" />
                        <span class="sr-only">Quitar</span>
                    </Button>
                </div>

                <div
                    v-if="form.propietarios.length"
                    class="flex items-center justify-between border-t pt-3 text-sm"
                >
                    <span class="text-muted-foreground">Total</span>
                    <span
                        class="font-medium tabular-nums"
                        :class="
                            totalValido
                                ? 'text-emerald-600 dark:text-emerald-400'
                                : 'text-rose-600 dark:text-rose-400'
                        "
                    >
                        {{ totalPorcentaje.toFixed(2) }} %
                        <span v-if="!totalValido" class="font-normal">
                            (tiene que sumar 100)
                        </span>
                    </span>
                </div>

                <InputError :message="form.errors.propietarios" />
            </section>

            <div class="grid gap-2">
                <Label for="notas">Notas</Label>
                <textarea
                    id="notas"
                    v-model="form.notas"
                    rows="3"
                    class="border-input bg-background rounded-md border px-3 py-2 text-sm"
                />
            </div>

            <div class="flex gap-2">
                <Button
                    type="submit"
                    :disabled="form.processing || !totalValido"
                >
                    {{ editando ? 'Guardar cambios' : 'Crear propiedad' }}
                </Button>
                <Button as-child variant="outline" type="button">
                    <Link :href="rutasPropiedades.index()">Cancelar</Link>
                </Button>
            </div>
        </form>
    </div>
</template>

<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import rutasGastos from '@/routes/gastos';

type Opcion = { value: string; label: string };

const props = defineProps<{
    propiedades: Array<{ id: number; alias: string }>;
    contratos: Array<{ id: number; property_id: number; label: string }>;
    tipos: Opcion[];
    categorias: Opcion[];
    aCargoDe: Opcion[];
    gasto?: {
        id: number;
        property_id: number;
        contract_id: number | null;
        tipo: string;
        categoria: string;
        descripcion: string | null;
        periodo: string;
        monto: string;
        vencimiento: string | null;
        a_cargo_de: string;
        pagado: boolean;
        fecha_pago: string | null;
        notas: string | null;
    };
}>();

const editando = computed(() => props.gasto !== undefined);

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Gastos', href: rutasGastos.index() }],
    },
});

const form = useForm({
    property_id: props.gasto?.property_id ?? null,
    contract_id: props.gasto?.contract_id ?? null,
    tipo: props.gasto?.tipo ?? 'servicio',
    categoria: props.gasto?.categoria ?? 'luz',
    descripcion: props.gasto?.descripcion ?? '',
    periodo:
        props.gasto?.periodo ?? new Date().toISOString().slice(0, 8) + '01',
    monto: props.gasto?.monto ?? '',
    vencimiento: props.gasto?.vencimiento ?? '',
    a_cargo_de: props.gasto?.a_cargo_de ?? 'inquilino',
    pagado: props.gasto?.pagado ?? false,
    fecha_pago: props.gasto?.fecha_pago ?? '',
    notas: props.gasto?.notas ?? '',
});

/* Los gastos extraordinarios e impuestos normalmente los soportan los dueños;
   los servicios, el inquilino. Se sugiere al cambiar el tipo, sin imponerlo. */
watch(
    () => form.tipo,
    (tipo) => {
        if (editando.value) return;
        form.a_cargo_de = ['extraordinario', 'impuesto'].includes(tipo)
            ? 'propietarios'
            : 'inquilino';
    },
);

const contratosDeLaPropiedad = computed(() =>
    props.contratos.filter((c) => c.property_id === form.property_id),
);

function enviar() {
    if (editando.value && props.gasto) {
        form.put(rutasGastos.update(props.gasto.id).url);
        return;
    }

    form.post(rutasGastos.store().url);
}
</script>

<template>
    <Head :title="editando ? 'Editar gasto' : 'Nuevo gasto'" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <PageHeader :titulo="editando ? 'Editar gasto' : 'Nuevo gasto'" />

        <form class="grid max-w-2xl gap-6" @submit.prevent="enviar">
            <section
                class="border-sidebar-border/70 dark:border-sidebar-border tarjeta grid gap-4 rounded-xl border p-4 sm:grid-cols-2"
            >
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="property_id">Propiedad</Label>
                    <select
                        id="property_id"
                        v-model="form.property_id"
                        class="border-input bg-background h-9 rounded-md border px-3 text-sm"
                        required
                    >
                        <option :value="null" disabled>Elegí una</option>
                        <option
                            v-for="p in propiedades"
                            :key="p.id"
                            :value="p.id"
                        >
                            {{ p.alias }}
                        </option>
                    </select>
                    <InputError :message="form.errors.property_id" />
                </div>

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
                </div>

                <div class="grid gap-2">
                    <Label for="categoria">Categoría</Label>
                    <select
                        id="categoria"
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
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <Label for="descripcion">Descripción</Label>
                    <Input
                        id="descripcion"
                        v-model="form.descripcion"
                        placeholder="Edesur, cambio del termotanque…"
                    />
                </div>
            </section>

            <section
                class="border-sidebar-border/70 dark:border-sidebar-border tarjeta grid gap-4 rounded-xl border p-4 sm:grid-cols-2"
            >
                <div class="grid gap-2">
                    <Label for="monto">Monto</Label>
                    <Input
                        id="monto"
                        v-model="form.monto"
                        type="number"
                        step="0.01"
                        min="0"
                        class="tabular-nums"
                        required
                    />
                    <InputError :message="form.errors.monto" />
                </div>

                <div class="grid gap-2">
                    <Label for="periodo">Período</Label>
                    <Input
                        id="periodo"
                        v-model="form.periodo"
                        type="date"
                        required
                    />
                    <p class="text-muted-foreground text-xs">
                        El mes al que corresponde el gasto.
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="vencimiento">Vencimiento</Label>
                    <Input
                        id="vencimiento"
                        v-model="form.vencimiento"
                        type="date"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="a_cargo_de">A cargo de</Label>
                    <select
                        id="a_cargo_de"
                        v-model="form.a_cargo_de"
                        class="border-input bg-background h-9 rounded-md border px-3 text-sm"
                    >
                        <option
                            v-for="a in aCargoDe"
                            :key="a.value"
                            :value="a.value"
                        >
                            {{ a.label }}
                        </option>
                    </select>
                    <p
                        v-if="form.a_cargo_de === 'propietarios'"
                        class="text-xs text-emerald-600 dark:text-emerald-400"
                    >
                        Se va a repartir entre los dueños según su porcentaje.
                    </p>
                </div>

                <div
                    v-if="contratosDeLaPropiedad.length"
                    class="grid gap-2 sm:col-span-2"
                >
                    <Label for="contract_id">Contrato (opcional)</Label>
                    <select
                        id="contract_id"
                        v-model="form.contract_id"
                        class="border-input bg-background h-9 rounded-md border px-3 text-sm"
                    >
                        <option :value="null">Sin asociar</option>
                        <option
                            v-for="c in contratosDeLaPropiedad"
                            :key="c.id"
                            :value="c.id"
                        >
                            {{ c.label }}
                        </option>
                    </select>
                </div>
            </section>

            <section
                class="border-sidebar-border/70 dark:border-sidebar-border tarjeta grid gap-4 rounded-xl border p-4"
            >
                <div class="flex items-center gap-2">
                    <Checkbox id="pagado" v-model="form.pagado" />
                    <Label for="pagado" class="cursor-pointer">
                        Ya está pagado
                    </Label>
                </div>

                <div v-if="form.pagado" class="grid max-w-xs gap-2">
                    <Label for="fecha_pago">Fecha de pago</Label>
                    <Input
                        id="fecha_pago"
                        v-model="form.fecha_pago"
                        type="date"
                    />
                </div>
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
                <Button type="submit" :disabled="form.processing">
                    {{ editando ? 'Guardar cambios' : 'Cargar gasto' }}
                </Button>
                <Button as-child variant="outline" type="button">
                    <Link :href="rutasGastos.index()">Cancelar</Link>
                </Button>
            </div>
        </form>
    </div>
</template>

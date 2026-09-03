<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import rutasContratos from '@/routes/contratos';

type Opcion = { value: string; label: string };

const props = defineProps<{
    propiedades: Array<{ id: number; alias: string }>;
    inquilinos: Array<{ id: number; nombre: string }>;
    indices: Opcion[];
    estados: Opcion[];
    opcionesRedondeo: Array<{ value: number; label: string }>;
    contrato?: {
        id: number;
        property_id: number;
        tenant_id: number;
        fecha_inicio: string;
        fecha_fin: string;
        monto_base: string;
        monto_actual: string;
        dia_vencimiento: number;
        deposito: string | null;
        indice: string;
        frecuencia_meses: number;
        proximo_ajuste: string | null;
        redondeo: number;
        estado: string;
        notas: string | null;
    };
}>();

const editando = computed(() => props.contrato !== undefined);

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Contratos', href: rutasContratos.index() }],
    },
});

const form = useForm({
    property_id: props.contrato?.property_id ?? null,
    tenant_id: props.contrato?.tenant_id ?? null,
    fecha_inicio: props.contrato?.fecha_inicio ?? '',
    fecha_fin: props.contrato?.fecha_fin ?? '',
    monto_base: props.contrato?.monto_base ?? '',
    monto_actual: props.contrato?.monto_actual ?? '',
    dia_vencimiento: props.contrato?.dia_vencimiento ?? 10,
    deposito: props.contrato?.deposito ?? '',
    indice: props.contrato?.indice ?? 'ipc',
    frecuencia_meses: props.contrato?.frecuencia_meses ?? 3,
    proximo_ajuste: props.contrato?.proximo_ajuste ?? '',
    redondeo: props.contrato?.redondeo ?? 0,
    estado: props.contrato?.estado ?? 'activo',
    notas: props.contrato?.notas ?? '',
});

/* Al crear, sugerir la primera fecha de ajuste a partir del inicio y la
   frecuencia: es lo que uno haría a mano y evita dejarla vacía por olvido. */
function sugerirProximoAjuste() {
    if (editando.value || !form.fecha_inicio) return;

    const inicio = new Date(`${form.fecha_inicio}T00:00:00`);
    inicio.setMonth(inicio.getMonth() + Number(form.frecuencia_meses));
    form.proximo_ajuste = inicio.toISOString().slice(0, 10);
}

function enviar() {
    if (editando.value && props.contrato) {
        form.put(rutasContratos.update(props.contrato.id).url);
        return;
    }

    form.post(rutasContratos.store().url);
}
</script>

<template>
    <Head :title="editando ? 'Editar contrato' : 'Nuevo contrato'" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <PageHeader :titulo="editando ? 'Editar contrato' : 'Nuevo contrato'" />

        <form class="grid max-w-3xl gap-6" @submit.prevent="enviar">
            <section
                class="border-sidebar-border/70 dark:border-sidebar-border tarjeta grid gap-4 rounded-xl border p-4 sm:grid-cols-2"
            >
                <div class="grid gap-2">
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
                    <Label for="tenant_id">Inquilino</Label>
                    <select
                        id="tenant_id"
                        v-model="form.tenant_id"
                        class="border-input bg-background h-9 rounded-md border px-3 text-sm"
                        required
                    >
                        <option :value="null" disabled>Elegí uno</option>
                        <option
                            v-for="i in inquilinos"
                            :key="i.id"
                            :value="i.id"
                        >
                            {{ i.nombre }}
                        </option>
                    </select>
                    <InputError :message="form.errors.tenant_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="fecha_inicio">Desde</Label>
                    <Input
                        id="fecha_inicio"
                        v-model="form.fecha_inicio"
                        type="date"
                        required
                        @change="sugerirProximoAjuste"
                    />
                    <InputError :message="form.errors.fecha_inicio" />
                </div>

                <div class="grid gap-2">
                    <Label for="fecha_fin">Hasta</Label>
                    <Input
                        id="fecha_fin"
                        v-model="form.fecha_fin"
                        type="date"
                        required
                    />
                    <InputError :message="form.errors.fecha_fin" />
                </div>
            </section>

            <!-- Plata -->
            <section
                class="border-sidebar-border/70 dark:border-sidebar-border tarjeta grid gap-4 rounded-xl border p-4 sm:grid-cols-2"
            >
                <h2 class="text-sm font-medium sm:col-span-2">Valores</h2>

                <div class="grid gap-2">
                    <Label for="monto_base">Alquiler inicial</Label>
                    <Input
                        id="monto_base"
                        v-model="form.monto_base"
                        type="number"
                        step="0.01"
                        min="0"
                        class="tabular-nums"
                        required
                    />
                    <InputError :message="form.errors.monto_base" />
                </div>

                <div v-if="editando" class="grid gap-2">
                    <Label for="monto_actual">Alquiler vigente</Label>
                    <Input
                        id="monto_actual"
                        v-model="form.monto_actual"
                        type="number"
                        step="0.01"
                        min="0"
                        class="tabular-nums"
                    />
                    <p class="text-muted-foreground text-xs">
                        Normalmente lo mueven los ajustes. Tocalo sólo para
                        corregir.
                    </p>
                    <InputError :message="form.errors.monto_actual" />
                </div>

                <div class="grid gap-2">
                    <Label for="deposito">Depósito</Label>
                    <Input
                        id="deposito"
                        v-model="form.deposito"
                        type="number"
                        step="0.01"
                        min="0"
                        class="tabular-nums"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="dia_vencimiento">Día de vencimiento</Label>
                    <Input
                        id="dia_vencimiento"
                        v-model.number="form.dia_vencimiento"
                        type="number"
                        min="1"
                        max="31"
                    />
                    <InputError :message="form.errors.dia_vencimiento" />
                </div>
            </section>

            <!-- Actualización -->
            <section
                class="border-sidebar-border/70 dark:border-sidebar-border tarjeta grid gap-4 rounded-xl border p-4 sm:grid-cols-2"
            >
                <div class="sm:col-span-2">
                    <h2 class="text-sm font-medium">Actualización</h2>
                    <p class="text-muted-foreground text-xs">
                        La app calcula el aumento con este índice y te lo ofrece
                        cuando llega la fecha. Nunca lo aplica sola.
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="indice">Índice</Label>
                    <select
                        id="indice"
                        v-model="form.indice"
                        class="border-input bg-background h-9 rounded-md border px-3 text-sm"
                    >
                        <option
                            v-for="i in indices"
                            :key="i.value"
                            :value="i.value"
                        >
                            {{ i.label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.indice" />
                </div>

                <div class="grid gap-2">
                    <Label for="frecuencia_meses">Cada cuántos meses</Label>
                    <Input
                        id="frecuencia_meses"
                        v-model.number="form.frecuencia_meses"
                        type="number"
                        min="1"
                        max="24"
                        @change="sugerirProximoAjuste"
                    />
                    <InputError :message="form.errors.frecuencia_meses" />
                </div>

                <div class="grid gap-2">
                    <Label for="proximo_ajuste">Próximo ajuste</Label>
                    <Input
                        id="proximo_ajuste"
                        v-model="form.proximo_ajuste"
                        type="date"
                    />
                    <InputError :message="form.errors.proximo_ajuste" />
                </div>

                <div class="grid gap-2">
                    <Label for="redondeo">Redondeo del monto</Label>
                    <select
                        id="redondeo"
                        v-model.number="form.redondeo"
                        class="border-input bg-background h-9 rounded-md border px-3 text-sm"
                    >
                        <option
                            v-for="r in opcionesRedondeo"
                            :key="r.value"
                            :value="r.value"
                        >
                            {{ r.label }}
                        </option>
                    </select>
                    <p class="text-muted-foreground text-xs">
                        Para que el alquiler quede en un número redondo.
                    </p>
                </div>
            </section>

            <div class="grid gap-4 sm:grid-cols-2">
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
                </div>
            </div>

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
                    {{ editando ? 'Guardar cambios' : 'Crear contrato' }}
                </Button>
                <Button as-child variant="outline" type="button">
                    <Link :href="rutasContratos.index()">Cancelar</Link>
                </Button>
            </div>
        </form>
    </div>
</template>

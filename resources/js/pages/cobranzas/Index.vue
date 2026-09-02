<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Plus, Trash2, Wallet } from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import EstadoBadge from '@/components/EstadoBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { pesos, pesosRedondos } from '@/lib/formato';
import rutasCobranzas from '@/routes/cobranzas';
import rutasContratos from '@/routes/contratos';
import rutasPagos from '@/routes/pagos';

type Pago = {
    id: number;
    fecha: string;
    monto: string;
    medio: string;
    referencia: string | null;
};

type Cargo = {
    id: number;
    propiedad: string;
    contrato_id: number;
    inquilino: string;
    monto: string;
    pagado: string;
    saldo: string;
    vencimiento: string;
    estado: string;
    estado_label: string;
    pagos: Pago[];
};

const props = defineProps<{
    cargos: Cargo[];
    periodo: string;
    periodoLabel: string;
    totales: { facturado: string; cobrado: string; pendiente: string };
    mediosPago: Array<{ value: string; label: string }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Cobranzas', href: rutasCobranzas.index() }],
    },
});

const page = usePage();
const esAdmin = computed(() => page.props.auth?.esAdmin ?? false);

const periodoElegido = ref(props.periodo);

function cambiarPeriodo() {
    router.get(
        rutasCobranzas.index().url,
        { periodo: periodoElegido.value },
        { preserveState: true, preserveScroll: true },
    );
}

function generarCargos() {
    router.post(
        rutasCobranzas.generar().url,
        { periodo: periodoElegido.value },
        { preserveScroll: true },
    );
}

/* Registrar pago: se precarga el saldo, que es lo que se cobra casi siempre,
   pero se puede editar para registrar un pago parcial. */
const cobrando = ref<Cargo | null>(null);
const formPago = useForm({
    fecha: new Date().toISOString().slice(0, 10),
    monto: '',
    medio: 'transferencia',
    referencia: '',
});

function abrirCobro(cargo: Cargo) {
    cobrando.value = cargo;
    formPago.monto = cargo.saldo;
    formPago.fecha = new Date().toISOString().slice(0, 10);
}

function registrarPago() {
    if (!cobrando.value) return;

    formPago.post(rutasPagos.store(cobrando.value.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            cobrando.value = null;
            formPago.reset();
        },
    });
}

function borrarPago(id: number) {
    router.delete(rutasPagos.destroy(id).url, { preserveScroll: true });
}
</script>

<template>
    <Head title="Cobranzas" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <PageHeader titulo="Cobranzas" :descripcion="periodoLabel">
            <template #acciones>
                <!-- El input de mes muestra "septiembre de 2026": con w-40 se
                     corta en el celular, así que ahí ocupa toda la fila. -->
                <Input
                    v-model="periodoElegido"
                    type="month"
                    class="w-full sm:w-48"
                    @change="cambiarPeriodo"
                />
                <Button
                    v-if="esAdmin"
                    size="sm"
                    variant="outline"
                    @click="generarCargos"
                >
                    Emitir cargos
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-3">
            <StatCard
                etiqueta="Facturado"
                :valor="pesosRedondos(totales.facturado)"
            />
            <StatCard
                etiqueta="Cobrado"
                :valor="pesosRedondos(totales.cobrado)"
                acento="positivo"
            />
            <StatCard
                etiqueta="Pendiente"
                :valor="pesosRedondos(totales.pendiente)"
                :acento="Number(totales.pendiente) > 0 ? 'atencion' : 'normal'"
            />
        </div>

        <EmptyState
            v-if="!cargos.length"
            titulo="No hay cargos emitidos para este mes"
            descripcion="Los cargos se emiten solos el día 1. Si querés adelantarlos, usá «Emitir cargos»."
            :icono="Wallet"
        >
            <Button v-if="esAdmin" size="sm" @click="generarCargos">
                Emitir los de {{ periodoLabel }}
            </Button>
        </EmptyState>

        <div v-else class="grid gap-3">
            <article
                v-for="cargo in cargos"
                :key="cargo.id"
                class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
            >
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div class="min-w-0">
                        <Link
                            :href="rutasContratos.show(cargo.contrato_id)"
                            class="font-medium hover:underline"
                        >
                            {{ cargo.propiedad }}
                        </Link>
                        <p class="text-muted-foreground text-sm">
                            {{ cargo.inquilino }} · vence
                            {{ cargo.vencimiento }}
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="text-right">
                            <p class="font-semibold tabular-nums">
                                {{ pesos(cargo.monto) }}
                            </p>
                            <p
                                v-if="Number(cargo.saldo) > 0"
                                class="text-muted-foreground text-xs tabular-nums"
                            >
                                saldo {{ pesos(cargo.saldo) }}
                            </p>
                        </div>
                        <EstadoBadge
                            :estado="cargo.estado"
                            :label="cargo.estado_label"
                        />
                        <Button
                            v-if="esAdmin && Number(cargo.saldo) > 0"
                            size="sm"
                            @click="abrirCobro(cargo)"
                        >
                            <Plus class="size-4" />
                            Pago
                        </Button>
                    </div>
                </div>

                <!-- Pagos registrados -->
                <ul
                    v-if="cargo.pagos.length"
                    class="mt-3 divide-y border-t text-sm"
                >
                    <li
                        v-for="pago in cargo.pagos"
                        :key="pago.id"
                        class="flex items-center justify-between gap-3 py-2"
                    >
                        <div class="text-muted-foreground min-w-0 text-xs">
                            {{ pago.fecha }} · {{ pago.medio }}
                            <span v-if="pago.referencia">
                                · {{ pago.referencia }}
                            </span>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <span class="tabular-nums">
                                {{ pesos(pago.monto) }}
                            </span>
                            <Button
                                v-if="esAdmin"
                                size="icon"
                                variant="ghost"
                                class="size-7"
                                @click="borrarPago(pago.id)"
                            >
                                <Trash2 class="size-3.5" />
                                <span class="sr-only">Borrar pago</span>
                            </Button>
                        </div>
                    </li>
                </ul>
            </article>
        </div>
    </div>

    <Dialog
        :open="cobrando !== null"
        @update:open="(v) => !v && (cobrando = null)"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Registrar un pago</DialogTitle>
                <DialogDescription v-if="cobrando">
                    {{ cobrando.propiedad }} — {{ cobrando.inquilino }}. Saldo
                    pendiente: {{ pesos(cobrando.saldo) }}.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4">
                <div class="grid gap-2 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="fecha">Fecha</Label>
                        <Input
                            id="fecha"
                            v-model="formPago.fecha"
                            type="date"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="monto">Monto</Label>
                        <Input
                            id="monto"
                            v-model="formPago.monto"
                            type="number"
                            step="0.01"
                            min="0"
                            class="tabular-nums"
                        />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="medio">Medio</Label>
                    <select
                        id="medio"
                        v-model="formPago.medio"
                        class="border-input bg-background h-9 rounded-md border px-3 text-sm"
                    >
                        <option
                            v-for="m in mediosPago"
                            :key="m.value"
                            :value="m.value"
                        >
                            {{ m.label }}
                        </option>
                    </select>
                </div>

                <div class="grid gap-2">
                    <Label for="referencia">Referencia</Label>
                    <Input
                        id="referencia"
                        v-model="formPago.referencia"
                        placeholder="Nº de transferencia, recibo…"
                    />
                </div>

                <p class="text-muted-foreground text-xs">
                    Si el monto es menor al saldo, el cargo queda como pago
                    parcial.
                </p>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="cobrando = null">
                    Cancelar
                </Button>
                <Button
                    :disabled="formPago.processing"
                    @click="registrarPago"
                >
                    Registrar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

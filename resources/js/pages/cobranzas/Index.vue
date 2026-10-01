<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Check, Copy, MessageCircle, Plus, Trash2, Wallet } from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import EstadoBadge from '@/components/EstadoBadge.vue';
import InputError from '@/components/InputError.vue';
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
import rutasMensajeInquilino from '@/routes/mensaje-inquilino';
import rutasPagos from '@/routes/pagos';

type Pago = {
    id: number;
    fecha: string;
    monto: string;
    medio: string;
    referencia: string | null;
};

type ItemMes = {
    concepto: string;
    monto: string;
    vencimiento: string | null;
    adjuntos?: Array<{ nombre: string; url: string }>;
};

type Cargo = {
    id: number;
    propiedad: string;
    contrato_id: number;
    property_id: number;
    inquilino: string;
    telefono: string | null;
    monto: string;
    pagado: string;
    saldo: string;
    vencimiento: string;
    estado: string;
    estado_label: string;
    pagos: Pago[];
    mes: string;
    alquiler: ItemMes;
    gastos: ItemMes[];
    envio: { enviado: boolean; fecha: string | null };
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
const puedeGestionar = computed(() => page.props.auth?.puedeGestionar ?? false);

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

function borrarCargo(id: number) {
    router.delete(rutasCobranzas.destroy(id).url, { preserveScroll: true });
}

/* Cuadro y mensaje del mes para el inquilino */
function itemsDeCargo(cargo: Cargo): ItemMes[] {
    return [cargo.alquiler, ...cargo.gastos];
}

function totalDeCargo(cargo: Cargo): number {
    return itemsDeCargo(cargo).reduce((s, i) => s + Number(i.monto), 0);
}

function textoMensajeDeCargo(cargo: Cargo): string {
    const nombre = cargo.inquilino.split(' ')[0];
    const lineas = itemsDeCargo(cargo).map(
        (i) =>
            `• ${i.concepto} — ${pesos(i.monto)}` +
            (i.vencimiento ? ` (vence ${i.vencimiento})` : ''),
    );
    const adjuntos = itemsDeCargo(cargo).flatMap((i) => i.adjuntos ?? []);
    return [
        `Hola ${nombre}, te paso el alquiler y los gastos de este mes:`,
        '',
        ...lineas,
        '',
        `Total: ${pesos(totalDeCargo(cargo))}`,
        ...(adjuntos.length
            ? [
                  '',
                  'Facturas y comprobantes:',
                  ...adjuntos.map((a) => `• ${a.nombre}: ${a.url}`),
              ]
            : []),
        '',
        'Saludos!',
    ].join('\n');
}

function linkWhatsappDeCargo(cargo: Cargo): string {
    const texto = encodeURIComponent(textoMensajeDeCargo(cargo));
    const tel = (cargo.telefono ?? '').replace(/\D/g, '').replace(/^0/, '');
    if (!tel) return `https://wa.me/?text=${texto}`;
    return `https://wa.me/${tel.startsWith('54') ? tel : `54${tel}`}?text=${texto}`;
}

const copiadoId = ref<number | null>(null);

function copiarMensaje(cargo: Cargo) {
    navigator.clipboard.writeText(textoMensajeDeCargo(cargo));
    copiadoId.value = cargo.id;
    setTimeout(() => {
        copiadoId.value = null;
    }, 2000);
}

function marcarEnviado(cargo: Cargo) {
    router.patch(
        rutasMensajeInquilino.actualizar(cargo.property_id).url,
        { estado: 'enviado' },
        { preserveScroll: true },
    );
}

const cargoVolviendoPendiente = ref<Cargo | null>(null);
const formPendiente = useForm({ password: '' });

function abrirVolverAPendiente(cargo: Cargo) {
    formPendiente.reset();
    formPendiente.clearErrors();
    cargoVolviendoPendiente.value = cargo;
}

function confirmarPendiente() {
    if (!cargoVolviendoPendiente.value) return;
    formPendiente
        .transform((d) => ({ ...d, estado: 'pendiente' }))
        .patch(
            rutasMensajeInquilino.actualizar(
                cargoVolviendoPendiente.value.property_id,
            ).url,
            {
                preserveScroll: true,
                onSuccess: () => {
                    cargoVolviendoPendiente.value = null;
                    formPendiente.reset();
                },
            },
        );
}

function alTocarEstado(cargo: Cargo) {
    if (cargo.envio.enviado) abrirVolverAPendiente(cargo);
    else marcarEnviado(cargo);
}
</script>

<template>
    <Head title="Cobranzas" />

    <div class="tinte-esmeralda flex flex-1 flex-col gap-6 p-4">
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
                    v-if="puedeGestionar"
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
                tinte="indigo"
            />
            <StatCard
                etiqueta="Cobrado"
                :valor="pesosRedondos(totales.cobrado)"
                acento="positivo"
                tinte="esmeralda"
            />
            <StatCard
                etiqueta="Pendiente"
                :valor="pesosRedondos(totales.pendiente)"
                :acento="Number(totales.pendiente) > 0 ? 'atencion' : 'normal'"
                tinte="ambar"
            />
        </div>

        <EmptyState
            v-if="!cargos.length"
            titulo="No hay cargos emitidos para este mes"
            descripcion="Los cargos se emiten solos el día 1. Si querés adelantarlos, usá «Emitir cargos»."
            :icono="Wallet"
        >
            <Button v-if="puedeGestionar" size="sm" @click="generarCargos">
                Emitir los de {{ periodoLabel }}
            </Button>
        </EmptyState>

        <div v-else class="grid grid-cols-1 gap-3">
            <article
                v-for="cargo in cargos"
                :key="cargo.id"
                class="tarjeta min-w-0 rounded-xl border p-4"
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
                            v-if="puedeGestionar && Number(cargo.saldo) > 0"
                            size="sm"
                            @click="abrirCobro(cargo)"
                        >
                            <Plus class="size-4" />
                            Pago
                        </Button>
                        <!-- Sólo si no tiene pagos: uno con plata registrada no
                             se borra por acá, primero hay que borrar el pago. -->
                        <Button
                            v-if="puedeGestionar && !cargo.pagos.length"
                            size="icon"
                            variant="ghost"
                            class="size-8 shrink-0"
                            @click="borrarCargo(cargo.id)"
                        >
                            <Trash2 class="size-4" />
                            <span class="sr-only">Borrar cargo</span>
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
                                v-if="puedeGestionar"
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

                <!-- Cuadro alquiler + gastos del mes para el inquilino -->
                <div class="mt-3 overflow-hidden rounded-xl border">
                    <!-- En el celular el vencimiento va abajo del concepto:
                         tres columnas no entran. -->
                    <table class="w-full text-sm">
                        <tbody class="divide-y">
                            <tr
                                v-for="(item, i) in itemsDeCargo(cargo)"
                                :key="i"
                            >
                                <td class="px-3 py-2 sm:px-4">
                                    {{ item.concepto }}
                                    <span
                                        v-if="item.vencimiento"
                                        class="text-muted-foreground block text-xs sm:hidden"
                                    >
                                        vence {{ item.vencimiento }}
                                    </span>
                                </td>
                                <td
                                    class="text-muted-foreground hidden px-4 py-2 whitespace-nowrap sm:table-cell"
                                >
                                    <span v-if="item.vencimiento">
                                        vence {{ item.vencimiento }}
                                    </span>
                                </td>
                                <td
                                    class="px-3 py-2 text-right whitespace-nowrap tabular-nums sm:px-4"
                                >
                                    {{ pesos(item.monto) }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t">
                            <tr class="font-semibold">
                                <td class="px-3 py-2 sm:hidden">Total</td>
                                <td
                                    class="hidden px-4 py-2 sm:table-cell"
                                    colspan="2"
                                >
                                    Total
                                </td>
                                <td
                                    class="px-3 py-2 text-right whitespace-nowrap tabular-nums sm:px-4"
                                >
                                    {{ pesos(totalDeCargo(cargo)) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>

                    <div
                        class="bg-muted/40 flex flex-wrap items-center gap-2 border-t p-3"
                    >
                        <Button size="sm" @click="copiarMensaje(cargo)">
                            <Check
                                v-if="copiadoId === cargo.id"
                                class="size-4"
                            />
                            <Copy v-else class="size-4" />
                            {{
                                copiadoId === cargo.id
                                    ? 'Copiado'
                                    : 'Copiar mensaje'
                            }}
                        </Button>
                        <Button as-child size="sm" variant="outline">
                            <a
                                :href="linkWhatsappDeCargo(cargo)"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <MessageCircle class="size-4" />
                                WhatsApp
                            </a>
                        </Button>

                        <button
                            type="button"
                            class="ml-auto inline-flex cursor-pointer items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium first-letter:uppercase"
                            :class="
                                cargo.envio.enviado
                                    ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200 dark:bg-emerald-950 dark:text-emerald-300 dark:hover:bg-emerald-900'
                                    : 'bg-amber-100 text-amber-800 hover:bg-amber-200 dark:bg-amber-950 dark:text-amber-300 dark:hover:bg-amber-900'
                            "
                            :title="`Aviso de ${cargo.mes}`"
                            @click="alTocarEstado(cargo)"
                        >
                            <span
                                class="size-1.5 rounded-full"
                                :class="
                                    cargo.envio.enviado
                                        ? 'bg-emerald-500'
                                        : 'bg-amber-500'
                                "
                            />
                            {{ cargo.mes }} ·
                            {{
                                cargo.envio.enviado
                                    ? cargo.envio.fecha
                                        ? `Enviado ${cargo.envio.fecha}`
                                        : 'Enviado'
                                    : 'Pendiente'
                            }}
                        </button>
                    </div>

                    <details class="border-t px-4 py-3 text-sm">
                        <summary
                            class="text-muted-foreground cursor-pointer select-none"
                        >
                            Ver el mensaje
                        </summary>
                        <pre
                            class="mt-2 font-sans text-sm whitespace-pre-wrap"
                            >{{ textoMensajeDeCargo(cargo) }}</pre>
                    </details>
                </div>
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
                <Button :disabled="formPago.processing" @click="registrarPago">
                    Registrar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog
        :open="cargoVolviendoPendiente !== null"
        @update:open="(v) => !v && (cargoVolviendoPendiente = null)"
    >
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle>Volver el aviso a pendiente</DialogTitle>
                <DialogDescription>
                    Ingresá tu contraseña para confirmar. Es para no desmarcar
                    por error un aviso que ya mandaste.
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-3" @submit.prevent="confirmarPendiente">
                <div class="grid gap-1.5">
                    <Label for="pw-pendiente">Contraseña</Label>
                    <Input
                        id="pw-pendiente"
                        v-model="formPendiente.password"
                        type="password"
                        autocomplete="current-password"
                    />
                    <InputError :message="formPendiente.errors.password" />
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="cargoVolviendoPendiente = null"
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="submit"
                        :disabled="
                            formPendiente.processing || !formPendiente.password
                        "
                    >
                        Confirmar
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Clock, RefreshCw, TrendingUp } from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import EstadoBadge from '@/components/EstadoBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
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
import { pesos, porcentaje } from '@/lib/formato';
import rutasAjustes from '@/routes/ajustes';

type Ajuste = {
    id: number;
    contrato_id: number;
    propiedad: string;
    inquilino: string;
    vigencia: string;
    monto_anterior: string;
    monto_nuevo: string;
    diferencia: string;
    variacion: number;
    indice: string;
    ventana: string;
    estado: string;
    estado_label: string;
    notas: string | null;
};

const props = defineProps<{
    propuestos: Ajuste[];
    historial: Ajuste[];
    enEspera: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Ajustes', href: rutasAjustes.index() }],
    },
});

const page = usePage();
const esAdmin = computed(() => page.props.auth?.esAdmin ?? false);
const puedeGestionar = computed(() => page.props.auth?.puedeGestionar ?? false);

/* Aplicar: se puede editar el monto antes de confirmar, porque lo que se pacta
   con el inquilino no siempre es exactamente lo que da la cuenta. */
const aplicando = ref<Ajuste | null>(null);
const formAplicar = useForm({ monto: '' });

function abrirAplicar(ajuste: Ajuste) {
    aplicando.value = ajuste;
    formAplicar.monto = ajuste.monto_nuevo;
}

function confirmarAplicar() {
    if (!aplicando.value) return;

    formAplicar
        .transform((datos) => ({
            // Si no lo tocó, se manda vacío y queda el monto calculado.
            monto:
                datos.monto === aplicando.value?.monto_nuevo
                    ? null
                    : datos.monto,
        }))
        .post(rutasAjustes.aplicar(aplicando.value.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                aplicando.value = null;
                formAplicar.reset();
            },
        });
}

const rechazando = ref<Ajuste | null>(null);
const formRechazar = useForm({ motivo: '' });

function confirmarRechazar() {
    if (!rechazando.value) return;

    formRechazar.post(rutasAjustes.rechazar(rechazando.value.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            rechazando.value = null;
            formRechazar.reset();
        },
    });
}

const recalculando = ref(false);

function recalcular() {
    recalculando.value = true;
    router.post(
        rutasAjustes.recalcular().url,
        {},
        {
            preserveScroll: true,
            onFinish: () => (recalculando.value = false),
        },
    );
}
</script>

<template>
    <Head title="Ajustes" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <PageHeader
            titulo="Ajustes de alquiler"
            descripcion="La app calcula el aumento que corresponde por índice. Aplicarlo lo decidís vos."
        >
            <template #acciones>
                <Button
                    v-if="esAdmin"
                    variant="outline"
                    size="sm"
                    :disabled="recalculando"
                    @click="recalcular"
                >
                    <RefreshCw
                        class="size-4"
                        :class="recalculando && 'animate-spin'"
                    />
                    Recalcular
                </Button>
            </template>
        </PageHeader>

        <!-- Contratos esperando que se publique el índice -->
        <div
            v-if="enEspera.length"
            class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/40"
        >
            <div class="flex items-start gap-3">
                <Clock
                    class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400"
                />
                <div class="min-w-0 text-sm">
                    <p class="font-medium text-amber-900 dark:text-amber-200">
                        Esperando que se publique el índice
                    </p>
                    <ul
                        class="mt-1 space-y-0.5 text-amber-800 dark:text-amber-300"
                    >
                        <li v-for="(motivo, i) in enEspera" :key="i">
                            {{ motivo }}
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Propuestas para resolver -->
        <section class="space-y-3">
            <h2 class="text-sm font-medium">
                Para revisar
                <span v-if="propuestos.length" class="text-muted-foreground">
                    ({{ propuestos.length }})
                </span>
            </h2>

            <EmptyState
                v-if="!propuestos.length"
                titulo="No hay ajustes pendientes"
                descripcion="Cuando un contrato llegue a su fecha de actualización y el índice esté publicado, la propuesta aparece acá."
                :icono="TrendingUp"
            />

            <div v-else class="grid gap-3">
                <article
                    v-for="ajuste in propuestos"
                    :key="ajuste.id"
                    class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
                >
                    <div
                        class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="font-medium">{{ ajuste.propiedad }}</p>
                            <p class="text-muted-foreground text-sm">
                                {{ ajuste.inquilino }} · vigencia
                                {{ ajuste.vigencia }}
                            </p>
                            <p class="text-muted-foreground mt-1 text-xs">
                                {{ ajuste.indice }} {{ ajuste.ventana }}
                            </p>
                        </div>

                        <!-- El número, que es lo que se viene a mirar -->
                        <div
                            class="flex items-baseline gap-2 tabular-nums lg:gap-3"
                        >
                            <span class="text-muted-foreground line-through">
                                {{ pesos(ajuste.monto_anterior) }}
                            </span>
                            <span class="text-muted-foreground">→</span>
                            <span class="text-lg font-semibold">
                                {{ pesos(ajuste.monto_nuevo) }}
                            </span>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="
                                    ajuste.variacion >= 0
                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
                                        : 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300'
                                "
                            >
                                {{ porcentaje(ajuste.variacion) }}
                            </span>
                        </div>

                        <div v-if="puedeGestionar" class="flex shrink-0 gap-2">
                            <Button size="sm" @click="abrirAplicar(ajuste)">
                                Aplicar
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                @click="rechazando = ajuste"
                            >
                                Rechazar
                            </Button>
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <!-- Historial -->
        <section v-if="historial.length" class="space-y-3">
            <h2 class="text-sm font-medium">Historial</h2>

            <div
                class="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border"
            >
                <table class="w-full text-sm">
                    <thead class="text-muted-foreground border-b text-left">
                        <tr>
                            <th class="px-4 py-2 font-medium">Propiedad</th>
                            <th class="px-4 py-2 font-medium">Vigencia</th>
                            <th class="px-4 py-2 font-medium">Índice</th>
                            <th class="px-4 py-2 text-right font-medium">
                                Anterior
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Nuevo
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Variación
                            </th>
                            <th class="px-4 py-2 font-medium">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="a in historial" :key="a.id">
                            <td class="px-4 py-2">{{ a.propiedad }}</td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                {{ a.vigencia }}
                            </td>
                            <td class="px-4 py-2">{{ a.indice }}</td>
                            <td
                                class="px-4 py-2 text-right whitespace-nowrap tabular-nums"
                            >
                                {{ pesos(a.monto_anterior) }}
                            </td>
                            <td
                                class="px-4 py-2 text-right whitespace-nowrap tabular-nums"
                            >
                                {{ pesos(a.monto_nuevo) }}
                            </td>
                            <td
                                class="px-4 py-2 text-right whitespace-nowrap tabular-nums"
                            >
                                {{ porcentaje(a.variacion) }}
                            </td>
                            <td class="px-4 py-2">
                                <EstadoBadge
                                    :estado="a.estado"
                                    :label="a.estado_label"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <!-- Confirmar aplicación, con el monto editable -->
    <Dialog
        :open="aplicando !== null"
        @update:open="(v) => !v && (aplicando = null)"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Aplicar el ajuste</DialogTitle>
                <DialogDescription v-if="aplicando">
                    {{ aplicando.propiedad }} — el alquiler pasa a valer esto
                    desde el {{ aplicando.vigencia }}.
                </DialogDescription>
            </DialogHeader>

            <div v-if="aplicando" class="space-y-4">
                <div class="text-muted-foreground flex justify-between text-sm">
                    <span>Alquiler actual</span>
                    <span class="tabular-nums">
                        {{ pesos(aplicando.monto_anterior) }}
                    </span>
                </div>

                <div class="grid gap-2">
                    <Label for="monto">Nuevo alquiler</Label>
                    <Input
                        id="monto"
                        v-model="formAplicar.monto"
                        type="number"
                        step="0.01"
                        class="tabular-nums"
                    />
                    <p class="text-muted-foreground text-xs">
                        Calculado por {{ aplicando.indice }}
                        {{ aplicando.ventana }} ({{
                            porcentaje(aplicando.variacion)
                        }}). Podés cambiarlo si pactaste otro número.
                    </p>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="aplicando = null">
                    Cancelar
                </Button>
                <Button
                    :disabled="formAplicar.processing"
                    @click="confirmarAplicar"
                >
                    Confirmar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Rechazar -->
    <Dialog
        :open="rechazando !== null"
        @update:open="(v) => !v && (rechazando = null)"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Rechazar el ajuste</DialogTitle>
                <DialogDescription>
                    El alquiler queda como está. El próximo ajuste se calcula
                    recién en el período siguiente, así que la inflación de este
                    trimestre no se recupera después.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-2">
                <Label for="motivo">Motivo (opcional)</Label>
                <Input
                    id="motivo"
                    v-model="formRechazar.motivo"
                    placeholder="Acordado con el inquilino…"
                />
            </div>

            <DialogFooter>
                <Button variant="outline" @click="rechazando = null">
                    Cancelar
                </Button>
                <Button
                    variant="destructive"
                    :disabled="formRechazar.processing"
                    @click="confirmarRechazar"
                >
                    Rechazar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

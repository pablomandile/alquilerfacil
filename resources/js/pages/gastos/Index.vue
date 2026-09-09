<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Paperclip, Pencil, Plus, Receipt } from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import EstadoBadge from '@/components/EstadoBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import VisorArchivo, {
    type ArchivoVisible,
} from '@/components/VisorArchivo.vue';
import { Button } from '@/components/ui/button';
import { pesos, pesosRedondos } from '@/lib/formato';
import rutasGastos from '@/routes/gastos';
import rutasGastosDocumentos from '@/routes/gastos/documentos';

type Documento = {
    id: number;
    tipo_label: string;
    nombre: string;
    mime: string;
};

type Gasto = {
    id: number;
    propiedad: string;
    propiedad_id: number;
    tipo: string;
    categoria: string;
    descripcion: string | null;
    periodo: string;
    monto: string;
    vencimiento: string | null;
    a_cargo_de: string;
    a_cargo_de_label: string;
    pagado: boolean;
    vencido: boolean;
    reparto: Array<{ nombre: string; porcentaje: number; monto: string }>;
    documentos: Documento[];
};

const props = defineProps<{
    gastos: Gasto[];
    filtros: {
        property_id?: number | null;
        tipo?: string | null;
        pagado?: string | null;
    };
    propiedades: Array<{ id: number; alias: string }>;
    tipos: Array<{ value: string; label: string }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Gastos', href: rutasGastos.index() }],
    },
});

const page = usePage();
const puedeGestionar = computed(() => page.props.auth?.puedeGestionar ?? false);

const filtro = ref({
    property_id: props.filtros.property_id ?? '',
    tipo: props.filtros.tipo ?? '',
    pagado: props.filtros.pagado ?? '',
});

function aplicarFiltros() {
    router.get(
        rutasGastos.index().url,
        Object.fromEntries(
            Object.entries(filtro.value).filter(([, v]) => v !== ''),
        ),
        { preserveState: true, preserveScroll: true },
    );
}

const total = computed(() =>
    props.gastos.reduce((suma, g) => suma + Number(g.monto), 0),
);

const impagos = computed(() => props.gastos.filter((g) => !g.pagado));

const impagoTotal = computed(() =>
    impagos.value.reduce((suma, g) => suma + Number(g.monto), 0),
);

const vencidos = computed(() => props.gastos.filter((g) => g.vencido).length);

const visor = ref<ArchivoVisible | null>(null);

function verDocumento(d: Documento) {
    visor.value = {
        nombre: d.nombre,
        mime: d.mime,
        verUrl: rutasGastosDocumentos.show(d.id).url,
        descargarUrl: rutasGastosDocumentos.show(d.id, {
            query: { descarga: 1 },
        }).url,
    };
}
</script>

<template>
    <Head title="Gastos" />

    <div class="tinte-rosa flex flex-1 flex-col gap-6 p-4">
        <PageHeader
            titulo="Gastos"
            :descripcion="`${gastos.length} gastos · ${pesos(total)} en total`"
        >
            <template #acciones>
                <Button v-if="puedeGestionar" as-child size="sm">
                    <Link :href="rutasGastos.create()">
                        <Plus class="size-4" />
                        Nuevo gasto
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div v-if="gastos.length" class="grid gap-4 sm:grid-cols-3">
            <StatCard
                etiqueta="Total"
                :valor="pesosRedondos(total)"
                :detalle="`${gastos.length} gastos`"
                tinte="rosa"
            />
            <StatCard
                etiqueta="Impago"
                :valor="pesosRedondos(impagoTotal)"
                :detalle="
                    impagos.length
                        ? `${impagos.length} sin pagar`
                        : 'todo al día'
                "
                :acento="impagos.length ? 'atencion' : 'normal'"
                tinte="ambar"
            />
            <StatCard
                etiqueta="Vencidos"
                :valor="vencidos"
                :acento="vencidos > 0 ? 'alerta' : 'normal'"
                tinte="rosa"
            />
        </div>

        <!-- Filtros -->
        <div class="flex flex-wrap gap-2">
            <select
                v-model="filtro.property_id"
                class="border-input bg-background h-9 rounded-md border px-3 text-sm"
                @change="aplicarFiltros"
            >
                <option value="">Todas las propiedades</option>
                <option v-for="p in propiedades" :key="p.id" :value="p.id">
                    {{ p.alias }}
                </option>
            </select>

            <select
                v-model="filtro.tipo"
                class="border-input bg-background h-9 rounded-md border px-3 text-sm"
                @change="aplicarFiltros"
            >
                <option value="">Todos los tipos</option>
                <option v-for="t in tipos" :key="t.value" :value="t.value">
                    {{ t.label }}
                </option>
            </select>

            <select
                v-model="filtro.pagado"
                class="border-input bg-background h-9 rounded-md border px-3 text-sm"
                @change="aplicarFiltros"
            >
                <option value="">Pagos e impagos</option>
                <option value="no">Sólo impagos</option>
                <option value="si">Sólo pagados</option>
            </select>
        </div>

        <EmptyState
            v-if="!gastos.length"
            titulo="No hay gastos cargados"
            descripcion="Cargá acá los servicios, expensas, impuestos y arreglos. Los que van a cargo de los dueños se reparten solos según su porcentaje."
            :icono="Receipt"
        >
            <Button v-if="puedeGestionar" as-child size="sm">
                <Link :href="rutasGastos.create()">Cargar un gasto</Link>
            </Button>
        </EmptyState>

        <div v-else class="grid gap-3">
            <article
                v-for="g in gastos"
                :key="g.id"
                class="tarjeta rounded-xl border p-4"
            >
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div class="min-w-0">
                        <p class="font-medium">
                            {{ g.descripcion || g.categoria }}
                        </p>
                        <p class="text-muted-foreground text-sm">
                            {{ g.propiedad }} · {{ g.categoria }} ·
                            {{ g.periodo }}
                        </p>
                        <p class="text-muted-foreground mt-1 text-xs">
                            A cargo de {{ g.a_cargo_de_label }}
                            <span v-if="g.vencimiento">
                                · vence {{ g.vencimiento }}
                            </span>
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
                        <span class="font-semibold tabular-nums">
                            {{ pesos(g.monto) }}
                        </span>
                        <EstadoBadge
                            :estado="
                                g.pagado
                                    ? 'pagado'
                                    : g.vencido
                                      ? 'vencido'
                                      : 'pendiente'
                            "
                            :label="
                                g.pagado
                                    ? 'Pagado'
                                    : g.vencido
                                      ? 'Vencido'
                                      : 'Impago'
                            "
                        />
                        <Button
                            v-if="puedeGestionar"
                            as-child
                            size="icon"
                            variant="ghost"
                            class="size-8"
                        >
                            <Link :href="rutasGastos.edit(g.id)">
                                <Pencil class="size-4" />
                                <span class="sr-only">Editar</span>
                            </Link>
                        </Button>
                    </div>
                </div>

                <!-- Comprobantes adjuntos -->
                <div
                    v-if="g.documentos.length"
                    class="mt-3 flex flex-wrap gap-x-4 gap-y-1 border-t pt-3 text-xs"
                >
                    <button
                        v-for="d in g.documentos"
                        :key="d.id"
                        type="button"
                        class="text-muted-foreground hover:text-foreground flex cursor-pointer items-center gap-1"
                        @click="verDocumento(d)"
                    >
                        <Paperclip class="size-3.5 shrink-0" />
                        {{ d.tipo_label }}
                    </button>
                </div>

                <!-- Reparto entre dueños, cuando el gasto va a cargo de ellos -->
                <div v-if="g.reparto.length" class="mt-3 border-t pt-3">
                    <p class="text-muted-foreground mb-1.5 text-xs">
                        {{
                            g.a_cargo_de === 'mitades'
                                ? 'La mitad, repartida entre los propietarios (la otra mitad la paga el inquilino)'
                                : 'Repartido entre los propietarios'
                        }}
                    </p>
                    <ul class="grid gap-1 text-sm sm:grid-cols-3">
                        <li
                            v-for="(parte, i) in g.reparto"
                            :key="i"
                            class="flex justify-between gap-2"
                        >
                            <span class="truncate">
                                {{ parte.nombre }}
                                <span class="text-muted-foreground text-xs">
                                    {{ parte.porcentaje }}%
                                </span>
                            </span>
                            <span class="shrink-0 tabular-nums">
                                {{ pesos(parte.monto) }}
                            </span>
                        </li>
                    </ul>
                </div>
            </article>
        </div>
    </div>

    <VisorArchivo v-model:archivo="visor" />
</template>

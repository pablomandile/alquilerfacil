<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { PieChart } from '@lucide/vue';
import { ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Input } from '@/components/ui/input';
import { pesos } from '@/lib/formato';
import rutasLiquidaciones from '@/routes/liquidaciones';

type Liquidacion = {
    id: number;
    nombre: string;
    alquileres: Array<{
        propiedad: string;
        porcentaje: number;
        monto: string;
        cobrado: string;
        estado: string;
    }>;
    gastos: Array<{
        propiedad: string;
        descripcion: string;
        porcentaje: number;
        monto: string;
        pagado: boolean;
    }>;
    facturado: string;
    cobrado: string;
    gastos_total: string;
    neto: string;
};

const props = defineProps<{
    propietarios: Liquidacion[];
    periodo: string;
    periodoLabel: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Liquidaciones', href: rutasLiquidaciones.index() },
        ],
    },
});

const periodoElegido = ref(props.periodo);

function cambiarPeriodo() {
    router.get(
        rutasLiquidaciones.index().url,
        { periodo: periodoElegido.value },
        { preserveState: true, preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Liquidaciones" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <PageHeader
            titulo="Liquidaciones"
            :descripcion="`Lo que le corresponde a cada dueño en ${periodoLabel}`"
        >
            <template #acciones>
                <Input
                    v-model="periodoElegido"
                    type="month"
                    class="w-full sm:w-48"
                    @change="cambiarPeriodo"
                />
            </template>
        </PageHeader>

        <EmptyState
            v-if="!propietarios.length"
            titulo="No hay movimientos en este mes"
            descripcion="Cuando se emitan los cargos de alquiler o se carguen gastos a cargo de los dueños, la liquidación aparece acá."
            :icono="PieChart"
        />

        <article
            v-for="p in propietarios"
            :key="p.id"
            class="border-sidebar-border/70 dark:border-sidebar-border tarjeta rounded-xl border"
        >
            <header
                class="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3"
            >
                <h2 class="font-medium">{{ p.nombre }}</h2>
                <div class="flex items-baseline gap-4 text-sm">
                    <span class="text-muted-foreground">
                        Cobrado
                        <span class="text-foreground ml-1 tabular-nums">
                            {{ pesos(p.cobrado) }}
                        </span>
                    </span>
                    <span
                        v-if="Number(p.gastos_total) > 0"
                        class="text-muted-foreground"
                    >
                        Gastos
                        <span
                            class="ml-1 text-rose-600 tabular-nums dark:text-rose-400"
                        >
                            −{{ pesos(p.gastos_total) }}
                        </span>
                    </span>
                    <span class="font-semibold tabular-nums">
                        {{ pesos(p.neto) }}
                    </span>
                </div>
            </header>

            <div class="grid gap-4 p-4 lg:grid-cols-2">
                <!-- Alquileres -->
                <div>
                    <h3 class="text-muted-foreground mb-2 text-xs font-medium">
                        Alquileres
                    </h3>
                    <ul v-if="p.alquileres.length" class="divide-y text-sm">
                        <li
                            v-for="(a, i) in p.alquileres"
                            :key="i"
                            class="flex items-center justify-between gap-3 py-2 first:pt-0"
                        >
                            <div class="min-w-0">
                                <p class="truncate">{{ a.propiedad }}</p>
                                <p class="text-muted-foreground text-xs">
                                    {{ a.porcentaje }}% · {{ a.estado }}
                                </p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="tabular-nums">{{ pesos(a.monto) }}</p>
                                <p
                                    v-if="a.cobrado !== a.monto"
                                    class="text-muted-foreground text-xs tabular-nums"
                                >
                                    cobrado {{ pesos(a.cobrado) }}
                                </p>
                            </div>
                        </li>
                    </ul>
                    <p v-else class="text-muted-foreground text-sm">—</p>
                </div>

                <!-- Gastos -->
                <div>
                    <h3 class="text-muted-foreground mb-2 text-xs font-medium">
                        Gastos a su cargo
                    </h3>
                    <ul v-if="p.gastos.length" class="divide-y text-sm">
                        <li
                            v-for="(g, i) in p.gastos"
                            :key="i"
                            class="flex items-center justify-between gap-3 py-2 first:pt-0"
                        >
                            <div class="min-w-0">
                                <p class="truncate">{{ g.descripcion }}</p>
                                <p
                                    class="text-muted-foreground truncate text-xs"
                                >
                                    {{ g.propiedad }} · {{ g.porcentaje }}%
                                </p>
                            </div>
                            <span class="shrink-0 tabular-nums">
                                −{{ pesos(g.monto) }}
                            </span>
                        </li>
                    </ul>
                    <p v-else class="text-muted-foreground text-sm">—</p>
                </div>
            </div>
        </article>
    </div>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Building2,
    CalendarClock,
    FileText,
    Receipt,
    TrendingUp,
    Wallet,
} from '@lucide/vue';
import EstadoBadge from '@/components/EstadoBadge.vue';
import StatCard from '@/components/StatCard.vue';
import { Button } from '@/components/ui/button';
import { pesos, pesosRedondos, porcentaje } from '@/lib/formato';
import { dashboard } from '@/routes';
import rutasAjustes from '@/routes/ajustes';
import rutasCobranzas from '@/routes/cobranzas';
import rutasGastos from '@/routes/gastos';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Panel', href: dashboard() }],
    },
});

defineProps<{
    mes: string;
    cobranza: {
        facturado: string;
        cobrado: string;
        pendiente: string;
        cargos: number;
        vencidos: number;
    };
    resumen: {
        propiedades: number;
        contratos_activos: number;
        ajustes_propuestos: number;
        gastos_impagos: number;
    };
    ajustesPendientes: Array<{
        id: number;
        propiedad: string;
        monto_actual: string;
        fecha: string;
        indice: string;
    }>;
    gastosPorVencer: Array<{
        id: number;
        propiedad: string;
        descripcion: string;
        monto: string;
        vencimiento: string;
        vencido: boolean;
    }>;
    indices: Array<{
        nombre: string;
        fecha: string | null;
        valor: number | null;
        variacion: number | null;
    }>;
}>();
</script>

<template>
    <Head title="Panel" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Panel</h1>
            <p
                class="text-muted-foreground mt-1 text-sm first-letter:uppercase"
            >
                {{ mes }}
            </p>
        </div>

        <!-- Cobranza del mes -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                etiqueta="Facturado del mes"
                :valor="pesosRedondos(cobranza.facturado)"
                :detalle="`${cobranza.cargos} cargos emitidos`"
                :icono="Wallet"
            />
            <StatCard
                etiqueta="Cobrado"
                :valor="pesosRedondos(cobranza.cobrado)"
                acento="positivo"
                :icono="Wallet"
            />
            <StatCard
                etiqueta="Pendiente de cobro"
                :valor="pesosRedondos(cobranza.pendiente)"
                :acento="Number(cobranza.pendiente) > 0 ? 'atencion' : 'normal'"
                :detalle="
                    cobranza.vencidos > 0
                        ? `${cobranza.vencidos} vencidos`
                        : undefined
                "
                :icono="CalendarClock"
            />
            <StatCard
                etiqueta="Ajustes para revisar"
                :valor="resumen.ajustes_propuestos"
                :acento="resumen.ajustes_propuestos > 0 ? 'atencion' : 'normal'"
                :icono="TrendingUp"
            />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <!-- Ajustes que ya están en fecha -->
            <section
                class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border"
            >
                <header
                    class="flex items-center justify-between border-b px-4 py-3"
                >
                    <h2 class="text-sm font-medium">Alquileres a actualizar</h2>
                    <Button as-child size="sm" variant="ghost">
                        <Link :href="rutasAjustes.index()">Ver ajustes</Link>
                    </Button>
                </header>

                <ul v-if="ajustesPendientes.length" class="divide-y text-sm">
                    <li
                        v-for="item in ajustesPendientes"
                        :key="item.id"
                        class="flex items-center justify-between gap-3 px-4 py-3"
                    >
                        <div class="min-w-0">
                            <p class="truncate font-medium">
                                {{ item.propiedad }}
                            </p>
                            <p class="text-muted-foreground text-xs">
                                Ajusta el {{ item.fecha }} por
                                {{ item.indice }}
                            </p>
                        </div>
                        <span class="shrink-0 tabular-nums">
                            {{ pesos(item.monto_actual) }}
                        </span>
                    </li>
                </ul>
                <p v-else class="text-muted-foreground px-4 py-6 text-sm">
                    Ningún contrato llegó a su fecha de ajuste.
                </p>
            </section>

            <!-- Gastos impagos -->
            <section
                class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border"
            >
                <header
                    class="flex items-center justify-between border-b px-4 py-3"
                >
                    <h2 class="text-sm font-medium">Gastos por vencer</h2>
                    <Button as-child size="sm" variant="ghost">
                        <Link :href="rutasGastos.index()">Ver gastos</Link>
                    </Button>
                </header>

                <ul v-if="gastosPorVencer.length" class="divide-y text-sm">
                    <li
                        v-for="gasto in gastosPorVencer"
                        :key="gasto.id"
                        class="flex items-center justify-between gap-3 px-4 py-3"
                    >
                        <div class="min-w-0">
                            <p class="truncate font-medium">
                                {{ gasto.descripcion }}
                            </p>
                            <p class="text-muted-foreground truncate text-xs">
                                {{ gasto.propiedad }} · vence
                                {{ gasto.vencimiento }}
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <EstadoBadge
                                v-if="gasto.vencido"
                                estado="vencido"
                                label="Vencido"
                            />
                            <span class="tabular-nums">
                                {{ pesos(gasto.monto) }}
                            </span>
                        </div>
                    </li>
                </ul>
                <p v-else class="text-muted-foreground px-4 py-6 text-sm">
                    No hay gastos impagos.
                </p>
            </section>
        </div>

        <!-- Índices y totales -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                etiqueta="Propiedades"
                :valor="resumen.propiedades"
                :icono="Building2"
            />
            <StatCard
                etiqueta="Contratos activos"
                :valor="resumen.contratos_activos"
                :icono="FileText"
            />
            <StatCard
                etiqueta="Gastos impagos"
                :valor="resumen.gastos_impagos"
                :acento="resumen.gastos_impagos > 0 ? 'atencion' : 'normal'"
                :icono="Receipt"
            />
            <div
                class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
            >
                <p class="text-muted-foreground text-sm">Último índice</p>
                <ul class="mt-2 space-y-1.5">
                    <li
                        v-for="indice in indices"
                        :key="indice.nombre"
                        class="flex items-baseline justify-between gap-2 text-sm"
                    >
                        <span class="font-medium">{{ indice.nombre }}</span>
                        <span
                            class="text-muted-foreground text-xs first-letter:uppercase"
                        >
                            {{ indice.fecha ?? 'sin datos' }}
                        </span>
                        <span
                            v-if="indice.variacion !== null"
                            class="tabular-nums"
                        >
                            {{ porcentaje(indice.variacion) }}
                        </span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <Button as-child variant="outline" size="sm">
                <Link :href="rutasCobranzas.index()">Ir a cobranzas</Link>
            </Button>
        </div>
    </div>
</template>

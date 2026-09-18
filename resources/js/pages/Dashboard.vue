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
import GraficoLineas from '@/components/GraficoLineas.vue';
import GraficoTorta from '@/components/GraficoTorta.vue';
import StatCard from '@/components/StatCard.vue';
import { Button } from '@/components/ui/button';
import { pesos, pesosRedondos, porcentaje } from '@/lib/formato';
import { dashboard } from '@/routes';
import rutasAjustes from '@/routes/ajustes';
import rutasCobranzas from '@/routes/cobranzas';
import rutasGastos from '@/routes/gastos';
import rutasPropiedades from '@/routes/propiedades';

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
    alquileres: {
        por_propiedad: Array<{
            id: number;
            alias: string;
            cobrado: string;
            neto: string;
        }>;
        facturado: string;
        cobrado: string;
        gastos_ordinarios: string;
        gastos_extraordinarios: string;
        neto: string;
    };
    evolucion: Array<{
        clave: string;
        alquileres: string | null;
        gastos: string | null;
    }>;
    gastos: {
        por_propiedad: Array<{
            id: number;
            alias: string;
            total: string;
            categorias: Array<{
                clave: string;
                etiqueta: string;
                monto: string;
                color: number;
            }>;
        }>;
        leyenda: Array<{ clave: string; etiqueta: string; color: number }>;
        total: string;
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
                tinte="indigo"
            />
            <StatCard
                etiqueta="Cobrado"
                :valor="pesosRedondos(cobranza.cobrado)"
                acento="positivo"
                :icono="Wallet"
                tinte="esmeralda"
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
                tinte="ambar"
            />
            <StatCard
                etiqueta="Ajustes para revisar"
                :valor="resumen.ajustes_propuestos"
                :acento="resumen.ajustes_propuestos > 0 ? 'atencion' : 'normal'"
                :icono="TrendingUp"
                tinte="violeta"
            />
        </div>

        <!-- Cómo vienen el alquiler y los gastos mes a mes -->
        <section
            class="tarjeta tinte-esmeralda paleta-evolucion rounded-xl border"
        >
            <header
                class="flex items-center justify-between gap-3 border-b px-4 py-3"
            >
                <h2 class="text-sm font-medium">Alquileres y gastos por mes</h2>
                <Button as-child size="sm" variant="ghost">
                    <Link :href="rutasCobranzas.index()">Ver cobranzas</Link>
                </Button>
            </header>

            <div v-if="evolucion.length" class="px-4 py-4">
                <GraficoLineas :meses="evolucion" />
            </div>
            <p v-else class="text-muted-foreground px-4 py-6 text-sm">
                Todavía no hay meses con movimiento.
            </p>
        </section>

        <div class="grid gap-4 lg:grid-cols-2">
            <!-- Ajustes que ya están en fecha -->
            <section class="tarjeta tinte-violeta rounded-xl border">
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
            <section class="tarjeta tinte-rosa rounded-xl border">
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

        <div class="grid items-start gap-4 lg:grid-cols-2">
            <!-- Acumulado del alquiler por propiedad -->
            <section class="tarjeta tinte-indigo rounded-xl border">
                <header
                    class="flex items-center justify-between border-b px-4 py-3"
                >
                    <h2 class="text-sm font-medium">
                        Alquileres por propiedad
                    </h2>
                    <Button as-child size="sm" variant="ghost">
                        <Link :href="rutasPropiedades.index()">
                            Ver propiedades
                        </Link>
                    </Button>
                </header>

                <template v-if="alquileres.por_propiedad.length">
                    <ul class="divide-y text-sm">
                        <li
                            v-for="p in alquileres.por_propiedad"
                            :key="p.id"
                            class="flex items-center justify-between gap-3 px-4 py-3"
                        >
                            <div class="min-w-0">
                                <p class="truncate font-medium">
                                    {{ p.alias }}
                                </p>
                                <p
                                    class="text-muted-foreground text-xs tabular-nums"
                                >
                                    Cobrado {{ pesos(p.cobrado) }}
                                </p>
                            </div>
                            <span
                                class="shrink-0 font-semibold tabular-nums"
                                :class="
                                    Number(p.neto) < 0
                                        ? 'text-rose-600 dark:text-rose-400'
                                        : ''
                                "
                            >
                                {{ pesos(p.neto) }}
                            </span>
                        </li>
                    </ul>

                    <dl class="space-y-1.5 border-t px-4 py-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-muted-foreground">Facturado</dt>
                            <dd class="tabular-nums">
                                {{ pesos(alquileres.facturado) }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-muted-foreground">Cobrado</dt>
                            <dd class="tabular-nums">
                                {{ pesos(alquileres.cobrado) }}
                            </dd>
                        </div>
                        <div
                            v-if="Number(alquileres.gastos_ordinarios) > 0"
                            class="flex items-center justify-between gap-3"
                        >
                            <dt class="text-muted-foreground">
                                Gastos ordinarios a cargo de los dueños
                            </dt>
                            <dd
                                class="text-rose-600 tabular-nums dark:text-rose-400"
                            >
                                −{{ pesos(alquileres.gastos_ordinarios) }}
                            </dd>
                        </div>
                        <div
                            v-if="Number(alquileres.gastos_extraordinarios) > 0"
                            class="flex items-center justify-between gap-3"
                        >
                            <dt class="text-muted-foreground">
                                Gastos extraordinarios
                            </dt>
                            <dd
                                class="text-rose-600 tabular-nums dark:text-rose-400"
                            >
                                −{{ pesos(alquileres.gastos_extraordinarios) }}
                            </dd>
                        </div>
                        <div
                            class="flex items-center justify-between gap-3 border-t pt-1.5 font-semibold"
                        >
                            <dt>Neto</dt>
                            <dd
                                class="tabular-nums"
                                :class="
                                    Number(alquileres.neto) < 0
                                        ? 'text-rose-600 dark:text-rose-400'
                                        : ''
                                "
                            >
                                {{ pesos(alquileres.neto) }}
                            </dd>
                        </div>
                    </dl>
                </template>

                <p v-else class="text-muted-foreground px-4 py-6 text-sm">
                    Todavía no hay alquileres facturados.
                </p>
            </section>

            <!-- A dónde se va la plata: una torta por propiedad, abierta por
                 categoría. La paleta va acá para que las tortas y la leyenda
                 hereden los mismos colores. -->
            <section class="tarjeta tinte-rosa paleta-gastos rounded-xl border">
                <header
                    class="flex items-center justify-between border-b px-4 py-3"
                >
                    <h2 class="text-sm font-medium">Gastos por propiedad</h2>
                    <Button as-child size="sm" variant="ghost">
                        <Link :href="rutasGastos.index()">Ver gastos</Link>
                    </Button>
                </header>

                <template v-if="gastos.por_propiedad.length">
                    <div
                        class="flex flex-wrap justify-center gap-x-6 gap-y-5 px-4 py-5"
                    >
                        <GraficoTorta
                            v-for="p in gastos.por_propiedad"
                            :key="p.id"
                            class="w-36"
                            :porciones="p.categorias"
                            :titulo="p.alias"
                        />
                    </div>

                    <!-- Una sola leyenda para todas las tortas: así el color
                         nunca es lo único que identifica a una categoría. -->
                    <ul
                        class="flex flex-wrap gap-x-4 gap-y-1.5 border-t px-4 py-3 text-xs"
                    >
                        <li
                            v-for="c in gastos.leyenda"
                            :key="c.clave"
                            class="flex items-center gap-1.5"
                        >
                            <span
                                class="size-2.5 shrink-0 rounded-full"
                                :style="{
                                    background: `var(--torta-${c.color})`,
                                }"
                                aria-hidden="true"
                            />
                            {{ c.etiqueta }}
                        </li>
                    </ul>

                    <div
                        class="flex items-center justify-between gap-3 border-t px-4 py-3 text-sm"
                    >
                        <span class="text-muted-foreground">
                            Total de gastos
                        </span>
                        <span class="font-semibold tabular-nums">
                            {{ pesos(gastos.total) }}
                        </span>
                    </div>
                </template>

                <p v-else class="text-muted-foreground px-4 py-6 text-sm">
                    Todavía no hay gastos cargados.
                </p>
            </section>
        </div>

        <!-- Índices y totales -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                etiqueta="Propiedades"
                :valor="resumen.propiedades"
                :icono="Building2"
                tinte="cielo"
            />
            <StatCard
                etiqueta="Contratos activos"
                :valor="resumen.contratos_activos"
                :icono="FileText"
                tinte="indigo"
            />
            <StatCard
                etiqueta="Gastos impagos"
                :valor="resumen.gastos_impagos"
                :acento="resumen.gastos_impagos > 0 ? 'atencion' : 'normal'"
                :icono="Receipt"
                tinte="rosa"
            />
            <div class="tarjeta tinte-turquesa rounded-xl border p-4">
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

<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import { computed } from 'vue';
import EstadoBadge from '@/components/EstadoBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { pesos } from '@/lib/formato';
import rutasContratos from '@/routes/contratos';
import rutasPropiedades from '@/routes/propiedades';

const props = defineProps<{
    propiedad: {
        id: number;
        alias: string;
        direccion: string;
        tipo: string;
        estado: string;
        estado_label: string;
        ambientes: number | null;
        superficie_m2: string | null;
        partida_inmobiliaria: string | null;
        notas: string | null;
        propietarios: Array<{
            id: number;
            nombre: string;
            email: string | null;
            telefono: string | null;
            porcentaje: number;
        }>;
        contratos: Array<{
            id: number;
            inquilino: string;
            desde: string;
            hasta: string;
            monto_actual: string;
            estado: string;
            estado_label: string;
            indice: string;
        }>;
        gastos: Array<{
            id: number;
            descripcion: string;
            categoria: string;
            periodo: string;
            monto: string;
            a_cargo_de: string;
            pagado: boolean;
        }>;
        totales: {
            por_contrato: Array<{
                id: number;
                inquilino: string;
                estado: string;
                estado_label: string;
                facturado: string;
                cobrado: string;
            }>;
            facturado: string;
            cobrado: string;
            gastos_extraordinarios: string;
            neto: string;
        };
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Propiedades', href: rutasPropiedades.index() }],
    },
});

const page = usePage();
const esAdmin = computed(() => page.props.auth?.esAdmin ?? false);
</script>

<template>
    <Head :title="propiedad.alias" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <PageHeader
            :titulo="propiedad.alias"
            :descripcion="propiedad.direccion"
        >
            <template #acciones>
                <EstadoBadge
                    :estado="propiedad.estado"
                    :label="propiedad.estado_label"
                />
                <Button v-if="esAdmin" as-child size="sm" variant="outline">
                    <Link :href="rutasPropiedades.edit(propiedad.id)">
                        <Pencil class="size-4" />
                        Editar
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 lg:grid-cols-3">
            <!-- Ficha -->
            <section
                class="border-sidebar-border/70 dark:border-sidebar-border tarjeta rounded-xl border p-4"
            >
                <h2 class="text-sm font-medium">Ficha</h2>
                <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-muted-foreground text-xs">Tipo</dt>
                        <dd>{{ propiedad.tipo }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground text-xs">Ambientes</dt>
                        <dd>{{ propiedad.ambientes ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground text-xs">
                            Superficie
                        </dt>
                        <dd>
                            {{
                                propiedad.superficie_m2
                                    ? `${propiedad.superficie_m2} m²`
                                    : '—'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground text-xs">Partida</dt>
                        <dd>{{ propiedad.partida_inmobiliaria ?? '—' }}</dd>
                    </div>
                </dl>
                <p
                    v-if="propiedad.notas"
                    class="text-muted-foreground mt-3 border-t pt-3 text-sm"
                >
                    {{ propiedad.notas }}
                </p>
            </section>

            <!-- Dueños -->
            <section
                class="border-sidebar-border/70 dark:border-sidebar-border tarjeta rounded-xl border p-4 lg:col-span-2"
            >
                <h2 class="text-sm font-medium">Propietarios</h2>

                <ul
                    v-if="propiedad.propietarios.length"
                    class="mt-3 divide-y text-sm"
                >
                    <li
                        v-for="o in propiedad.propietarios"
                        :key="o.id"
                        class="flex items-center justify-between gap-3 py-2 first:pt-0"
                    >
                        <div class="min-w-0">
                            <p class="truncate font-medium">{{ o.nombre }}</p>
                            <p class="text-muted-foreground truncate text-xs">
                                {{
                                    [o.email, o.telefono]
                                        .filter(Boolean)
                                        .join(' · ') || '—'
                                }}
                            </p>
                        </div>
                        <span class="shrink-0 font-medium tabular-nums">
                            {{ o.porcentaje }} %
                        </span>
                    </li>
                </ul>
                <p v-else class="text-muted-foreground mt-3 text-sm">
                    Sin propietarios cargados. Hasta que no los cargues, no se
                    pueden emitir cargos de alquiler para esta propiedad.
                </p>
            </section>
        </div>

        <!-- Contratos -->
        <section class="space-y-3">
            <h2 class="text-sm font-medium">Contratos</h2>

            <div
                v-if="propiedad.contratos.length"
                class="border-sidebar-border/70 dark:border-sidebar-border tarjeta overflow-x-auto rounded-xl border"
            >
                <table class="w-full text-sm">
                    <thead class="text-muted-foreground border-b text-left">
                        <tr>
                            <th class="px-4 py-2 font-medium">Inquilino</th>
                            <th class="px-4 py-2 font-medium">Desde</th>
                            <th class="px-4 py-2 font-medium">Hasta</th>
                            <th class="px-4 py-2 font-medium">Índice</th>
                            <th class="px-4 py-2 text-right font-medium">
                                Alquiler
                            </th>
                            <th class="px-4 py-2 font-medium">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="c in propiedad.contratos"
                            :key="c.id"
                            class="hover:bg-accent/50"
                        >
                            <td class="px-4 py-2">
                                <Link
                                    :href="rutasContratos.show(c.id)"
                                    class="font-medium hover:underline"
                                >
                                    {{ c.inquilino }}
                                </Link>
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                {{ c.desde }}
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                {{ c.hasta }}
                            </td>
                            <td class="px-4 py-2">{{ c.indice }}</td>
                            <td
                                class="px-4 py-2 text-right whitespace-nowrap tabular-nums"
                            >
                                {{ pesos(c.monto_actual) }}
                            </td>
                            <td class="px-4 py-2">
                                <EstadoBadge
                                    :estado="c.estado"
                                    :label="c.estado_label"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="text-muted-foreground text-sm">
                Esta propiedad todavía no tiene contratos.
            </p>
        </section>

        <!-- Totales del alquiler: acumulado de todos los contratos y el neto -->
        <section v-if="propiedad.contratos.length" class="space-y-3">
            <h2 class="text-sm font-medium">Totales del alquiler</h2>

            <div
                class="border-sidebar-border/70 dark:border-sidebar-border tarjeta overflow-hidden rounded-xl border"
            >
                <ul class="divide-y text-sm">
                    <li
                        v-for="c in propiedad.totales.por_contrato"
                        :key="c.id"
                        class="px-4 py-3"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <Link
                                :href="rutasContratos.show(c.id)"
                                class="truncate font-medium hover:underline"
                            >
                                {{ c.inquilino }}
                            </Link>
                            <EstadoBadge
                                :estado="c.estado"
                                :label="c.estado_label"
                            />
                        </div>
                        <div
                            class="text-muted-foreground mt-1 flex flex-wrap gap-x-4 gap-y-0.5 text-xs tabular-nums"
                        >
                            <span>Facturado {{ pesos(c.facturado) }}</span>
                            <span>Cobrado {{ pesos(c.cobrado) }}</span>
                        </div>
                    </li>
                </ul>

                <dl class="space-y-1.5 border-t px-4 py-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted-foreground">Facturado total</dt>
                        <dd class="tabular-nums">
                            {{ pesos(propiedad.totales.facturado) }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted-foreground">Cobrado total</dt>
                        <dd class="tabular-nums">
                            {{ pesos(propiedad.totales.cobrado) }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted-foreground">
                            Gastos extraordinarios
                        </dt>
                        <dd
                            class="text-rose-600 tabular-nums dark:text-rose-400"
                        >
                            −
                            {{
                                pesos(propiedad.totales.gastos_extraordinarios)
                            }}
                        </dd>
                    </div>
                    <div
                        class="flex items-center justify-between gap-3 border-t pt-1.5 text-base font-semibold"
                    >
                        <dt>Neto</dt>
                        <dd
                            class="tabular-nums"
                            :class="
                                Number(propiedad.totales.neto) < 0
                                    ? 'text-rose-600 dark:text-rose-400'
                                    : ''
                            "
                        >
                            {{ pesos(propiedad.totales.neto) }}
                        </dd>
                    </div>
                </dl>
            </div>
        </section>

        <!-- Gastos -->
        <section v-if="propiedad.gastos.length" class="space-y-3">
            <h2 class="text-sm font-medium">Últimos gastos</h2>

            <div
                class="border-sidebar-border/70 dark:border-sidebar-border tarjeta overflow-x-auto rounded-xl border"
            >
                <table class="w-full text-sm">
                    <thead class="text-muted-foreground border-b text-left">
                        <tr>
                            <th class="px-4 py-2 font-medium">Concepto</th>
                            <th class="px-4 py-2 font-medium">Período</th>
                            <th class="px-4 py-2 font-medium">A cargo de</th>
                            <th class="px-4 py-2 text-right font-medium">
                                Monto
                            </th>
                            <th class="px-4 py-2 font-medium">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="g in propiedad.gastos" :key="g.id">
                            <td class="px-4 py-2">{{ g.descripcion }}</td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                {{ g.periodo }}
                            </td>
                            <td class="px-4 py-2">{{ g.a_cargo_de }}</td>
                            <td
                                class="px-4 py-2 text-right whitespace-nowrap tabular-nums"
                            >
                                {{ pesos(g.monto) }}
                            </td>
                            <td class="px-4 py-2">
                                <EstadoBadge
                                    :estado="g.pagado ? 'pagado' : 'pendiente'"
                                    :label="g.pagado ? 'Pagado' : 'Impago'"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>

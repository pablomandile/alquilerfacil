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
                class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
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
                class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4 lg:col-span-2"
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
                                {{ [o.email, o.telefono].filter(Boolean).join(' · ') || '—' }}
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
                class="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border"
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
                                class="px-4 py-2 text-right tabular-nums whitespace-nowrap"
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

        <!-- Gastos -->
        <section v-if="propiedad.gastos.length" class="space-y-3">
            <h2 class="text-sm font-medium">Últimos gastos</h2>

            <div
                class="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border"
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
                                class="px-4 py-2 text-right tabular-nums whitespace-nowrap"
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

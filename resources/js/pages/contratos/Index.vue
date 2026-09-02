<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { FileText, Plus } from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import EstadoBadge from '@/components/EstadoBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { pesos } from '@/lib/formato';
import rutasContratos from '@/routes/contratos';

type Contrato = {
    id: number;
    propiedad: string;
    propiedad_id: number;
    inquilino: string;
    desde: string;
    hasta: string;
    monto_actual: string;
    indice: string;
    frecuencia_meses: number;
    proximo_ajuste: string | null;
    ajuste_vencido: boolean;
    estado: string;
    estado_label: string;
};

defineProps<{ contratos: Contrato[] }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Contratos', href: rutasContratos.index() }],
    },
});

const page = usePage();
const esAdmin = computed(() => page.props.auth?.esAdmin ?? false);
</script>

<template>
    <Head title="Contratos" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <PageHeader titulo="Contratos">
            <template #acciones>
                <Button v-if="esAdmin" as-child size="sm">
                    <Link :href="rutasContratos.create()">
                        <Plus class="size-4" />
                        Nuevo contrato
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <EmptyState
            v-if="!contratos.length"
            titulo="No hay contratos cargados"
            descripcion="Un contrato une una propiedad con un inquilino y define cómo se actualiza el alquiler."
            :icono="FileText"
        >
            <Button v-if="esAdmin" as-child size="sm">
                <Link :href="rutasContratos.create()">Cargar el primero</Link>
            </Button>
        </EmptyState>

        <template v-else>
            <!-- Tarjetas en el celular -->
            <div class="grid gap-3 md:hidden">
                <Link
                    v-for="c in contratos"
                    :key="c.id"
                    :href="rutasContratos.show(c.id)"
                    class="border-sidebar-border/70 dark:border-sidebar-border hover:bg-accent/50 rounded-xl border p-4"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="truncate font-medium">
                                {{ c.propiedad }}
                            </p>
                            <p class="text-muted-foreground truncate text-sm">
                                {{ c.inquilino }}
                            </p>
                        </div>
                        <EstadoBadge
                            :estado="c.estado"
                            :label="c.estado_label"
                        />
                    </div>

                    <div class="mt-3 flex items-end justify-between gap-2">
                        <div class="text-muted-foreground text-xs">
                            <p>{{ c.desde }} — {{ c.hasta }}</p>
                            <p
                                v-if="c.proximo_ajuste"
                                :class="
                                    c.ajuste_vencido &&
                                    'font-medium text-amber-600 dark:text-amber-400'
                                "
                            >
                                Ajusta {{ c.proximo_ajuste }} ({{ c.indice }}
                                cada {{ c.frecuencia_meses }} m)
                            </p>
                        </div>
                        <span class="font-medium tabular-nums">
                            {{ pesos(c.monto_actual) }}
                        </span>
                    </div>
                </Link>
            </div>

            <div
                class="border-sidebar-border/70 dark:border-sidebar-border hidden overflow-x-auto rounded-xl border md:block"
            >
                <table class="w-full text-sm">
                    <thead class="text-muted-foreground border-b text-left">
                        <tr>
                            <th class="px-4 py-2 font-medium">Propiedad</th>
                            <th class="px-4 py-2 font-medium">Inquilino</th>
                            <th class="px-4 py-2 font-medium">Vigencia</th>
                            <th class="px-4 py-2 font-medium">Ajuste</th>
                            <th class="px-4 py-2 text-right font-medium">
                                Alquiler
                            </th>
                            <th class="px-4 py-2 font-medium">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="c in contratos"
                            :key="c.id"
                            class="hover:bg-accent/50"
                        >
                            <td class="px-4 py-2">
                                <Link
                                    :href="rutasContratos.show(c.id)"
                                    class="font-medium hover:underline"
                                >
                                    {{ c.propiedad }}
                                </Link>
                            </td>
                            <td class="px-4 py-2">{{ c.inquilino }}</td>
                            <td
                                class="text-muted-foreground px-4 py-2 text-xs whitespace-nowrap"
                            >
                                {{ c.desde }} — {{ c.hasta }}
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                <span class="text-xs">
                                    {{ c.indice }} cada
                                    {{ c.frecuencia_meses }} m
                                </span>
                                <p
                                    v-if="c.proximo_ajuste"
                                    class="text-xs"
                                    :class="
                                        c.ajuste_vencido
                                            ? 'font-medium text-amber-600 dark:text-amber-400'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    {{ c.proximo_ajuste }}
                                </p>
                            </td>
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
        </template>
    </div>
</template>

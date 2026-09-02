<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Building2, Plus } from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import EstadoBadge from '@/components/EstadoBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { pesos } from '@/lib/formato';
import rutasPropiedades from '@/routes/propiedades';

type Propiedad = {
    id: number;
    alias: string;
    direccion: string;
    tipo: string;
    estado: string;
    estado_label: string;
    ambientes: number | null;
    superficie_m2: string | null;
    propietarios: Array<{ nombre: string; porcentaje: number }>;
    inquilino: string | null;
    monto_actual: string | null;
};

defineProps<{ propiedades: Propiedad[] }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Propiedades', href: rutasPropiedades.index() }],
    },
});

const page = usePage();
const esAdmin = computed(() => page.props.auth?.esAdmin ?? false);
</script>

<template>
    <Head title="Propiedades" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <PageHeader titulo="Propiedades">
            <template #acciones>
                <Button v-if="esAdmin" as-child size="sm">
                    <Link :href="rutasPropiedades.create()">
                        <Plus class="size-4" />
                        Nueva propiedad
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <EmptyState
            v-if="!propiedades.length"
            titulo="Todavía no cargaste ninguna propiedad"
            descripcion="Empezá por acá: después vas a poder asociarle dueños, un contrato y sus gastos."
            :icono="Building2"
        >
            <Button v-if="esAdmin" as-child size="sm">
                <Link :href="rutasPropiedades.create()">Cargar la primera</Link>
            </Button>
        </EmptyState>

        <!-- En el celular, tarjetas apiladas: una tabla de 6 columnas no entra. -->
        <div v-else class="grid gap-3 md:hidden">
            <Link
                v-for="p in propiedades"
                :key="p.id"
                :href="rutasPropiedades.show(p.id)"
                class="border-sidebar-border/70 dark:border-sidebar-border hover:bg-accent/50 rounded-xl border p-4"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ p.alias }}</p>
                        <p class="text-muted-foreground truncate text-sm">
                            {{ p.direccion }}
                        </p>
                    </div>
                    <EstadoBadge :estado="p.estado" :label="p.estado_label" />
                </div>

                <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <dt class="text-muted-foreground text-xs">Inquilino</dt>
                        <dd class="truncate">{{ p.inquilino ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground text-xs">Alquiler</dt>
                        <dd class="tabular-nums">
                            {{ p.monto_actual ? pesos(p.monto_actual) : '—' }}
                        </dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-muted-foreground text-xs">Dueños</dt>
                        <dd class="truncate">
                            {{
                                p.propietarios
                                    .map((o) => `${o.nombre} ${o.porcentaje}%`)
                                    .join(' · ') || '—'
                            }}
                        </dd>
                    </div>
                </dl>
            </Link>
        </div>

        <div
            v-if="propiedades.length"
            class="border-sidebar-border/70 dark:border-sidebar-border hidden overflow-x-auto rounded-xl border md:block"
        >
            <table class="w-full text-sm">
                <thead class="text-muted-foreground border-b text-left">
                    <tr>
                        <th class="px-4 py-2 font-medium">Propiedad</th>
                        <th class="px-4 py-2 font-medium">Tipo</th>
                        <th class="px-4 py-2 font-medium">Dueños</th>
                        <th class="px-4 py-2 font-medium">Inquilino</th>
                        <th class="px-4 py-2 text-right font-medium">
                            Alquiler
                        </th>
                        <th class="px-4 py-2 font-medium">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr
                        v-for="p in propiedades"
                        :key="p.id"
                        class="hover:bg-accent/50"
                    >
                        <td class="px-4 py-2">
                            <Link
                                :href="rutasPropiedades.show(p.id)"
                                class="font-medium hover:underline"
                            >
                                {{ p.alias }}
                            </Link>
                            <p class="text-muted-foreground text-xs">
                                {{ p.direccion }}
                            </p>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            {{ p.tipo }}
                        </td>
                        <td class="px-4 py-2">
                            <span
                                v-for="(o, i) in p.propietarios"
                                :key="i"
                                class="text-muted-foreground mr-2 text-xs whitespace-nowrap"
                            >
                                {{ o.nombre }}
                                <span class="tabular-nums"
                                    >{{ o.porcentaje }}%</span
                                >
                            </span>
                            <span
                                v-if="!p.propietarios.length"
                                class="text-muted-foreground"
                                >—</span
                            >
                        </td>
                        <td class="px-4 py-2">{{ p.inquilino ?? '—' }}</td>
                        <td
                            class="px-4 py-2 text-right whitespace-nowrap tabular-nums"
                        >
                            {{ p.monto_actual ? pesos(p.monto_actual) : '—' }}
                        </td>
                        <td class="px-4 py-2">
                            <EstadoBadge
                                :estado="p.estado"
                                :label="p.estado_label"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

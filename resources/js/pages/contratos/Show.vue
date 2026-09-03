<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Clock, Pencil, TrendingUp } from '@lucide/vue';
import { computed } from 'vue';
import EstadoBadge from '@/components/EstadoBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { pesos, porcentaje } from '@/lib/formato';
import rutasAjustes from '@/routes/ajustes';
import rutasContratos from '@/routes/contratos';
import rutasPropiedades from '@/routes/propiedades';

defineProps<{
    contrato: {
        id: number;
        propiedad: string;
        propiedad_id: number;
        inquilino: {
            id: number;
            nombre: string;
            email: string | null;
            telefono: string | null;
            documento: string | null;
        };
        desde: string;
        hasta: string;
        monto_base: string;
        monto_actual: string;
        deposito: string | null;
        dia_vencimiento: number;
        indice: string;
        frecuencia_meses: number;
        proximo_ajuste: string | null;
        estado: string;
        estado_label: string;
        notas: string | null;
        ajustes: Array<{
            id: number;
            vigencia: string;
            monto_anterior: string;
            monto_nuevo: string;
            variacion: number;
            indice: string;
            estado: string;
            estado_label: string;
        }>;
        cargos: Array<{
            id: number;
            periodo: string;
            monto: string;
            pagado: string;
            saldo: string;
            vencimiento: string;
            estado: string;
            estado_label: string;
        }>;
    };
    proyeccion:
        | {
              disponible: true;
              vigencia: string;
              monto_nuevo: string;
              variacion: number;
              ventana: string;
          }
        | { disponible: false; motivo: string }
        | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Contratos', href: rutasContratos.index() }],
    },
});

const page = usePage();
const puedeGestionar = computed(() => page.props.auth?.puedeGestionar ?? false);
</script>

<template>
    <Head :title="`${contrato.propiedad} — ${contrato.inquilino.nombre}`" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <PageHeader
            :titulo="contrato.propiedad"
            :descripcion="`${contrato.inquilino.nombre} · ${contrato.desde} a ${contrato.hasta}`"
        >
            <template #acciones>
                <EstadoBadge
                    :estado="contrato.estado"
                    :label="contrato.estado_label"
                />
                <Button as-child size="sm" variant="ghost">
                    <Link :href="rutasPropiedades.show(contrato.propiedad_id)">
                        Ver propiedad
                    </Link>
                </Button>
                <Button
                    v-if="puedeGestionar"
                    as-child
                    size="sm"
                    variant="outline"
                >
                    <Link :href="rutasContratos.edit(contrato.id)">
                        <Pencil class="size-4" />
                        Editar
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 lg:grid-cols-3">
            <!-- Condiciones -->
            <section
                class="border-sidebar-border/70 dark:border-sidebar-border tarjeta rounded-xl border p-4"
            >
                <h2 class="text-sm font-medium">Condiciones</h2>
                <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-muted-foreground text-xs">
                            Alquiler actual
                        </dt>
                        <dd class="text-lg font-semibold tabular-nums">
                            {{ pesos(contrato.monto_actual) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground text-xs">
                            Valor inicial
                        </dt>
                        <dd class="tabular-nums">
                            {{ pesos(contrato.monto_base) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground text-xs">Depósito</dt>
                        <dd class="tabular-nums">
                            {{ pesos(contrato.deposito) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground text-xs">Vence el</dt>
                        <dd>día {{ contrato.dia_vencimiento }}</dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-muted-foreground text-xs">
                            Actualización
                        </dt>
                        <dd>
                            {{ contrato.indice }} cada
                            {{ contrato.frecuencia_meses }} meses
                        </dd>
                    </div>
                </dl>
            </section>

            <!-- Inquilino -->
            <section
                class="border-sidebar-border/70 dark:border-sidebar-border tarjeta rounded-xl border p-4"
            >
                <h2 class="text-sm font-medium">Inquilino</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div>
                        <dt class="text-muted-foreground text-xs">Nombre</dt>
                        <dd>{{ contrato.inquilino.nombre }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground text-xs">Documento</dt>
                        <dd>{{ contrato.inquilino.documento ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground text-xs">Contacto</dt>
                        <dd class="truncate">
                            {{
                                [
                                    contrato.inquilino.email,
                                    contrato.inquilino.telefono,
                                ]
                                    .filter(Boolean)
                                    .join(' · ') || '—'
                            }}
                        </dd>
                    </div>
                </dl>
            </section>

            <!-- Próximo ajuste: se calcula al vuelo -->
            <section
                class="border-sidebar-border/70 dark:border-sidebar-border tarjeta rounded-xl border p-4"
            >
                <h2 class="text-sm font-medium">Próximo ajuste</h2>

                <template v-if="proyeccion?.disponible">
                    <p class="mt-3 text-2xl font-semibold tabular-nums">
                        {{ pesos(proyeccion.monto_nuevo) }}
                    </p>
                    <p class="text-muted-foreground mt-1 text-sm">
                        {{ porcentaje(proyeccion.variacion) }} · vigencia
                        {{ proyeccion.vigencia }}
                    </p>
                    <p class="text-muted-foreground mt-1 text-xs">
                        Calculado con {{ contrato.indice }}
                        {{ proyeccion.ventana }}
                    </p>
                    <Button
                        v-if="puedeGestionar"
                        as-child
                        size="sm"
                        class="mt-3"
                        variant="outline"
                    >
                        <Link :href="rutasAjustes.index()">
                            <TrendingUp class="size-4" />
                            Ir a ajustes
                        </Link>
                    </Button>
                </template>

                <div
                    v-else-if="proyeccion && !proyeccion.disponible"
                    class="mt-3 flex items-start gap-2 text-sm"
                >
                    <Clock
                        class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400"
                    />
                    <p class="text-muted-foreground">
                        {{ proyeccion.motivo }}
                    </p>
                </div>

                <p v-else class="text-muted-foreground mt-3 text-sm">
                    Este contrato se ajusta a mano.
                </p>
            </section>
        </div>

        <!-- Historial de ajustes -->
        <section v-if="contrato.ajustes.length" class="space-y-3">
            <h2 class="text-sm font-medium">Historial de ajustes</h2>
            <div
                class="border-sidebar-border/70 dark:border-sidebar-border tarjeta overflow-x-auto rounded-xl border"
            >
                <table class="w-full text-sm">
                    <thead class="text-muted-foreground border-b text-left">
                        <tr>
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
                        <tr v-for="a in contrato.ajustes" :key="a.id">
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

        <!-- Cargos -->
        <section v-if="contrato.cargos.length" class="space-y-3">
            <h2 class="text-sm font-medium">Cargos emitidos</h2>
            <div
                class="border-sidebar-border/70 dark:border-sidebar-border tarjeta overflow-x-auto rounded-xl border"
            >
                <table class="w-full text-sm">
                    <thead class="text-muted-foreground border-b text-left">
                        <tr>
                            <th class="px-4 py-2 font-medium">Período</th>
                            <th class="px-4 py-2 font-medium">Vencimiento</th>
                            <th class="px-4 py-2 text-right font-medium">
                                Monto
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Pagado
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Saldo
                            </th>
                            <th class="px-4 py-2 font-medium">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="c in contrato.cargos" :key="c.id">
                            <td class="px-4 py-2 first-letter:uppercase">
                                {{ c.periodo }}
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                {{ c.vencimiento }}
                            </td>
                            <td
                                class="px-4 py-2 text-right whitespace-nowrap tabular-nums"
                            >
                                {{ pesos(c.monto) }}
                            </td>
                            <td
                                class="px-4 py-2 text-right whitespace-nowrap tabular-nums"
                            >
                                {{ pesos(c.pagado) }}
                            </td>
                            <td
                                class="px-4 py-2 text-right whitespace-nowrap tabular-nums"
                            >
                                {{ pesos(c.saldo) }}
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
        </section>
    </div>
</template>

<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { RefreshCw } from '@lucide/vue';
import { ref } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { numero, porcentaje } from '@/lib/formato';
import rutasIndices from '@/routes/indices';

defineProps<{
    series: Array<{
        fuente: string;
        nombre: string;
        esMensual: boolean;
        ultimaFecha: string | null;
        total: number;
        valores: Array<{
            fecha: string;
            valor: number;
            variacion: number | null;
        }>;
    }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Índices', href: rutasIndices.index() }],
    },
});

const sincronizando = ref<string | null>(null);

function sincronizar(fuente?: string) {
    sincronizando.value = fuente ?? 'todos';
    router.post(
        rutasIndices.sincronizar().url,
        fuente ? { fuente } : {},
        {
            preserveScroll: true,
            onFinish: () => (sincronizando.value = null),
        },
    );
}
</script>

<template>
    <Head title="Índices" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <PageHeader
            titulo="Índices"
            descripcion="Los valores oficiales con los que se calculan los ajustes. Se bajan solos todos los días."
        >
            <template #acciones>
                <Button
                    size="sm"
                    variant="outline"
                    :disabled="sincronizando !== null"
                    @click="sincronizar()"
                >
                    <RefreshCw
                        class="size-4"
                        :class="sincronizando === 'todos' && 'animate-spin'"
                    />
                    Sincronizar todo
                </Button>
            </template>
        </PageHeader>

        <section
            v-for="serie in series"
            :key="serie.fuente"
            class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border"
        >
            <header
                class="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3"
            >
                <div>
                    <h2 class="font-medium">{{ serie.nombre }}</h2>
                    <p class="text-muted-foreground text-xs">
                        {{ serie.total }} valores ·
                        {{
                            serie.ultimaFecha
                                ? `último: ${serie.ultimaFecha}`
                                : 'sin datos'
                        }}
                    </p>
                </div>
                <Button
                    size="sm"
                    variant="ghost"
                    :disabled="sincronizando !== null"
                    @click="sincronizar(serie.fuente)"
                >
                    <RefreshCw
                        class="size-4"
                        :class="sincronizando === serie.fuente && 'animate-spin'"
                    />
                    Actualizar
                </Button>
            </header>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-muted-foreground border-b text-left">
                        <tr>
                            <th class="px-4 py-2 font-medium">
                                {{ serie.esMensual ? 'Mes' : 'Fecha' }}
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Índice
                            </th>
                            <th
                                v-if="serie.esMensual"
                                class="px-4 py-2 text-right font-medium"
                            >
                                Variación
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="(v, i) in [...serie.valores].reverse()"
                            :key="i"
                        >
                            <td class="px-4 py-1.5 first-letter:uppercase">
                                {{ v.fecha }}
                            </td>
                            <td
                                class="px-4 py-1.5 text-right tabular-nums"
                            >
                                {{ numero(v.valor) }}
                            </td>
                            <td
                                v-if="serie.esMensual"
                                class="px-4 py-1.5 text-right tabular-nums"
                                :class="
                                    (v.variacion ?? 0) > 0
                                        ? 'text-rose-600 dark:text-rose-400'
                                        : 'text-emerald-600 dark:text-emerald-400'
                                "
                            >
                                {{
                                    v.variacion !== null
                                        ? porcentaje(v.variacion)
                                        : '—'
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>

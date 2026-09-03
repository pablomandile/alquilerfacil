<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, UserSquare } from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import rutasInquilinos from '@/routes/inquilinos';

defineProps<{
    inquilinos: Array<{
        id: number;
        nombre: string;
        documento: string | null;
        tipo_documento: string | null;
        email: string | null;
        telefono: string | null;
        propiedades: string[];
    }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Inquilinos', href: rutasInquilinos.index() }],
    },
});

const page = usePage();
const puedeGestionar = computed(() => page.props.auth?.puedeGestionar ?? false);
</script>

<template>
    <Head title="Inquilinos" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <PageHeader titulo="Inquilinos">
            <template #acciones>
                <Button v-if="puedeGestionar" as-child size="sm">
                    <Link :href="rutasInquilinos.create()">
                        <Plus class="size-4" />
                        Nuevo inquilino
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <EmptyState
            v-if="!inquilinos.length"
            titulo="No hay inquilinos cargados"
            :icono="UserSquare"
        >
            <Button v-if="puedeGestionar" as-child size="sm">
                <Link :href="rutasInquilinos.create()">Cargar el primero</Link>
            </Button>
        </EmptyState>

        <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <article
                v-for="i in inquilinos"
                :key="i.id"
                class="border-sidebar-border/70 dark:border-sidebar-border tarjeta rounded-xl border p-4"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ i.nombre }}</p>
                        <p class="text-muted-foreground text-xs">
                            {{ i.tipo_documento }} {{ i.documento }}
                        </p>
                    </div>
                    <Button
                        v-if="puedeGestionar"
                        as-child
                        size="icon"
                        variant="ghost"
                        class="size-8 shrink-0"
                    >
                        <Link :href="rutasInquilinos.edit(i.id)">
                            <Pencil class="size-4" />
                            <span class="sr-only">Editar</span>
                        </Link>
                    </Button>
                </div>

                <div class="text-muted-foreground mt-3 space-y-1 text-sm">
                    <p v-if="i.email" class="truncate">{{ i.email }}</p>
                    <p v-if="i.telefono">{{ i.telefono }}</p>
                </div>

                <p
                    v-if="i.propiedades.length"
                    class="text-muted-foreground mt-3 border-t pt-3 text-xs"
                >
                    {{ i.propiedades.join(' · ') }}
                </p>
            </article>
        </div>
    </div>
</template>

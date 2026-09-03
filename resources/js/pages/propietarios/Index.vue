<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, Users } from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import rutasPropietarios from '@/routes/propietarios';

defineProps<{
    propietarios: Array<{
        id: number;
        nombre: string;
        documento: string | null;
        tipo_documento: string | null;
        email: string | null;
        telefono: string | null;
        cbu: string | null;
        alias_cbu: string | null;
        propiedades: number;
        vinculo: 'con_cuenta' | 'pendiente' | 'sin_email';
    }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Propietarios', href: rutasPropietarios.index() },
        ],
    },
});

const page = usePage();
const esAdmin = computed(() => page.props.auth?.esAdmin ?? false);
</script>

<template>
    <Head title="Propietarios" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <PageHeader titulo="Propietarios">
            <template #acciones>
                <Button v-if="esAdmin" as-child size="sm">
                    <Link :href="rutasPropietarios.create()">
                        <Plus class="size-4" />
                        Nuevo propietario
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <EmptyState
            v-if="!propietarios.length"
            titulo="No hay propietarios cargados"
            descripcion="Cargá los dueños antes que las propiedades: después vas a asignarles su porcentaje en cada una."
            :icono="Users"
        >
            <Button v-if="esAdmin" as-child size="sm">
                <Link :href="rutasPropietarios.create()"
                    >Cargar el primero</Link
                >
            </Button>
        </EmptyState>

        <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <article
                v-for="o in propietarios"
                :key="o.id"
                class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ o.nombre }}</p>
                        <p class="text-muted-foreground text-xs">
                            {{ o.tipo_documento }} {{ o.documento }}
                        </p>
                    </div>
                    <Button
                        v-if="esAdmin"
                        as-child
                        size="icon"
                        variant="ghost"
                        class="size-8 shrink-0"
                    >
                        <Link :href="rutasPropietarios.edit(o.id)">
                            <Pencil class="size-4" />
                            <span class="sr-only">Editar</span>
                        </Link>
                    </Button>
                </div>

                <dl class="mt-3 space-y-1 text-sm">
                    <div v-if="o.email" class="truncate">
                        <dd class="text-muted-foreground">{{ o.email }}</dd>
                    </div>
                    <div v-if="o.telefono">
                        <dd class="text-muted-foreground">{{ o.telefono }}</dd>
                    </div>
                    <div v-if="o.alias_cbu || o.cbu" class="truncate">
                        <dt class="text-muted-foreground text-xs">
                            Para transferirle
                        </dt>
                        <dd class="font-mono text-xs">
                            {{ o.alias_cbu || o.cbu }}
                        </dd>
                    </div>
                </dl>

                <div
                    class="text-muted-foreground mt-3 flex items-center gap-2 border-t pt-3 text-xs"
                >
                    <span>
                        {{ o.propiedades }}
                        {{ o.propiedades === 1 ? 'propiedad' : 'propiedades' }}
                    </span>
                    <span
                        v-if="o.vinculo === 'con_cuenta'"
                        class="text-emerald-600 dark:text-emerald-400"
                    >
                        · entra a la app
                    </span>
                    <span v-else-if="o.vinculo === 'pendiente'">
                        · todavía no ingresó
                    </span>
                </div>
            </article>
        </div>
    </div>
</template>

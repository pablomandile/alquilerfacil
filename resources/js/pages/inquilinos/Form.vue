<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import rutasInquilinos from '@/routes/inquilinos';

const props = defineProps<{
    tiposDocumento: Array<{ value: string; label: string }>;
    inquilino?: {
        id: number;
        nombre: string;
        tipo_documento: string | null;
        documento: string | null;
        email: string | null;
        telefono: string | null;
        notas: string | null;
    };
}>();

const editando = computed(() => props.inquilino !== undefined);

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Inquilinos', href: rutasInquilinos.index() }],
    },
});

const form = useForm({
    nombre: props.inquilino?.nombre ?? '',
    tipo_documento: props.inquilino?.tipo_documento ?? 'dni',
    documento: props.inquilino?.documento ?? '',
    email: props.inquilino?.email ?? '',
    telefono: props.inquilino?.telefono ?? '',
    notas: props.inquilino?.notas ?? '',
});

function enviar() {
    if (editando.value && props.inquilino) {
        form.put(rutasInquilinos.update(props.inquilino.id).url);
        return;
    }

    form.post(rutasInquilinos.store().url);
}
</script>

<template>
    <Head :title="editando ? 'Editar inquilino' : 'Nuevo inquilino'" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <PageHeader
            :titulo="editando ? 'Editar inquilino' : 'Nuevo inquilino'"
        />

        <form class="grid max-w-xl gap-4" @submit.prevent="enviar">
            <div class="grid gap-2">
                <Label for="nombre">Nombre y apellido</Label>
                <Input id="nombre" v-model="form.nombre" required />
                <InputError :message="form.errors.nombre" />
            </div>

            <div class="grid gap-4 sm:grid-cols-[auto_1fr]">
                <div class="grid gap-2">
                    <Label for="tipo_documento">Tipo</Label>
                    <select
                        id="tipo_documento"
                        v-model="form.tipo_documento"
                        class="border-input bg-background h-9 rounded-md border px-3 text-sm"
                    >
                        <option
                            v-for="t in tiposDocumento"
                            :key="t.value"
                            :value="t.value"
                        >
                            {{ t.label }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-2">
                    <Label for="documento">Número</Label>
                    <Input id="documento" v-model="form.documento" />
                    <InputError :message="form.errors.documento" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input id="email" v-model="form.email" type="email" />
                    <InputError :message="form.errors.email" />
                </div>
                <div class="grid gap-2">
                    <Label for="telefono">Teléfono</Label>
                    <Input id="telefono" v-model="form.telefono" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="notas">Notas</Label>
                <textarea
                    id="notas"
                    v-model="form.notas"
                    rows="3"
                    class="border-input bg-background rounded-md border px-3 py-2 text-sm"
                />
            </div>

            <div class="flex gap-2">
                <Button type="submit" :disabled="form.processing">
                    {{ editando ? 'Guardar cambios' : 'Crear inquilino' }}
                </Button>
                <Button as-child variant="outline" type="button">
                    <Link :href="rutasInquilinos.index()">Cancelar</Link>
                </Button>
            </div>
        </form>
    </div>
</template>

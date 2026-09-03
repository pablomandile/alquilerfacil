<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Clock,
    Download,
    FileText,
    Pencil,
    Trash2,
    TrendingUp,
    Upload,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import EstadoBadge from '@/components/EstadoBadge.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { pesos, porcentaje, tamano } from '@/lib/formato';
import rutasAjustes from '@/routes/ajustes';
import rutasContratos from '@/routes/contratos';
import rutasDocumentos from '@/routes/documentos';
import rutasPropiedades from '@/routes/propiedades';

const props = defineProps<{
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
        documentos: Array<{
            id: number;
            tipo: string;
            tipo_label: string;
            nota: string | null;
            nombre: string;
            tamano: number;
            mime: string;
            subido_por: string | null;
            fecha: string | null;
        }>;
    };
    tiposDocumento: Array<{ value: string; label: string }>;
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

/* Documentos del contrato: se suben desde acá, sin pasar por el form de edición. */
const formDoc = useForm({
    tipo: 'contrato_firmado',
    nota: '',
    archivo: null as File | null,
});

const archivoInput = ref<HTMLInputElement | null>(null);

function elegirArchivo(evento: Event) {
    formDoc.archivo = (evento.target as HTMLInputElement).files?.[0] ?? null;
}

function subirDocumento() {
    formDoc.post(rutasDocumentos.store(props.contrato.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            formDoc.reset();
            if (archivoInput.value) archivoInput.value.value = '';
        },
    });
}

function borrarDocumento(id: number) {
    router.delete(rutasDocumentos.destroy(id).url, { preserveScroll: true });
}
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

        <!-- Documentos: el contrato firmado, la garantía, el pagaré, etc. -->
        <section class="space-y-3">
            <h2 class="text-sm font-medium">Documentos</h2>
            <div
                class="border-sidebar-border/70 dark:border-sidebar-border tarjeta divide-y overflow-hidden rounded-xl border"
            >
                <p
                    v-if="!contrato.documentos.length"
                    class="text-muted-foreground px-4 py-3 text-sm"
                >
                    Todavía no hay documentos cargados.
                </p>

                <div
                    v-for="d in contrato.documentos"
                    :key="d.id"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <FileText class="text-muted-foreground size-5 shrink-0" />
                    <div class="min-w-0 flex-1">
                        <p class="font-medium">{{ d.tipo_label }}</p>
                        <p class="text-muted-foreground truncate text-xs">
                            {{ d.nombre
                            }}<span v-if="d.nota"> · {{ d.nota }}</span>
                        </p>
                        <p class="text-muted-foreground text-xs">
                            {{ tamano(d.tamano) }}
                            <span v-if="d.subido_por">
                                · subido por {{ d.subido_por }}</span
                            >
                            <span v-if="d.fecha"> · {{ d.fecha }}</span>
                        </p>
                    </div>
                    <Button
                        as-child
                        size="icon"
                        variant="ghost"
                        class="size-8 shrink-0"
                    >
                        <a :href="rutasDocumentos.show(d.id).url">
                            <Download class="size-4" />
                            <span class="sr-only">Descargar</span>
                        </a>
                    </Button>
                    <Button
                        v-if="puedeGestionar"
                        size="icon"
                        variant="ghost"
                        class="size-8 shrink-0"
                        @click="borrarDocumento(d.id)"
                    >
                        <Trash2 class="size-4" />
                        <span class="sr-only">Eliminar</span>
                    </Button>
                </div>

                <form
                    v-if="puedeGestionar"
                    class="space-y-3 p-4"
                    @submit.prevent="subirDocumento"
                >
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="doc-tipo">Tipo</Label>
                            <select
                                id="doc-tipo"
                                v-model="formDoc.tipo"
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
                            <InputError :message="formDoc.errors.tipo" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="doc-nota">Aclaración (opcional)</Label>
                            <Input
                                id="doc-nota"
                                v-model="formDoc.nota"
                                placeholder="Ej: firmado por ambas partes"
                            />
                            <InputError :message="formDoc.errors.nota" />
                        </div>
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="doc-archivo">Archivo</Label>
                        <input
                            id="doc-archivo"
                            ref="archivoInput"
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx"
                            class="file:bg-secondary text-sm file:mr-3 file:rounded-md file:border-0 file:px-3 file:py-1.5 file:text-sm file:font-medium"
                            @change="elegirArchivo"
                        />
                        <p class="text-muted-foreground text-xs">
                            PDF, imágenes o Word. Hasta 10 MB.
                        </p>
                        <InputError :message="formDoc.errors.archivo" />
                    </div>

                    <Button
                        type="submit"
                        :disabled="formDoc.processing || !formDoc.archivo"
                    >
                        <Upload class="size-4" />
                        Subir documento
                    </Button>
                </form>
            </div>
        </section>
    </div>
</template>

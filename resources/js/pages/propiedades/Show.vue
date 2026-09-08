<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Download, Eye, FileText, Pencil, Trash2, Upload } from '@lucide/vue';
import { computed, ref } from 'vue';
import EstadoBadge from '@/components/EstadoBadge.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import SeguimientoAdministracion from '@/components/SeguimientoAdministracion.vue';
import VisorArchivo, {
    type ArchivoVisible,
} from '@/components/VisorArchivo.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { pesos, tamano } from '@/lib/formato';
import rutasContratos from '@/routes/contratos';
import rutasPropiedades from '@/routes/propiedades';
import rutasPropiedadDocumentos from '@/routes/propiedades/documentos';

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
        administracion: Array<{
            id: number;
            titulo: string;
            categoria: string;
            categoria_label: string;
            estado: string;
            estado_label: string;
            creado: string | null;
            entradas: Array<{
                id: number;
                fecha: string;
                fecha_iso: string;
                detalle: string;
                registrado_por: string | null;
                adjuntos: Array<{
                    id: number;
                    nombre: string;
                    tamano: number;
                    mime: string;
                }>;
            }>;
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
    tiposDocumento: Array<{ value: string; label: string }>;
    categoriasTemaAdmin: Array<{ value: string; label: string }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Propiedades', href: rutasPropiedades.index() }],
    },
});

const page = usePage();
const esAdmin = computed(() => page.props.auth?.esAdmin ?? false);
const puedeGestionar = computed(() => page.props.auth?.puedeGestionar ?? false);

/* Documentos de la propiedad: la escritura, el reglamento, planos, etc. Se
   suben desde acá, sin pasar por el form de edición. */
const formDoc = useForm({
    tipo: 'escritura',
    nota: '',
    archivo: null as File | null,
});

const archivoInput = ref<HTMLInputElement | null>(null);

function elegirArchivo(evento: Event) {
    formDoc.archivo = (evento.target as HTMLInputElement).files?.[0] ?? null;
}

function subirDocumento() {
    formDoc.post(rutasPropiedadDocumentos.store(props.propiedad.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            formDoc.reset();
            if (archivoInput.value) archivoInput.value.value = '';
        },
    });
}

function borrarDocumento(id: number) {
    router.delete(rutasPropiedadDocumentos.destroy(id).url, {
        preserveScroll: true,
    });
}

/* Visor a pantalla completa de los documentos. */
const visor = ref<ArchivoVisible | null>(null);

function verDocumento(d: { id: number; nombre: string; mime: string }) {
    visor.value = {
        nombre: d.nombre,
        mime: d.mime,
        verUrl: rutasPropiedadDocumentos.show(d.id).url,
        descargarUrl: rutasPropiedadDocumentos.show(d.id, {
            query: { descarga: 1 },
        }).url,
    };
}
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

        <!-- Seguimiento con la administración: reclamos, problemas, consultas -->
        <SeguimientoAdministracion
            :propiedad-id="propiedad.id"
            :temas="propiedad.administracion"
            :categorias="categoriasTemaAdmin"
            :puede-gestionar="puedeGestionar"
        />

        <!-- Documentos: la escritura, el reglamento de copropiedad, planos, etc. -->
        <section class="space-y-3">
            <h2 class="text-sm font-medium">Documentos</h2>
            <div
                class="border-sidebar-border/70 dark:border-sidebar-border tarjeta divide-y overflow-hidden rounded-xl border"
            >
                <p
                    v-if="!propiedad.documentos.length"
                    class="text-muted-foreground px-4 py-3 text-sm"
                >
                    Todavía no hay documentos cargados.
                </p>

                <div
                    v-for="d in propiedad.documentos"
                    :key="d.id"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <FileText class="text-muted-foreground size-5 shrink-0" />
                    <button
                        type="button"
                        class="group min-w-0 flex-1 cursor-pointer text-left"
                        @click="verDocumento(d)"
                    >
                        <p class="font-medium group-hover:underline">
                            {{ d.tipo_label }}
                        </p>
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
                    </button>
                    <Button
                        size="icon"
                        variant="ghost"
                        class="size-8 shrink-0"
                        @click="verDocumento(d)"
                    >
                        <Eye class="size-4" />
                        <span class="sr-only">Ver</span>
                    </Button>
                    <Button
                        as-child
                        size="icon"
                        variant="ghost"
                        class="size-8 shrink-0"
                    >
                        <a
                            :href="
                                rutasPropiedadDocumentos.show(d.id, {
                                    query: { descarga: 1 },
                                }).url
                            "
                        >
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
                                placeholder="Ej: escritura completa, 12 fojas"
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

    <VisorArchivo v-model:archivo="visor" />
</template>

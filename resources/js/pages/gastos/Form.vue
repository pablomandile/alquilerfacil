<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Download, Eye, FileText, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import VisorArchivo, {
    type ArchivoVisible,
} from '@/components/VisorArchivo.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { tamano } from '@/lib/formato';
import rutasGastos from '@/routes/gastos';
import rutasGastosDocumentos from '@/routes/gastos/documentos';

type Opcion = { value: string; label: string };

type Documento = {
    id: number;
    tipo: string;
    tipo_label: string;
    nombre: string;
    tamano: number;
    mime: string;
    fecha: string | null;
};

const props = defineProps<{
    propiedades: Array<{ id: number; alias: string }>;
    contratos: Array<{ id: number; property_id: number; label: string }>;
    tipos: Opcion[];
    categorias: Opcion[];
    aCargoDe: Opcion[];
    gasto?: {
        id: number;
        property_id: number;
        contract_id: number | null;
        tipo: string;
        categoria: string;
        descripcion: string | null;
        periodo: string;
        monto: string;
        vencimiento: string | null;
        a_cargo_de: string;
        pagado: boolean;
        fecha_pago: string | null;
        notas: string | null;
        documentos: Documento[];
    };
}>();

const editando = computed(() => props.gasto !== undefined);

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Gastos', href: rutasGastos.index() }],
    },
});

const form = useForm<{
    property_id: number | null;
    contract_id: number | null;
    tipo: string;
    categoria: string;
    descripcion: string;
    periodo: string;
    monto: string;
    vencimiento: string;
    a_cargo_de: string;
    pagado: boolean;
    fecha_pago: string;
    notas: string;
    factura: File | null;
    comprobante: File | null;
}>({
    property_id: props.gasto?.property_id ?? null,
    contract_id: props.gasto?.contract_id ?? null,
    tipo: props.gasto?.tipo ?? 'servicio',
    categoria: props.gasto?.categoria ?? 'luz',
    descripcion: props.gasto?.descripcion ?? '',
    periodo:
        props.gasto?.periodo ?? new Date().toISOString().slice(0, 8) + '01',
    monto: props.gasto?.monto ?? '',
    vencimiento: props.gasto?.vencimiento ?? '',
    a_cargo_de: props.gasto?.a_cargo_de ?? 'inquilino',
    pagado: props.gasto?.pagado ?? false,
    fecha_pago: props.gasto?.fecha_pago ?? '',
    notas: props.gasto?.notas ?? '',
    factura: null,
    comprobante: null,
});

function elegirArchivo(campo: 'factura' | 'comprobante', evento: Event) {
    form[campo] = (evento.target as HTMLInputElement).files?.[0] ?? null;
}

const visor = ref<ArchivoVisible | null>(null);

function verDocumento(d: Documento) {
    visor.value = {
        nombre: d.nombre,
        mime: d.mime,
        verUrl: rutasGastosDocumentos.show(d.id).url,
        descargarUrl: rutasGastosDocumentos.show(d.id, {
            query: { descarga: 1 },
        }).url,
    };
}

function borrarDocumento(id: number) {
    router.delete(rutasGastosDocumentos.destroy(id).url, {
        preserveScroll: true,
    });
}

/* Los gastos extraordinarios e impuestos normalmente los soportan los dueños;
   los servicios, el inquilino. Se sugiere al cambiar el tipo, sin imponerlo. */
watch(
    () => form.tipo,
    (tipo) => {
        if (editando.value) return;
        form.a_cargo_de = ['extraordinario', 'impuesto'].includes(tipo)
            ? 'propietarios'
            : 'inquilino';
    },
);

const contratosDeLaPropiedad = computed(() =>
    props.contratos.filter((c) => c.property_id === form.property_id),
);

function enviar() {
    if (editando.value && props.gasto) {
        // PUT con multipart no funciona (PHP no llena $_FILES): se manda POST
        // con _method spoofeado, que Inertia no hace solo.
        form.transform((datos) => ({ ...datos, _method: 'put' })).post(
            rutasGastos.update(props.gasto.id).url,
        );
        return;
    }

    form.post(rutasGastos.store().url);
}
</script>

<template>
    <Head :title="editando ? 'Editar gasto' : 'Nuevo gasto'" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <PageHeader :titulo="editando ? 'Editar gasto' : 'Nuevo gasto'" />

        <form class="grid max-w-2xl gap-6" @submit.prevent="enviar">
            <section
                class="border-sidebar-border/70 dark:border-sidebar-border tarjeta grid gap-4 rounded-xl border p-4 sm:grid-cols-2"
            >
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="property_id">Propiedad</Label>
                    <select
                        id="property_id"
                        v-model="form.property_id"
                        class="border-input bg-background h-9 rounded-md border px-3 text-sm"
                        required
                    >
                        <option :value="null" disabled>Elegí una</option>
                        <option
                            v-for="p in propiedades"
                            :key="p.id"
                            :value="p.id"
                        >
                            {{ p.alias }}
                        </option>
                    </select>
                    <InputError :message="form.errors.property_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="tipo">Tipo</Label>
                    <select
                        id="tipo"
                        v-model="form.tipo"
                        class="border-input bg-background h-9 rounded-md border px-3 text-sm"
                    >
                        <option
                            v-for="t in tipos"
                            :key="t.value"
                            :value="t.value"
                        >
                            {{ t.label }}
                        </option>
                    </select>
                </div>

                <div class="grid gap-2">
                    <Label for="categoria">Categoría</Label>
                    <select
                        id="categoria"
                        v-model="form.categoria"
                        class="border-input bg-background h-9 rounded-md border px-3 text-sm"
                    >
                        <option
                            v-for="c in categorias"
                            :key="c.value"
                            :value="c.value"
                        >
                            {{ c.label }}
                        </option>
                    </select>
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <Label for="descripcion">Descripción</Label>
                    <Input
                        id="descripcion"
                        v-model="form.descripcion"
                        placeholder="Edesur, cambio del termotanque…"
                    />
                </div>
            </section>

            <section
                class="border-sidebar-border/70 dark:border-sidebar-border tarjeta grid gap-4 rounded-xl border p-4 sm:grid-cols-2"
            >
                <div class="grid gap-2">
                    <Label for="monto">Monto</Label>
                    <Input
                        id="monto"
                        v-model="form.monto"
                        type="number"
                        step="0.01"
                        min="0"
                        class="tabular-nums"
                        required
                    />
                    <InputError :message="form.errors.monto" />
                </div>

                <div class="grid gap-2">
                    <Label for="periodo">Período</Label>
                    <Input
                        id="periodo"
                        v-model="form.periodo"
                        type="date"
                        required
                    />
                    <p class="text-muted-foreground text-xs">
                        El mes al que corresponde el gasto.
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="vencimiento">Vencimiento</Label>
                    <Input
                        id="vencimiento"
                        v-model="form.vencimiento"
                        type="date"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="a_cargo_de">A cargo de</Label>
                    <select
                        id="a_cargo_de"
                        v-model="form.a_cargo_de"
                        class="border-input bg-background h-9 rounded-md border px-3 text-sm"
                    >
                        <option
                            v-for="a in aCargoDe"
                            :key="a.value"
                            :value="a.value"
                        >
                            {{ a.label }}
                        </option>
                    </select>
                    <p
                        v-if="form.a_cargo_de === 'propietarios'"
                        class="text-xs text-emerald-600 dark:text-emerald-400"
                    >
                        Se va a repartir entre los dueños según su porcentaje.
                    </p>
                    <p
                        v-else-if="form.a_cargo_de === 'mitades'"
                        class="text-xs text-emerald-600 dark:text-emerald-400"
                    >
                        La mitad la paga el inquilino; la otra mitad se reparte
                        entre los dueños según su porcentaje.
                    </p>
                </div>

                <div
                    v-if="contratosDeLaPropiedad.length"
                    class="grid gap-2 sm:col-span-2"
                >
                    <Label for="contract_id">Contrato (opcional)</Label>
                    <select
                        id="contract_id"
                        v-model="form.contract_id"
                        class="border-input bg-background h-9 rounded-md border px-3 text-sm"
                    >
                        <option :value="null">Sin asociar</option>
                        <option
                            v-for="c in contratosDeLaPropiedad"
                            :key="c.id"
                            :value="c.id"
                        >
                            {{ c.label }}
                        </option>
                    </select>
                </div>
            </section>

            <section
                class="border-sidebar-border/70 dark:border-sidebar-border tarjeta grid gap-4 rounded-xl border p-4"
            >
                <div class="flex items-center gap-2">
                    <Checkbox id="pagado" v-model="form.pagado" />
                    <Label for="pagado" class="cursor-pointer">
                        Ya está pagado
                    </Label>
                </div>

                <div v-if="form.pagado" class="grid max-w-xs gap-2">
                    <Label for="fecha_pago">Fecha de pago</Label>
                    <Input
                        id="fecha_pago"
                        v-model="form.fecha_pago"
                        type="date"
                    />
                </div>
            </section>

            <!-- Comprobantes: la factura / expensa del período y el pago -->
            <section
                class="border-sidebar-border/70 dark:border-sidebar-border tarjeta grid gap-4 rounded-xl border p-4"
            >
                <h2 class="text-sm font-medium">Comprobantes</h2>

                <!-- Adjuntos ya cargados (sólo al editar) -->
                <ul
                    v-if="gasto?.documentos.length"
                    class="divide-y rounded-lg border"
                >
                    <li
                        v-for="d in gasto.documentos"
                        :key="d.id"
                        class="flex items-center gap-3 px-3 py-2"
                    >
                        <FileText
                            class="text-muted-foreground size-4 shrink-0"
                        />
                        <button
                            type="button"
                            class="min-w-0 flex-1 cursor-pointer text-left"
                            @click="verDocumento(d)"
                        >
                            <p class="text-sm font-medium">
                                {{ d.tipo_label }}
                            </p>
                            <p class="text-muted-foreground truncate text-xs">
                                {{ d.nombre }} · {{ tamano(d.tamano) }}
                                <span v-if="d.fecha"> · {{ d.fecha }}</span>
                            </p>
                        </button>
                        <button
                            type="button"
                            class="text-muted-foreground hover:text-foreground shrink-0 cursor-pointer"
                            @click="verDocumento(d)"
                        >
                            <Eye class="size-4" />
                            <span class="sr-only">Ver</span>
                        </button>
                        <a
                            :href="
                                rutasGastosDocumentos.show(d.id, {
                                    query: { descarga: 1 },
                                }).url
                            "
                            class="text-muted-foreground hover:text-foreground shrink-0"
                        >
                            <Download class="size-4" />
                            <span class="sr-only">Descargar</span>
                        </a>
                        <button
                            type="button"
                            class="text-muted-foreground hover:text-foreground shrink-0 cursor-pointer"
                            @click="borrarDocumento(d.id)"
                        >
                            <Trash2 class="size-4" />
                            <span class="sr-only">Eliminar</span>
                        </button>
                    </li>
                </ul>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="factura">Factura / expensa</Label>
                        <input
                            id="factura"
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx"
                            class="file:bg-secondary text-sm file:mr-3 file:rounded-md file:border-0 file:px-3 file:py-1.5 file:text-sm file:font-medium"
                            @change="elegirArchivo('factura', $event)"
                        />
                        <InputError :message="form.errors.factura" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="comprobante">Comprobante de pago</Label>
                        <input
                            id="comprobante"
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx"
                            class="file:bg-secondary text-sm file:mr-3 file:rounded-md file:border-0 file:px-3 file:py-1.5 file:text-sm file:font-medium"
                            @change="elegirArchivo('comprobante', $event)"
                        />
                        <InputError :message="form.errors.comprobante" />
                    </div>
                </div>
                <p class="text-muted-foreground text-xs">
                    PDF, imágenes o Word. Hasta 10 MB cada uno.
                </p>
            </section>

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
                    {{ editando ? 'Guardar cambios' : 'Cargar gasto' }}
                </Button>
                <Button as-child variant="outline" type="button">
                    <Link :href="rutasGastos.index()">Cancelar</Link>
                </Button>
            </div>
        </form>
    </div>

    <VisorArchivo v-model:archivo="visor" />
</template>

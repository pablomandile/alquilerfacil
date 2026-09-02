<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Building2,
    FileText,
    LayoutGrid,
    Receipt,
    TrendingUp,
    Users,
    UserSquare,
    Wallet,
    PieChart,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import rutasAjustes from '@/routes/ajustes';
import rutasCobranzas from '@/routes/cobranzas';
import rutasContratos from '@/routes/contratos';
import rutasGastos from '@/routes/gastos';
import rutasIndices from '@/routes/indices';
import rutasInquilinos from '@/routes/inquilinos';
import rutasLiquidaciones from '@/routes/liquidaciones';
import rutasPropiedades from '@/routes/propiedades';
import rutasPropietarios from '@/routes/propietarios';
import type { NavItem } from '@/types';

const page = usePage();
const esAdmin = computed(() => page.props.auth?.esAdmin ?? false);

const gestion: NavItem[] = [
    { title: 'Panel', href: dashboard(), icon: LayoutGrid },
    { title: 'Propiedades', href: rutasPropiedades.index(), icon: Building2 },
    { title: 'Contratos', href: rutasContratos.index(), icon: FileText },
    { title: 'Ajustes', href: rutasAjustes.index(), icon: TrendingUp },
];

const dinero: NavItem[] = [
    { title: 'Cobranzas', href: rutasCobranzas.index(), icon: Wallet },
    { title: 'Gastos', href: rutasGastos.index(), icon: Receipt },
    {
        title: 'Liquidaciones',
        href: rutasLiquidaciones.index(),
        icon: PieChart,
    },
];

// Los índices y las personas son datos de referencia: sólo los toca el admin.
const referencia = computed<NavItem[]>(() =>
    esAdmin.value
        ? [
              {
                  title: 'Propietarios',
                  href: rutasPropietarios.index(),
                  icon: Users,
              },
              {
                  title: 'Inquilinos',
                  href: rutasInquilinos.index(),
                  icon: UserSquare,
              },
              {
                  title: 'Índices',
                  href: rutasIndices.index(),
                  icon: TrendingUp,
              },
          ]
        : [
              {
                  title: 'Propietarios',
                  href: rutasPropietarios.index(),
                  icon: Users,
              },
          ],
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="gestion" titulo="Gestión" />
            <NavMain :items="dinero" titulo="Dinero" />
            <NavMain :items="referencia" titulo="Datos" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

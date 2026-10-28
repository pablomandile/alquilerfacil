<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import GoogleButton from '@/components/GoogleButton.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import PasskeyVerify from '@/components/PasskeyVerify.vue';

defineOptions({
    layout: {
        title: 'Entrá a tu cuenta',
        description: 'Ingresá tu email y tu contraseña para entrar',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

// Errores del login con Google: vuelven como redirect con withErrors(), así que
// están en las props de la página y no en el submit del formulario.
const page = usePage();
const oauthError = computed(() => page.props.errors?.oauth);

// Solo en la demo: los datos de acceso a la vista y un botón que entra directo.
const demo = computed(() => page.props.demo);
const entrando = ref(false);

function entrarALaDemo(): void {
    if (!demo.value) {
        return;
    }

    router.post(
        store().url,
        { email: demo.value.email, password: demo.value.password },
        {
            onStart: () => (entrando.value = true),
            onFinish: () => (entrando.value = false),
        },
    );
}
</script>

<template>
    <Head title="Iniciar sesión" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <div
        v-if="oauthError"
        class="text-destructive mb-4 text-center text-sm font-medium"
    >
        {{ oauthError }}
    </div>

    <section
        v-if="demo"
        class="border-primary/40 bg-primary/5 mb-4 space-y-3 rounded-lg border p-4"
        aria-labelledby="titulo-demo"
    >
        <h2 id="titulo-demo" class="font-semibold">Probá la demo</h2>
        <p class="text-muted-foreground text-sm">
            Propiedades, contratos, ajustes por índice y cobranzas inventados,
            con los IPC e ICL reales. Los datos se borran y vuelven a cargarse
            cada noche.
        </p>
        <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
            <dt class="text-muted-foreground">Email</dt>
            <dd class="break-all">{{ demo.email }}</dd>
            <dt class="text-muted-foreground">Contraseña</dt>
            <dd>{{ demo.password }}</dd>
        </dl>
        <Button
            type="button"
            class="w-full"
            :disabled="entrando"
            @click="entrarALaDemo"
        >
            <Spinner v-if="entrando" />
            Entrar a la demo
        </Button>
    </section>

    <PasskeyVerify />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">Email</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">Contraseña</Label>
                    <TextLink
                        v-if="canResetPassword && !demo"
                        :href="request()"
                        class="text-sm"
                        :tabindex="5"
                    >
                        ¿Olvidaste tu contraseña?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Contraseña"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>Mantener la sesión iniciada</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Iniciar sesión
            </Button>

            <template v-if="!demo">
                <div class="flex items-center gap-3 text-sm">
                    <span class="bg-border h-px flex-1"></span>
                    <span class="text-muted-foreground">o</span>
                    <span class="bg-border h-px flex-1"></span>
                </div>

                <GoogleButton :tabindex="6" />
            </template>
        </div>

        <div v-if="!demo" class="text-muted-foreground text-center text-sm">
            ¿No tenés cuenta?
            <TextLink :href="register()" :tabindex="7">Crear cuenta</TextLink>
        </div>
    </Form>
</template>

<?php

/*
 * Modo demo: la copia de alquilerfacil.pablomandile.com.ar es la demo del
 * portfolio. Con APP_DEMO=true hay una cuenta de administrador con propiedades,
 * contratos y cobranzas inventados que se resiembran todas las noches
 * (`demo:reset`), y nadie puede registrarse, entrar con Google ni cambiar la
 * cuenta. En la productiva queda apagado y nada de esto actúa.
 */
return [
    'enabled' => (bool) env('APP_DEMO', false),
    'email' => 'demo@pablomandile.com.ar',
    'password' => 'demo1234',
];

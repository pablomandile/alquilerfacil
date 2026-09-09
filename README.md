# Alquiler Fácil

Administración de propiedades en alquiler: contratos, actualización del alquiler
por índice oficial, gastos, cobranzas y reparto entre varios dueños.

## Funcionalidades

**Propiedades y contratos**

- Alta/baja de propiedades con tipo, dirección, ambientes, superficie, partida y
  estado (disponible / alquilada / en refacción).
- Varios dueños por propiedad, cada uno con su porcentaje (suma 100).
- Contratos con inquilino, vigencia, monto, depósito, día de vencimiento, índice
  y frecuencia de ajuste.
- Documentos adjuntos por propiedad (escritura, reglamento, planos, impuestos…) y
  por contrato (contrato firmado, garantía, pagaré, seguro de caución…). Van a un
  disco privado y se descargan detrás de la policy.

**Actualización del alquiler por índice**

- Índice **IPC** (INDEC) o **ICL** (BCRA) por contrato; los valores se bajan
  solos de las APIs oficiales.
- La app **calcula y propone**; aplicar el aumento es siempre decisión del
  usuario, que puede editar el monto antes de confirmar.
- El alquiler queda **siempre en pesos enteros**; opcionalmente, redondeado al
  centenar o al millar más cercano según el contrato.
- Rechazar una propuesta con motivo; corregir el importe del último ajuste ya
  aplicado (para dejarlo en un número más redondo).
- Contempla el desfasaje de publicación del INDEC (ver más abajo).

**Cobranzas**

- Emisión del cargo de alquiler del mes, manual o por scheduler, con el monto
  congelado al emitirlo.
- Pagos totales o parciales con medio de pago; estados pendiente / parcial /
  pagado / vencido.
- Borrar un cargo emitido para corregirlo y volver a emitirlo.

**Gastos**

- Servicios, expensas, impuestos y extraordinarios, a cargo del inquilino, de los
  propietarios, o **a medias (50 / 50)**.
- Adjuntar la factura / expensa del período y el comprobante de pago.
- Los que van a los dueños —o la mitad, si es compartido— se reparten por
  porcentaje.

**Reparto y liquidaciones**

- El reparto entre dueños se **persiste** (no se recalcula) y las partes suman el
  total exacto (ver más abajo).
- Liquidación mensual por dueño: su parte del alquiler cobrado menos su parte de
  los gastos a cargo de los propietarios.
- Ficha de la propiedad con el total facturado, cobrado y el neto.

**Aviso mensual al inquilino**

- Cuadro del mes (alquiler + gastos que vencen) y mensaje listo para copiar y
  pegar en WhatsApp.
- Estado enviado / pendiente del aviso por mes; volverlo a pendiente pide la
  contraseña, para no desmarcar por error.

**Seguimiento con la administración**

- Temas tratados con el consorcio (reclamos, problemas, consultas) con entradas
  fechadas, adjuntos y estado abierto / resuelto.

**Lo demás**

- Visor de imágenes y PDF a pantalla completa.
- Panel con lo facturado y cobrado del mes, los ajustes por revisar y los gastos
  por vencer.
- PWA instalable, con pantalla de "sin conexión".
- Ingreso con email y contraseña, con Google (OAuth) o con passkey; 2FA opcional.
- Interfaz en español rioplatense; modo claro y oscuro.
- Dos roles (ver "Roles" más abajo).

## Puesta en marcha

```bash
composer install
npm install
cp .env.example .env && php artisan key:generate

# Crear la base y cargar datos de ejemplo
mysql -u root -e "CREATE DATABASE alquilerfacil CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan migrate --seed

# Bajar el IPC y el ICL desde las APIs oficiales
php artisan indices:sincronizar

npm run dev
```

El seeder deja tres propiedades con contratos, gastos, cobranzas en varios
estados, ajustes (uno aplicado y otro pendiente), seguimiento con la
administración e índices cargados, más estos usuarios:

| Usuario                     | Contraseña | Rol                                               |
| --------------------------- | ---------- | ------------------------------------------------- |
| `pablo.mandile@gmail.com`   | `password` | Administrador                                     |
| `demo@alquilerfacil.com.ar` | `demo1234` | Administrador (para mostrar la app)               |
| `laura@example.com`         | `password` | Propietaria, co-administra dos de las propiedades |

## Cómo se actualiza el alquiler

La app **calcula y propone**; aplicar el aumento es siempre una decisión del
usuario, que además puede editar el monto antes de confirmarlo.

Cada contrato define su índice (**IPC** del INDEC o **ICL** del BCRA) y cada
cuántos meses ajusta. Cuando llega la fecha, `ajustes:proponer` calcula la
propuesta y aparece en la pantalla de Ajustes.

### El coeficiente sale de dividir dos números índice

No de encadenar las variaciones mensuales. Con los valores reales del INDEC para
el trimestre mayo–julio 2026:

| Método                                                    | Coeficiente    |
| --------------------------------------------------------- | -------------- |
| `IPC[jul] / IPC[abr]` = `12076,3937 / 11363,0904`         | **1,06277370** |
| `1,0215 × 1,0189 × 1,0211` (encadenando los % publicados) | 1,06276736     |

La diferencia es el redondeo que se acumula al encadenar porcentajes. Por eso
`index_values` guarda el **número índice** y no la variación.

### El desfasaje de publicación

El INDEC publica el IPC de un mes **a mediados del mes siguiente**. Un ajuste con
vigencia el 1 de octubre necesita el índice de septiembre, que recién sale
alrededor del 15 de octubre.

Cuando falta el índice que cierra la ventana, el cálculo devuelve
`IndiceNoDisponible` con la fecha estimada de publicación, en vez de calcular con
los datos que haya. La pantalla lo muestra como _"Falta el IPC de agosto de 2026,
que se publica alrededor del 15 de septiembre"_, y la propuesta aparece sola
cuando el índice se sincroniza.

### Fuentes de datos

| Índice       | Origen                 | Endpoint                                                             |
| ------------ | ---------------------- | -------------------------------------------------------------------- |
| IPC Nacional | INDEC vía datos.gob.ar | `apis.datos.gob.ar/series/api/series/?ids=148.3_INIVELNAL_DICI_M_26` |
| ICL          | BCRA                   | `api.bcra.gob.ar/estadisticas/v4.0/Monetarias/40`                    |

Ambas son públicas y sin autenticación. El **IPCBA** de la Ciudad quedó afuera a
propósito: sólo se publica en PDF y XLSX, no tiene API. Si hace falta, se carga a
mano con `fuente = manual`.

> El BCRA sirve su certificado sin la raíz de la cadena, así que la validación
> depende del bundle de CA del servidor. Para no depender de eso, el cliente HTTP
> usa el bundle de `composer/ca-bundle`, que viaja con el proyecto. La
> verificación TLS queda siempre activa.

## El reparto entre dueños

Cada propiedad tiene dueños con un porcentaje que debe sumar exactamente 100. El
alquiler de cada mes y los gastos a cargo de los propietarios se reparten con esa
proporción.

**El reparto se guarda, no se recalcula.** Los porcentajes cambian con el tiempo
(se vende una parte, se hereda); si se recalculara contra `property_owner`, una
liquidación del año pasado se re-repartiría con los porcentajes de hoy.

**Las partes siempre suman el total exacto.** Redondear cada parte por separado no
lo garantiza: con tres dueños al 33,33/33,33/33,34 % las partes redondeadas
pierden un centavo. El repartidor trunca a centavos y asigna el residuo al dueño
de mayor porcentaje, con desempate por `owner_id` para que sea determinístico.

## Roles

|                                           | Administrador | Propietario          |
| ----------------------------------------- | ------------- | -------------------- |
| Ver                                       | todo          | sólo sus propiedades |
| Gastos, pagos, contratos, aplicar ajustes | sí            | sí, sobre lo suyo    |
| Alta/baja de propiedades, dueños y %      | sí            | no                   |
| Índices, recálculo global de ajustes      | sí            | no                   |
| Ver a los otros dueños                    | sí            | no                   |

Un propietario **co-administra** las propiedades donde figura como dueño: sobre
ellas hace casi todo lo que hace el admin. La estructura (qué propiedades hay,
quién es dueño y con qué porcentaje) y lo global (índices) son sólo del admin.

Cómo entra un copropietario: se carga su ficha con su email y, cuando ingresa
—con contraseña o con Google—, se vincula sola su cuenta con la ficha (hook en
`Owner` + listener `VincularOwnerAlIngresar`). A partir de ahí ve su propiedad y
la co-administra.

La estructura pasa por el middleware `admin`; el resto de la escritura la
autorizan las policies (`ExpensePolicy`, `ContractPolicy`, …) contra
`User::puedeGestionar($property)`. Las de lectura filtran con el scope
`Property::visiblePara()`, y pedir algo ajeno devuelve **404 y no 403**,
para no confirmar que exista.

## Comandos

| Comando               | Cuándo corre | Qué hace                               |
| --------------------- | ------------ | -------------------------------------- |
| `indices:sincronizar` | diario 09:00 | Baja IPC e ICL. Idempotente            |
| `ajustes:proponer`    | diario 09:15 | Calcula los ajustes que están en fecha |
| `cargos:generar`      | día 1, 06:00 | Emite el alquiler del mes y lo reparte |

Los tres son idempotentes: volver a correrlos no duplica nada.

## Tests

```bash
php artisan test          # 166 tests
npm run check             # formato y lint
```

Los que importan: `CalculadorDeAjusteTest` (incluye el desfasaje de publicación),
`RepartoEntreDuenosTest` y `GastoCompartidoTest` (las partes suman el total
exacto, también con el gasto a medias), `AccesoDeDuenoTest` (un propietario
co-administra lo suyo y no toca lo ajeno; el vínculo por email), `CobranzasTest`
(pagos parciales) y los de adjuntos (`DocumentosDe*Test`, disco privado detrás de
la policy).

Las APIs externas se testean con `Http::fake()`; los tests no pegan a INDEC ni al
BCRA.

## Notas para el deploy

- **Se compila local** (`npm run build`) y se sube `public/build`: el hosting
  compartido no tiene Node.
- **`resources/js/pages` va en minúscula.** Linux distingue mayúsculas y Windows
  no, así que un `Pages` mezclado funciona en local y falla en producción con
  `Inertia page component does not exist`.
- **Los cron de hPanel no soportan `cd ... &&`**, así que hay que configurar el
  scheduler como espera su ejecutor o `indices:sincronizar` no corre nunca.

## Stack

Laravel 13 · PHP 8.4 · MySQL 8 · Inertia 3 · Vue 3 + TypeScript · Tailwind 4 ·
reka-ui

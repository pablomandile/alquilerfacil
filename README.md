# Alquiler Fácil

Administración de propiedades en alquiler: contratos, actualización del alquiler
por índice oficial, gastos, cobranzas y reparto entre varios dueños.

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

El seeder deja un administrador (`pablo.mandile@gmail.com` / `password`), una
propietaria con acceso de sólo lectura (`laura@example.com` / `password`) y tres
propiedades con contratos, gastos y cobranzas.

## Cómo se actualiza el alquiler

La app **calcula y propone**; aplicar el aumento es siempre una decisión del
usuario, que además puede editar el monto antes de confirmarlo.

Cada contrato define su índice (**IPC** del INDEC o **ICL** del BCRA) y cada
cuántos meses ajusta. Cuando llega la fecha, `ajustes:proponer` calcula la
propuesta y aparece en la pantalla de Ajustes.

### El coeficiente sale de dividir dos números índice

No de encadenar las variaciones mensuales. Con los valores reales del INDEC para
el trimestre mayo–julio 2026:

| Método | Coeficiente |
|---|---|
| `IPC[jul] / IPC[abr]` = `12076,3937 / 11363,0904` | **1,06277370** |
| `1,0215 × 1,0189 × 1,0211` (encadenando los % publicados) | 1,06276736 |

La diferencia es el redondeo que se acumula al encadenar porcentajes. Por eso
`index_values` guarda el **número índice** y no la variación.

### El desfasaje de publicación

El INDEC publica el IPC de un mes **a mediados del mes siguiente**. Un ajuste con
vigencia el 1 de octubre necesita el índice de septiembre, que recién sale
alrededor del 15 de octubre.

Cuando falta el índice que cierra la ventana, el cálculo devuelve
`IndiceNoDisponible` con la fecha estimada de publicación, en vez de calcular con
los datos que haya. La pantalla lo muestra como *"Falta el IPC de agosto de 2026,
que se publica alrededor del 15 de septiembre"*, y la propuesta aparece sola
cuando el índice se sincroniza.

### Fuentes de datos

| Índice | Origen | Endpoint |
|---|---|---|
| IPC Nacional | INDEC vía datos.gob.ar | `apis.datos.gob.ar/series/api/series/?ids=148.3_INIVELNAL_DICI_M_26` |
| ICL | BCRA | `api.bcra.gob.ar/estadisticas/v4.0/Monetarias/40` |

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

| | Administrador | Propietario |
|---|---|---|
| Ver | todo | sólo sus propiedades |
| Crear, editar, borrar | sí | no |
| Ver a los otros dueños | sí | no |

Las rutas de escritura pasan por el middleware `admin`. Las de lectura filtran con
el scope `Property::visiblePara()`, y pedir algo ajeno devuelve **404 y no 403**,
para no confirmar que exista.

## Comandos

| Comando | Cuándo corre | Qué hace |
|---|---|---|
| `indices:sincronizar` | diario 09:00 | Baja IPC e ICL. Idempotente |
| `ajustes:proponer` | diario 09:15 | Calcula los ajustes que están en fecha |
| `cargos:generar` | día 1, 06:00 | Emite el alquiler del mes y lo reparte |

Los tres son idempotentes: volver a correrlos no duplica nada.

## Tests

```bash
php artisan test          # 79 tests
npm run check             # formato y lint
```

Los que importan: `CalculadorDeAjusteTest` (incluye el desfasaje de publicación),
`RepartoEntreDuenosTest` (las partes suman el total exacto), `AccesoDeDuenoTest`
(un propietario no ve ni toca lo ajeno) y `CobranzasTest` (pagos parciales).

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

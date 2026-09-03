/**
 * Formateo de números para Argentina: separador de miles con punto, decimales
 * con coma y el signo $ adelante.
 */

const moneda = new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const monedaCorta = new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
});

const decimal = new Intl.NumberFormat('es-AR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const unDecimal = new Intl.NumberFormat('es-AR', {
    maximumFractionDigits: 1,
});

/** Los montos viajan como string desde PHP para no perder precisión. */
export function pesos(valor: string | number | null | undefined): string {
    if (valor === null || valor === undefined || valor === '') return '—';
    return moneda.format(Number(valor));
}

/** Sin centavos, para los números grandes de las tarjetas del panel. */
export function pesosRedondos(
    valor: string | number | null | undefined,
): string {
    if (valor === null || valor === undefined || valor === '') return '—';
    return monedaCorta.format(Number(valor));
}

export function numero(valor: string | number | null | undefined): string {
    if (valor === null || valor === undefined || valor === '') return '—';
    return decimal.format(Number(valor));
}

/** Porcentaje con signo, como se lee un ajuste: «+6,28 %». */
export function porcentaje(valor: number | string | null | undefined): string {
    if (valor === null || valor === undefined || valor === '') return '—';
    const n = Number(valor);
    return `${n > 0 ? '+' : ''}${decimal.format(n)} %`;
}

/** Tamaño de archivo legible: «840 KB», «2,3 MB». */
export function tamano(bytes: number | null | undefined): string {
    if (!bytes || bytes < 0) return '—';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${unDecimal.format(bytes / (1024 * 1024))} MB`;
}

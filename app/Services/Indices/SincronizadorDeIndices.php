<?php

namespace App\Services\Indices;

use App\Enums\Indice;
use App\Exceptions\FuenteDeIndiceException;
use App\Models\IndexValue;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Baja los valores de cada fuente y los guarda en `index_values`.
 *
 * Las fuentes sólo saben hablar con su API; toda la lógica de persistencia vive
 * acá y es la misma para todas.
 */
class SincronizadorDeIndices
{
    /**
     * Cuánto se retrocede desde el último valor guardado al sincronizar.
     *
     * No se pide sólo lo nuevo: el INDEC revisa la serie de vez en cuando, así que
     * se vuelven a traer los últimos períodos para que las correcciones entren.
     */
    private const SOLAPAMIENTO_MESES = 3;

    private const SOLAPAMIENTO_DIAS = 10;

    /** @param  iterable<FuenteDeIndice>  $fuentes */
    public function __construct(private readonly iterable $fuentes) {}

    /**
     * @return Collection<int, ResultadoSincronizacion>
     */
    public function sincronizar(?Indice $soloEsta = null, bool $completo = false): Collection
    {
        return collect($this->fuentes)
            ->filter(fn (FuenteDeIndice $f) => $soloEsta === null || $f->indice() === $soloEsta)
            ->map(fn (FuenteDeIndice $f) => $this->sincronizarFuente($f, $completo))
            ->values();
    }

    public function sincronizarFuente(FuenteDeIndice $fuente, bool $completo = false): ResultadoSincronizacion
    {
        $indice = $fuente->indice();

        try {
            $valores = $fuente->traer($completo ? null : $this->desdeCuando($indice));
        } catch (FuenteDeIndiceException $e) {
            return ResultadoSincronizacion::fallo($indice, $e->getMessage());
        }

        // Se cuenta antes y después en vez de usar el retorno del upsert: MySQL
        // reporta 2 filas afectadas por cada actualización, así que ese número no
        // sirve para decirle al usuario cuántos valores entraron.
        $antes = IndexValue::query()->de($indice)->count();

        $this->guardar($indice, $valores);

        if ($indice->esMensual()) {
            $this->recalcularVariaciones($indice);
        }

        return new ResultadoSincronizacion(
            indice: $indice,
            recibidos: $valores->count(),
            nuevos: IndexValue::query()->de($indice)->count() - $antes,
            ultimaFecha: IndexValue::ultimaFecha($indice),
        );
    }

    /**
     * Desde qué fecha pedir. Null (toda la historia) si todavía no hay nada.
     */
    private function desdeCuando(Indice $indice): ?CarbonInterface
    {
        $ultima = IndexValue::ultimaFecha($indice);

        if ($ultima === null) {
            return null;
        }

        return $indice->esMensual()
            ? $ultima->copy()->subMonths(self::SOLAPAMIENTO_MESES)
            : $ultima->copy()->subDays(self::SOLAPAMIENTO_DIAS);
    }

    /**
     * @param  Collection<int, array{fecha: CarbonInterface, valor: string}>  $valores
     */
    private function guardar(Indice $indice, Collection $valores): int
    {
        if ($valores->isEmpty()) {
            return 0;
        }

        $ahora = now();

        $filas = $valores->map(fn (array $v) => [
            'fuente' => $indice->value,
            'fecha' => $v['fecha']->toDateString(),
            'valor' => $v['valor'],
            'sincronizado_at' => $ahora,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ])->all();

        // El unique(fuente, fecha) hace que esto sea idempotente: correrlo dos
        // veces no duplica nada y las revisiones del INDEC pisan el valor viejo.
        return IndexValue::query()->upsert(
            $filas,
            ['fuente', 'fecha'],
            ['valor', 'sincronizado_at', 'updated_at']
        );
    }

    /**
     * Recalcula la variación mes a mes de toda la serie.
     *
     * Es informativa (para mostrar «+1,9 %» en pantalla); el cálculo de los
     * ajustes usa siempre el número índice. Se recalcula entera porque son pocos
     * cientos de filas y así una revisión del INDEC queda reflejada hacia adelante.
     */
    private function recalcularVariaciones(Indice $indice): void
    {
        $serie = IndexValue::query()->de($indice)->orderBy('fecha')->get();

        $anterior = null;

        foreach ($serie as $valor) {
            $variacion = $anterior !== null && bccomp((string) $anterior->valor, '0', 8) > 0
                ? bcsub(bcdiv((string) $valor->valor, (string) $anterior->valor, 10), '1', 6)
                : null;

            if ((string) $valor->variacion_mensual !== (string) $variacion) {
                $valor->update(['variacion_mensual' => $variacion]);
            }

            $anterior = $valor;
        }
    }
}

<?php

namespace App\Models;

use App\Enums\Indice;
use Carbon\CarbonInterface;
use Database\Factories\IndexValueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;

/**
 * Un valor de un índice oficial en una fecha. Para el IPC hay uno por mes (con
 * fecha el día 1); para el ICL, uno por día.
 *
 * @property int $id
 * @property Indice $fuente
 * @property CarbonInterface $fecha
 * @property numeric-string $valor
 * @property numeric-string|null $variacion_mensual
 * @property CarbonInterface|null $sincronizado_at
 */
#[Fillable(['fuente', 'fecha', 'valor', 'variacion_mensual', 'sincronizado_at'])]
class IndexValue extends Model
{
    /** @use HasFactory<IndexValueFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'fuente' => Indice::class,
            'fecha' => 'date',
            'valor' => 'decimal:8',
            'variacion_mensual' => 'decimal:6',
            'sincronizado_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<IndexValue>  $query
     * @return Builder<IndexValue>
     */
    public function scopeDe(Builder $query, Indice $fuente): Builder
    {
        return $query->where('fuente', $fuente);
    }

    /**
     * El valor vigente en una fecha: el de esa fecha exacta o, si no hay (fin de
     * semana o feriado en el caso del ICL), el último anterior disponible.
     */
    public static function vigenteEn(Indice $fuente, CarbonInterface $fecha): ?self
    {
        return static::query()
            ->de($fuente)
            ->whereDate('fecha', '<=', $fecha)
            ->orderByDesc('fecha')
            ->first();
    }

    /** La fecha más reciente con datos para una fuente. */
    public static function ultimaFecha(Indice $fuente): ?CarbonInterface
    {
        $fecha = static::query()->de($fuente)->max('fecha');

        return $fecha ? Date::parse($fecha) : null;
    }
}

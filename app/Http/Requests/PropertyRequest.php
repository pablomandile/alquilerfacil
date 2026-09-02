<?php

namespace App\Http\Requests;

use App\Enums\EstadoPropiedad;
use App\Enums\TipoPropiedad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PropertyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'alias' => ['required', 'string', 'max:255'],
            'tipo' => ['required', Rule::enum(TipoPropiedad::class)],
            'estado' => ['required', Rule::enum(EstadoPropiedad::class)],
            'calle' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'piso' => ['nullable', 'string', 'max:10'],
            'depto' => ['nullable', 'string', 'max:10'],
            'localidad' => ['nullable', 'string', 'max:255'],
            'provincia' => ['nullable', 'string', 'max:255'],
            'codigo_postal' => ['nullable', 'string', 'max:20'],
            'ambientes' => ['nullable', 'integer', 'min:0', 'max:100'],
            'superficie_m2' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'partida_inmobiliaria' => ['nullable', 'string', 'max:255'],
            'notas' => ['nullable', 'string', 'max:5000'],

            'propietarios' => ['array'],
            'propietarios.*.owner_id' => ['required', 'integer', 'exists:owners,id'],
            'propietarios.*.porcentaje' => ['required', 'numeric', 'gt:0', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $propietarios = $this->input('propietarios', []);

                if ($propietarios === []) {
                    return;
                }

                // Sin esta regla el reparto del alquiler quedaría incompleto o de
                // más, y el error recién aparecería al emitir un cargo.
                $suma = array_reduce(
                    $propietarios,
                    fn ($acc, $p) => bcadd($acc, (string) ($p['porcentaje'] ?? 0), 2),
                    '0'
                );

                if (bccomp($suma, '100', 2) !== 0) {
                    $validator->errors()->add(
                        'propietarios',
                        "Los porcentajes suman {$suma}%. Tienen que sumar exactamente 100%."
                    );
                }

                $ids = array_column($propietarios, 'owner_id');

                if (count($ids) !== count(array_unique($ids))) {
                    $validator->errors()->add(
                        'propietarios',
                        'Hay un propietario repetido. Cargá una sola fila por dueño.'
                    );
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'alias' => 'nombre',
            'superficie_m2' => 'superficie',
            'propietarios' => 'propietarios',
        ];
    }
}

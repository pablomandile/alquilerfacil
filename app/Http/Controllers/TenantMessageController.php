<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\TenantMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantMessageController extends Controller
{
    /**
     * Marca el aviso mensual al inquilino como enviado o vuelve a pendiente.
     * Pasar a pendiente pide la contraseña, para no desmarcar por error algo
     * que ya se mandó.
     */
    public function update(Request $request, Property $property): RedirectResponse
    {
        abort_unless($request->user()->puedeGestionar($property), 403);

        $datos = $request->validate([
            'estado' => ['required', Rule::in(['enviado', 'pendiente'])],
            'password' => ['exclude_unless:estado,pendiente', 'required', 'current_password'],
        ]);

        $periodo = now()->startOfMonth();

        if ($datos['estado'] === 'enviado') {
            TenantMessage::query()->updateOrCreate(
                ['property_id' => $property->id, 'periodo' => $periodo],
                ['enviado_at' => now(), 'enviado_por' => $request->user()->id],
            );

            return back()->with('success', 'Aviso marcado como enviado.');
        }

        TenantMessage::query()
            ->where('property_id', $property->id)
            ->delPeriodo($periodo)
            ->delete();

        return back()->with('success', 'Aviso marcado como pendiente.');
    }
}

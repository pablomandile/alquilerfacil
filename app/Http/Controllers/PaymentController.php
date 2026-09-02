<?php

namespace App\Http\Controllers;

use App\Enums\MedioPago;
use App\Models\Payment;
use App\Models\RentCharge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function store(Request $request, RentCharge $charge): RedirectResponse
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date'],
            'monto' => ['required', 'numeric', 'gt:0'],
            'medio' => ['required', Rule::enum(MedioPago::class)],
            'referencia' => ['nullable', 'string', 'max:255'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ]);

        // El estado del cargo se recalcula solo al guardar el pago.
        $charge->payments()->create($datos);

        return back()->with('success', 'Pago registrado.');
    }

    public function destroy(Payment $payment): RedirectResponse
    {
        $payment->delete();

        return back()->with('success', 'Pago eliminado.');
    }
}

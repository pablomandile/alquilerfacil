<?php

namespace App\Http\Controllers;

use App\Enums\MedioPago;
use App\Http\Controllers\Concerns\EntregaArchivoPrivado;
use App\Models\Payment;
use App\Models\RentCharge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    use EntregaArchivoPrivado;

    public function store(Request $request, RentCharge $charge): RedirectResponse
    {
        $this->authorize('create', [Payment::class, $charge]);

        $datos = $request->validate([
            'fecha' => ['required', 'date'],
            'monto' => ['required', 'numeric', 'gt:0'],
            'medio' => ['required', Rule::enum(MedioPago::class)],
            'referencia' => ['nullable', 'string', 'max:255'],
            'notas' => ['nullable', 'string', 'max:1000'],
            // Por extensión y no por MIME (los .docx dan falso negativo).
            'comprobante' => ['nullable', 'file', 'max:10240', 'extensions:pdf,jpg,jpeg,png,webp,doc,docx'],
        ]);

        // El estado del cargo se recalcula solo al guardar el pago.
        $pago = $charge->payments()->create(Arr::except($datos, ['comprobante']));

        $archivo = $request->file('comprobante');

        if ($archivo instanceof UploadedFile) {
            $path = $archivo->storeAs(
                "pagos/{$pago->id}",
                Str::ulid().'.'.strtolower($archivo->getClientOriginalExtension()),
                'local',
            );

            if ($path !== false) {
                $pago->update([
                    'comprobante_path' => $path,
                    'comprobante_nombre' => $archivo->getClientOriginalName(),
                    'comprobante_mime' => $archivo->getMimeType() ?? $archivo->getClientMimeType(),
                ]);
            }
        }

        return back()->with('success', 'Pago registrado.');
    }

    public function comprobante(Request $request, Payment $payment): StreamedResponse
    {
        $this->authorize('view', $payment);

        abort_if($payment->comprobante_path === null, 404);

        return $this->entregarArchivo(
            $request,
            $payment->comprobante_path,
            $payment->comprobante_nombre ?? basename($payment->comprobante_path),
        );
    }

    public function destroy(Payment $payment): RedirectResponse
    {
        $this->authorize('delete', $payment);

        // El archivo del comprobante lo borra el modelo al eliminarse.
        $payment->delete();

        return back()->with('success', 'Pago eliminado.');
    }
}

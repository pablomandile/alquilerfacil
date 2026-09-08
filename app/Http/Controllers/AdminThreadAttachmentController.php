<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EntregaArchivoPrivado;
use App\Models\AdminThreadAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminThreadAttachmentController extends Controller
{
    use EntregaArchivoPrivado;

    public function show(Request $request, AdminThreadAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $attachment);

        return $this->entregarArchivo($request, $attachment->path, $attachment->nombre_original);
    }

    public function destroy(AdminThreadAttachment $attachment): RedirectResponse
    {
        $this->authorize('delete', $attachment);

        $attachment->borrarConArchivo();

        return back()->with('success', 'Adjunto eliminado.');
    }
}

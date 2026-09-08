<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EntregaArchivoPrivado;
use App\Models\ExpenseDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseDocumentController extends Controller
{
    use EntregaArchivoPrivado;

    public function show(Request $request, ExpenseDocument $document): StreamedResponse
    {
        $this->authorize('view', $document);

        return $this->entregarArchivo($request, $document->path, $document->nombre_original);
    }

    public function destroy(ExpenseDocument $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        $document->borrarConArchivo();

        return back()->with('success', 'Documento eliminado.');
    }
}

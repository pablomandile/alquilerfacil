<?php

namespace App\Http\Controllers;

use App\Enums\TipoDocumentoContrato;
use App\Models\Contract;
use App\Models\ContractDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContractDocumentController extends Controller
{
    public function store(Request $request, Contract $contract): RedirectResponse
    {
        $this->authorize('create', [ContractDocument::class, $contract]);

        $datos = $request->validate([
            'tipo' => ['required', Rule::enum(TipoDocumentoContrato::class)],
            'nota' => ['nullable', 'string', 'max:255'],
            // Por extensión y no por MIME: el sniffeo de contenido da falsos
            // negativos con los .docx (los ve como zip). El archivo se guarda en
            // el disco privado con nombre generado y sólo se sirve como descarga.
            'archivo' => ['required', 'file', 'max:10240', 'extensions:pdf,jpg,jpeg,png,webp,doc,docx'],
        ]);

        $archivo = $request->file('archivo');

        if (! $archivo instanceof UploadedFile) {
            return back()->withErrors(['archivo' => 'No se pudo leer el archivo.']);
        }

        $path = $archivo->storeAs(
            "contratos/{$contract->id}",
            Str::ulid().'.'.strtolower($archivo->getClientOriginalExtension()),
            'local',
        );

        if ($path === false) {
            return back()->withErrors(['archivo' => 'No se pudo guardar el archivo. Probá de nuevo.']);
        }

        $contract->documents()->create([
            'tipo' => $datos['tipo'],
            'nota' => $datos['nota'] ?? null,
            'nombre_original' => $archivo->getClientOriginalName(),
            'path' => $path,
            'mime' => $archivo->getMimeType() ?? $archivo->getClientMimeType(),
            'tamano' => $archivo->getSize() ?: 0,
            'subido_por' => $request->user()?->id,
        ]);

        return back()->with('success', 'Documento subido.');
    }

    public function show(ContractDocument $document): StreamedResponse
    {
        $this->authorize('view', $document);

        return Storage::disk('local')->download($document->path, $document->nombre_original);
    }

    public function destroy(ContractDocument $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        $document->borrarConArchivo();

        return back()->with('success', 'Documento eliminado.');
    }
}

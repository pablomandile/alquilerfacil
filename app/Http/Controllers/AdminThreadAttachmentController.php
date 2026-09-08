<?php

namespace App\Http\Controllers;

use App\Models\AdminThreadAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminThreadAttachmentController extends Controller
{
    public function show(AdminThreadAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $attachment);

        return Storage::disk('local')->download($attachment->path, $attachment->nombre_original);
    }

    public function destroy(AdminThreadAttachment $attachment): RedirectResponse
    {
        $this->authorize('delete', $attachment);

        $attachment->borrarConArchivo();

        return back()->with('success', 'Adjunto eliminado.');
    }
}

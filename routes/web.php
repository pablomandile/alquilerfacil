<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\ContractDocumentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\IndiceController;
use App\Http\Controllers\LiquidacionController;
use App\Http\Controllers\OwnerController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\RentAdjustmentController;
use App\Http\Controllers\RentChargeController;
use App\Http\Controllers\TenantController;
use Illuminate\Support\Facades\Route;

/*
 * Es una app de gestión interna: no hay nada que mostrarle a un visitante. La
 * landing de ejemplo de Laravel se reemplaza por una redirección al panel (o al
 * login, si no hay sesión).
 */
Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))
    ->name('home');

/*
 * Login con Google (OAuth2). Fuera del grupo 'auth' porque son justamente las
 * rutas para entrar. El usuario se busca o se crea en el callback.
 */
Route::controller(GoogleController::class)->group(function () {
    Route::get('auth/google/redirect', 'redirect')->name('google.redirect');
    Route::get('auth/google/callback', 'callback')->name('google.callback');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    /*
    |----------------------------------------------------------------------
    | Escritura de estructura: sólo el administrador
    |----------------------------------------------------------------------
    |
    | Va primero para que /propiedades/create no lo capture el {property}
    | de la ruta de detalle. El alta/baja de propiedades, quién es dueño y
    | con qué %, los índices y el recálculo global de ajustes son del admin.
    */
    Route::middleware('admin')->group(function () {
        Route::resource('propiedades', PropertyController::class)
            ->parameters(['propiedades' => 'property'])
            ->except(['index', 'show']);

        Route::resource('propietarios', OwnerController::class)
            ->parameters(['propietarios' => 'owner'])
            ->except(['index', 'show']);

        Route::post('ajustes/recalcular', [RentAdjustmentController::class, 'recalcular'])
            ->name('ajustes.recalcular');

        Route::post('indices/sincronizar', [IndiceController::class, 'sincronizar'])
            ->name('indices.sincronizar');
    });

    /*
    |----------------------------------------------------------------------
    | Gestión: el admin y los copropietarios de la propiedad
    |----------------------------------------------------------------------
    |
    | La autorización fina la hacen las policies en cada controlador
    | (ExpensePolicy, ContractPolicy, ...): un propietario sólo puede sobre
    | las propiedades donde figura como dueño.
    */
    Route::resource('contratos', ContractController::class)
        ->parameters(['contratos' => 'contract'])
        ->except(['index', 'show']);

    // Documentos del contrato: el contrato firmado, la garantía, el pagaré, etc.
    Route::post('contratos/{contract}/documentos', [ContractDocumentController::class, 'store'])
        ->name('documentos.store');
    Route::get('documentos/{document}', [ContractDocumentController::class, 'show'])
        ->name('documentos.show');
    Route::delete('documentos/{document}', [ContractDocumentController::class, 'destroy'])
        ->name('documentos.destroy');

    Route::resource('gastos', ExpenseController::class)
        ->parameters(['gastos' => 'expense'])
        ->except(['index', 'show']);

    Route::resource('inquilinos', TenantController::class)
        ->parameters(['inquilinos' => 'tenant'])
        ->except(['index', 'show']);

    // Ajustes: la app propone, el dueño decide.
    Route::post('ajustes/{adjustment}/aplicar', [RentAdjustmentController::class, 'aplicar'])
        ->name('ajustes.aplicar');
    Route::post('ajustes/{adjustment}/rechazar', [RentAdjustmentController::class, 'rechazar'])
        ->name('ajustes.rechazar');

    // Cobranzas
    Route::post('cobranzas/generar', [RentChargeController::class, 'generar'])
        ->name('cobranzas.generar');
    Route::delete('cobranzas/{charge}', [RentChargeController::class, 'destroy'])
        ->name('cobranzas.destroy');
    Route::post('cobranzas/{charge}/pagos', [PaymentController::class, 'store'])
        ->name('pagos.store');
    Route::delete('pagos/{payment}', [PaymentController::class, 'destroy'])
        ->name('pagos.destroy');

    /*
    |----------------------------------------------------------------------
    | Lectura: el administrador ve todo, cada propietario sólo lo suyo
    |----------------------------------------------------------------------
    */
    Route::get('propiedades', [PropertyController::class, 'index'])->name('propiedades.index');
    Route::get('propiedades/{property}', [PropertyController::class, 'show'])
        ->whereNumber('property')->name('propiedades.show');

    Route::get('propietarios', [OwnerController::class, 'index'])->name('propietarios.index');
    Route::get('inquilinos', [TenantController::class, 'index'])->name('inquilinos.index');

    Route::get('contratos', [ContractController::class, 'index'])->name('contratos.index');
    Route::get('contratos/{contract}', [ContractController::class, 'show'])
        ->whereNumber('contract')->name('contratos.show');

    Route::get('gastos', [ExpenseController::class, 'index'])->name('gastos.index');
    Route::get('ajustes', [RentAdjustmentController::class, 'index'])->name('ajustes.index');
    Route::get('cobranzas', [RentChargeController::class, 'index'])->name('cobranzas.index');
    Route::get('liquidaciones', [LiquidacionController::class, 'index'])->name('liquidaciones.index');
    Route::get('indices', [IndiceController::class, 'index'])->name('indices.index');
});

require __DIR__.'/settings.php';

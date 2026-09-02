<?php

use App\Http\Controllers\ContractController;
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

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    /*
    |----------------------------------------------------------------------
    | Escritura: sólo el administrador
    |----------------------------------------------------------------------
    |
    | Van primero para que /propiedades/create no lo capture el {property}
    | de la ruta de detalle.
    */
    Route::middleware('admin')->group(function () {
        Route::resource('propiedades', PropertyController::class)
            ->parameters(['propiedades' => 'property'])
            ->except(['index', 'show']);

        Route::resource('propietarios', OwnerController::class)
            ->parameters(['propietarios' => 'owner'])
            ->except(['index', 'show']);

        Route::resource('inquilinos', TenantController::class)
            ->parameters(['inquilinos' => 'tenant'])
            ->except(['index', 'show']);

        Route::resource('contratos', ContractController::class)
            ->parameters(['contratos' => 'contract'])
            ->except(['index', 'show']);

        Route::resource('gastos', ExpenseController::class)
            ->parameters(['gastos' => 'expense'])
            ->except(['index', 'show']);

        // Ajustes: la app propone, el usuario decide.
        Route::post('ajustes/recalcular', [RentAdjustmentController::class, 'recalcular'])
            ->name('ajustes.recalcular');
        Route::post('ajustes/{adjustment}/aplicar', [RentAdjustmentController::class, 'aplicar'])
            ->name('ajustes.aplicar');
        Route::post('ajustes/{adjustment}/rechazar', [RentAdjustmentController::class, 'rechazar'])
            ->name('ajustes.rechazar');

        // Cobranzas
        Route::post('cobranzas/generar', [RentChargeController::class, 'generar'])
            ->name('cobranzas.generar');
        Route::post('cobranzas/{charge}/pagos', [PaymentController::class, 'store'])
            ->name('pagos.store');
        Route::delete('pagos/{payment}', [PaymentController::class, 'destroy'])
            ->name('pagos.destroy');

        // Índices
        Route::post('indices/sincronizar', [IndiceController::class, 'sincronizar'])
            ->name('indices.sincronizar');
    });

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

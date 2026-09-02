<?php

use App\Enums\EstadoCargo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rent_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();

            $table->date('periodo'); // Día 1 del mes que se cobra

            // Congela el monto vigente de ese mes: si después se aplica un ajuste,
            // los cargos ya emitidos no cambian.
            $table->decimal('monto', 14, 2);

            $table->date('vencimiento');
            $table->string('estado')->default(EstadoCargo::Pendiente->value);
            $table->text('notas')->nullable();
            $table->timestamps();

            // Hace que la generación de cargos sea idempotente.
            $table->unique(['contract_id', 'periodo']);
            $table->index(['estado', 'vencimiento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rent_charges');
    }
};

<?php

use App\Enums\EstadoAjuste;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rent_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();

            $table->date('vigencia_desde');
            $table->decimal('monto_anterior', 14, 2);
            $table->decimal('monto_nuevo', 14, 2);
            $table->decimal('coeficiente', 16, 8);

            // Se guardan los valores del índice usados, no sólo el resultado: así el
            // ajuste queda auditable aunque el INDEC revise la serie después.
            $table->string('indice');
            $table->date('periodo_indice_desde');
            $table->date('periodo_indice_hasta');
            $table->decimal('valor_indice_desde', 20, 8);
            $table->decimal('valor_indice_hasta', 20, 8);
            $table->decimal('variacion_porcentual', 8, 4);

            $table->string('estado')->default(EstadoAjuste::Propuesto->value);
            $table->timestamp('aplicado_at')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index(['estado', 'vigencia_desde']);
            $table->unique(['contract_id', 'vigencia_desde']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rent_adjustments');
    }
};

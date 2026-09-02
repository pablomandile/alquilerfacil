<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reparto de un monto entre los dueños de una propiedad.
     *
     * Es polimórfica porque el negocio reparte dos cosas con la misma regla: el
     * alquiler (shareable = RentCharge) y los gastos a cargo de los propietarios
     * (shareable = Expense).
     *
     * El reparto se persiste en vez de calcularse al vuelo porque los porcentajes
     * de propiedad cambian con el tiempo (se vende una parte, se hereda). Si se
     * recalculara contra property_owner, un cargo de hace un año se re-repartiría
     * con los porcentajes de hoy y la historia quedaría mal.
     */
    public function up(): void
    {
        Schema::create('owner_shares', function (Blueprint $table) {
            $table->id();
            $table->morphs('shareable');
            $table->foreignId('owner_id')->constrained()->cascadeOnDelete();

            $table->decimal('porcentaje', 5, 2);
            $table->decimal('monto', 14, 2);

            $table->timestamps();

            $table->unique(['shareable_type', 'shareable_id', 'owner_id'], 'owner_shares_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_shares');
    }
};

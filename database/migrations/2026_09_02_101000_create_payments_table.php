<?php

use App\Enums\MedioPago;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // Varios pagos por cargo: soporta pagos parciales. El estado del cargo
            // se deriva de la suma de sus pagos.
            $table->foreignId('rent_charge_id')->constrained()->cascadeOnDelete();

            $table->date('fecha');
            $table->decimal('monto', 14, 2);
            $table->string('medio')->default(MedioPago::Transferencia->value);
            $table->string('referencia')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index('fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

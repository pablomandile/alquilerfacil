<?php

use App\Enums\EstadoContrato;
use App\Enums\Indice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();

            $table->date('fecha_inicio');
            $table->date('fecha_fin');

            $table->decimal('monto_base', 14, 2);

            // Desnormalizado a propósito: se recalcula al aplicar un ajuste.
            // Evita reconstruir la cadena de ajustes en cada listado.
            $table->decimal('monto_actual', 14, 2);

            $table->unsignedTinyInteger('dia_vencimiento')->default(10);
            $table->decimal('deposito', 14, 2)->nullable();

            // Actualización por índice
            $table->string('indice')->default(Indice::Ipc->value);
            $table->unsignedTinyInteger('frecuencia_meses')->default(3);
            $table->date('proximo_ajuste')->nullable();

            // Redondear el monto propuesto al múltiplo más cercano (0 = sin redondeo).
            $table->unsignedSmallInteger('redondeo')->default(0);

            $table->string('estado')->default(EstadoContrato::Activo->value);
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index(['estado', 'proximo_ajuste']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};

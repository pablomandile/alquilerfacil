<?php

use App\Enums\ACargoDe;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();

            // Nullable: un gasto puede caer en un período sin contrato activo
            // (propiedad vacía entre inquilinos).
            $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete();

            $table->string('tipo');      // App\Enums\TipoGasto
            $table->string('categoria'); // App\Enums\CategoriaGasto
            $table->string('descripcion')->nullable();

            $table->date('periodo'); // Mes al que corresponde el gasto
            $table->decimal('monto', 14, 2);
            $table->date('vencimiento')->nullable();

            $table->string('a_cargo_de')->default(ACargoDe::Inquilino->value);

            $table->boolean('pagado')->default(false);
            $table->date('fecha_pago')->nullable();

            $table->string('comprobante_path')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index(['property_id', 'periodo']);
            $table->index(['pagado', 'vencimiento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};

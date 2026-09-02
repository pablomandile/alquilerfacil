<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('index_values', function (Blueprint $table) {
            $table->id();

            $table->string('fuente'); // App\Enums\Indice

            // Para el IPC (mensual) es el día 1 del mes; para el ICL (diario) el día real.
            $table->date('fecha');

            // Número índice, no porcentaje: dividir dos índices da el coeficiente
            // exacto, mientras que encadenar porcentajes acumula error.
            $table->decimal('valor', 20, 8);

            // Informativa, para mostrar en pantalla. El cálculo usa `valor`.
            $table->decimal('variacion_mensual', 10, 6)->nullable();

            $table->timestamp('sincronizado_at')->nullable();
            $table->timestamps();

            $table->unique(['fuente', 'fecha']);
            $table->index(['fuente', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('index_values');
    }
};

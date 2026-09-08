<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Marca de que el aviso mensual (alquiler + gastos) ya se le mandó al
        // inquilino. Existe una fila sólo cuando está enviado; sin fila =
        // pendiente.
        Schema::create('tenant_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->date('periodo'); // mes (día 1)
            $table->timestamp('enviado_at');
            $table->foreignId('enviado_por')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['property_id', 'periodo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_messages');
    }
};

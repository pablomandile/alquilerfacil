<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();

            $table->string('tipo'); // App\Enums\TipoDocumentoGasto
            $table->string('nombre_original');
            $table->string('path'); // ruta en el disco privado 'local'
            $table->string('mime');
            $table->unsignedInteger('tamano'); // bytes

            $table->foreignId('subido_por')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('expense_id');
        });

        // `comprobante_path` venía del esquema inicial y nunca se cableó: lo
        // reemplaza esta tabla.
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('comprobante_path');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('comprobante_path')->nullable();
        });

        Schema::dropIfExists('expense_documents');
    }
};

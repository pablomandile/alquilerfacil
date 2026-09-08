<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();

            $table->string('tipo'); // App\Enums\TipoDocumentoPropiedad
            $table->string('nota')->nullable();

            // El nombre con el que se subió: se usa para mostrarlo y para el
            // nombre del archivo al descargarlo.
            $table->string('nombre_original');
            $table->string('path'); // ruta en el disco privado 'local'
            $table->string('mime');
            $table->unsignedInteger('tamano'); // bytes

            $table->foreignId('subido_por')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('property_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_documents');
    }
};

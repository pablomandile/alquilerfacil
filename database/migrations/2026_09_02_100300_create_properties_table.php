<?php

use App\Enums\EstadoPropiedad;
use App\Enums\TipoPropiedad;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('alias'); // Ej. "Cabildo 2300 4°B"
            $table->string('tipo')->default(TipoPropiedad::Departamento->value);
            $table->string('estado')->default(EstadoPropiedad::Disponible->value);

            $table->string('calle')->nullable();
            $table->string('numero')->nullable();
            $table->string('piso')->nullable();
            $table->string('depto')->nullable();
            $table->string('localidad')->nullable();
            $table->string('provincia')->nullable();
            $table->string('codigo_postal')->nullable();

            $table->unsignedSmallInteger('ambientes')->nullable();
            $table->decimal('superficie_m2', 8, 2)->nullable();
            $table->string('partida_inmobiliaria')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};

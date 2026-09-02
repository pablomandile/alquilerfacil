<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owners', function (Blueprint $table) {
            $table->id();

            // Nullable a propósito: se puede cargar un copropietario que no usa la app.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('nombre');
            $table->string('tipo_documento')->nullable();
            $table->string('documento')->nullable();
            $table->string('email')->nullable();
            $table->string('telefono')->nullable();
            $table->string('cbu')->nullable();
            $table->string('alias_cbu')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owners');
    }
};

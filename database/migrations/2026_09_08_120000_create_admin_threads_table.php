<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un tema tratado con la administración del inmueble: un reclamo, un
        // problema informado, una consulta. Se le hace seguimiento con entradas
        // fechadas, cada una con sus adjuntos.
        Schema::create('admin_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();

            $table->string('titulo');
            $table->string('categoria'); // App\Enums\CategoriaTemaAdmin
            $table->string('estado')->default('abierto'); // App\Enums\EstadoTemaAdmin
            $table->timestamp('resuelto_at')->nullable();

            $table->timestamps();

            $table->index('property_id');
        });

        Schema::create('admin_thread_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_thread_id')->constrained()->cascadeOnDelete();

            $table->date('fecha');
            $table->text('detalle');

            $table->foreignId('registrado_por')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('admin_thread_id');
        });

        Schema::create('admin_thread_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_thread_entry_id')->constrained()->cascadeOnDelete();

            $table->string('nombre_original');
            $table->string('path'); // ruta en el disco privado 'local'
            $table->string('mime');
            $table->unsignedInteger('tamano'); // bytes

            $table->foreignId('subido_por')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('admin_thread_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_thread_attachments');
        Schema::dropIfExists('admin_thread_entries');
        Schema::dropIfExists('admin_threads');
    }
};

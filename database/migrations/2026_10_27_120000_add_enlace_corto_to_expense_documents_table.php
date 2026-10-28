<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El enlace acortado con Zep que va en el aviso al inquilino. Se guarda
        // para no crear uno nuevo en Zep cada vez que se abre la página.
        Schema::table('expense_documents', function (Blueprint $table) {
            $table->string('enlace_corto')->nullable()->after('tamano');
            $table->timestamp('enlace_corto_vence_at')->nullable()->after('enlace_corto');
        });
    }

    public function down(): void
    {
        Schema::table('expense_documents', function (Blueprint $table) {
            $table->dropColumn(['enlace_corto', 'enlace_corto_vence_at']);
        });
    }
};

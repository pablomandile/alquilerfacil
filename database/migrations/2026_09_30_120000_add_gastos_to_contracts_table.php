<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Qué gastos incluye el contrato y quién paga cada uno: sólo sirve para
        // precompletar la carga de gastos, no se calcula nada con esto.
        Schema::table('contracts', function (Blueprint $table) {
            $table->json('gastos')->nullable()->after('redondeo');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('gastos');
        });
    }
};

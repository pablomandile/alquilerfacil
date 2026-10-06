<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un comprobante opcional por pago (la captura de la transferencia, el
        // recibo). Vive en el disco privado `local`.
        Schema::table('payments', function (Blueprint $table) {
            $table->string('comprobante_path')->nullable()->after('referencia');
            $table->string('comprobante_nombre')->nullable()->after('comprobante_path');
            $table->string('comprobante_mime')->nullable()->after('comprobante_nombre');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['comprobante_path', 'comprobante_nombre', 'comprobante_mime']);
        });
    }
};

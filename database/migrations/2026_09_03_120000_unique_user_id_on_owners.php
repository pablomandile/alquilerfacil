<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una cuenta se vincula a lo sumo a una ficha de propietario. El vínculo se
     * arma solo por email (hook en Owner + listener al ingresar), así que hace
     * falta la garantía en la base. MySQL admite varios NULL en un índice único.
     */
    public function up(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
        });
    }
};

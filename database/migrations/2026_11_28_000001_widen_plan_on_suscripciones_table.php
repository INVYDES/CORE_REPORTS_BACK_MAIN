<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('suscripciones')) {
            return;
        }

        Schema::table('suscripciones', function (Blueprint $table) {
            $table->string('plan', 64)->default('trial')->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('suscripciones')) {
            return;
        }

        Schema::table('suscripciones', function (Blueprint $table) {
            $table->enum('plan', ['trial', 'mensual', 'anual', 'empresarial'])->default('trial')->change();
        });
    }
};

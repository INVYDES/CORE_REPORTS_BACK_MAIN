<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MaterialController ya valida y filtra por stock_minimo, pero la
        // columna nunca fue creada en las migraciones.
        if (! Schema::hasColumn('materiales_catalogo', 'stock_minimo')) {
            Schema::table('materiales_catalogo', function (Blueprint $table) {
                $table->decimal('stock_minimo', 10, 2)->default(0)->after('stock_actual');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('materiales_catalogo', 'stock_minimo')) {
            Schema::table('materiales_catalogo', function (Blueprint $table) {
                $table->dropColumn('stock_minimo');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Logotipo comercial de la empresa (ruta relativa en el disco de uploads o URL absoluta)
        if (! Schema::hasColumn('dependencias', 'logo')) {
            Schema::table('dependencias', function (Blueprint $table) {
                $table->string('logo', 255)->nullable()->after('datos_facturacion');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // La columna logo se conserva para no perder el logotipo cargado
    }
};

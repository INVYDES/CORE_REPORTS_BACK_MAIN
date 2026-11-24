<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reporte_evidencias', function (Blueprint $table): void {
            $table->string('ruta')->nullable()->after('url');
            $table->string('disco', 50)->nullable()->after('ruta');
        });

        Schema::table('dependencias', function (Blueprint $table): void {
            $table->string('logo_ruta')->nullable()->after('logo');
            $table->string('logo_disco', 50)->nullable()->after('logo_ruta');
        });
    }

    public function down(): void
    {
        Schema::table('reporte_evidencias', function (Blueprint $table): void {
            $table->dropColumn(['ruta', 'disco']);
        });

        Schema::table('dependencias', function (Blueprint $table): void {
            $table->dropColumn(['logo_ruta', 'logo_disco']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('dependencias', 'es_plataforma')) {
            Schema::table('dependencias', function (Blueprint $table) {
                $table->boolean('es_plataforma')->default(false)->after('activa');
            });
        }

        if (! Schema::hasTable('pagos_licencia')) {
            Schema::create('pagos_licencia', function (Blueprint $table) {
                $table->id();
                $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();
                $table->foreignId('licencia_id')->nullable()->constrained('licencias')->nullOnDelete();
                $table->string('preferencia_id', 150)->nullable()->index();
                $table->string('payment_id', 150)->nullable()->unique();
                $table->string('external_reference', 150)->nullable()->index();
                $table->string('estado', 30)->default('pendiente')->index();
                $table->string('metodo', 30)->default('mercadopago');
                $table->decimal('monto', 10, 2)->default(0);
                $table->string('moneda', 3)->default('MXN');
                $table->timestamp('pagado_en')->nullable();
                $table->timestamp('procesado_en')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_licencia');

        if (Schema::hasColumn('dependencias', 'es_plataforma')) {
            Schema::table('dependencias', function (Blueprint $table) {
                $table->dropColumn('es_plataforma');
            });
        }
    }
};

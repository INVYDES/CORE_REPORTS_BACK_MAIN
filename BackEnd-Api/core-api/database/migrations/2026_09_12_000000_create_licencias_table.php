<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('licencias')) {
            Schema::create('licencias', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 150);
                $table->string('codigo', 50)->unique();
                $table->enum('tipo', ['trial', 'mensual', 'anual', 'empresarial'])->default('mensual');
                $table->integer('max_usuarios')->default(10);
                $table->integer('max_reportes_mensuales')->default(100);
                $table->decimal('precio', 10, 2)->default(0);
                $table->decimal('precio_anual', 10, 2)->nullable();
                $table->integer('dias_prueba')->default(7);
                $table->text('descripcion')->nullable();
                $table->boolean('activo')->default(true);
                $table->string('paypal_plan_id', 100)->nullable();
                $table->string('mercadopago_plan_id', 100)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Add licencia_id to suscripciones if not exists
        if (Schema::hasTable('suscripciones') && ! Schema::hasColumn('suscripciones', 'licencia_id')) {
            Schema::table('suscripciones', function (Blueprint $table) {
                $table->foreignId('licencia_id')->nullable()->after('dependencia_id')->constrained('licencias')->nullOnDelete();
            });
        }

        // Add mercadopago fields to suscripciones if not exists
        if (! Schema::hasColumn('suscripciones', 'mercadopago_payment_id')) {
            Schema::table('suscripciones', function (Blueprint $table) {
                $table->string('mercadopago_payment_id', 100)->nullable()->after('proveedor_suscripcion_id');
            });
        }
        if (! Schema::hasColumn('suscripciones', 'mercadopago_preference_id')) {
            Schema::table('suscripciones', function (Blueprint $table) {
                $table->string('mercadopago_preference_id', 100)->nullable()->after('mercadopago_payment_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('licencias')) {
            Schema::dropIfExists('licencias');
        }
        if (Schema::hasColumn('suscripciones', 'licencia_id')) {
            Schema::table('suscripciones', function (Blueprint $table) {
                $table->dropForeign(['licencia_id']);
                $table->dropColumn('licencia_id');
            });
        }
        if (Schema::hasColumn('suscripciones', 'mercadopago_payment_id')) {
            Schema::table('suscripciones', function (Blueprint $table) {
                $table->dropColumn('mercadopago_payment_id');
            });
        }
        if (Schema::hasColumn('suscripciones', 'mercadopago_preference_id')) {
            Schema::table('suscripciones', function (Blueprint $table) {
                $table->dropColumn('mercadopago_preference_id');
            });
        }
    }
};

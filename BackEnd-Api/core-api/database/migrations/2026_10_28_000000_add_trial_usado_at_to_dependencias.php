<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('dependencias', 'trial_usado_at')) {
            Schema::table('dependencias', function (Blueprint $table) {
                // Marca el momento en que la dependencia consumió su periodo de
                // prueba. Es independiente de la suscripción vigente, así que
                // sigue vigente aunque después contrate un plan de pago.
                $table->timestamp('trial_usado_at')->nullable()->after('fecha_expiracion');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('dependencias', 'trial_usado_at')) {
            Schema::table('dependencias', function (Blueprint $table) {
                $table->dropColumn('trial_usado_at');
            });
        }
    }
};

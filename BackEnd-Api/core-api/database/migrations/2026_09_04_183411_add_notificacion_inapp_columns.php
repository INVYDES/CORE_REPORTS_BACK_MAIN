<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notificaciones', function (Blueprint $table) {
            if (!Schema::hasColumn('notificaciones', 'usuario_id')) {
                $table->foreignId('usuario_id')->nullable()->after('dependencia_id')->constrained('usuarios')->nullOnDelete();
            }
            if (!Schema::hasColumn('notificaciones', 'leida_at')) {
                $table->timestamp('leida_at')->nullable()->after('estatus');
            }
            if (!Schema::hasColumn('notificaciones', 'titulo')) {
                $table->string('titulo', 200)->nullable()->after('tipo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notificaciones', function (Blueprint $table) {
            if (Schema::hasColumn('notificaciones', 'usuario_id')) {
                $table->dropForeign(['usuario_id']);
                $table->dropColumn('usuario_id');
            }
            if (Schema::hasColumn('notificaciones', 'leida_at')) $table->dropColumn('leida_at');
            if (Schema::hasColumn('notificaciones', 'titulo')) $table->dropColumn('titulo');
        });
    }
};

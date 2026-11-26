<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dependencias')) {
            return;
        }

        Schema::table('dependencias', function (Blueprint $table) {
            if (! Schema::hasColumn('dependencias', 'smtp_host')) {
                $table->string('smtp_host', 191)->nullable();
            }
            if (! Schema::hasColumn('dependencias', 'smtp_port')) {
                $table->integer('smtp_port')->nullable()->default(587);
            }
            if (! Schema::hasColumn('dependencias', 'smtp_encryption')) {
                $table->string('smtp_encryption', 20)->nullable()->default('tls');
            }
            if (! Schema::hasColumn('dependencias', 'smtp_username')) {
                $table->string('smtp_username', 191)->nullable();
            }
            if (! Schema::hasColumn('dependencias', 'smtp_password')) {
                $table->text('smtp_password')->nullable();
            }
            if (! Schema::hasColumn('dependencias', 'smtp_from_address')) {
                $table->string('smtp_from_address', 191)->nullable();
            }
            if (! Schema::hasColumn('dependencias', 'smtp_from_name')) {
                $table->string('smtp_from_name', 191)->nullable();
            }
            if (! Schema::hasColumn('dependencias', 'smtp_activo')) {
                $table->boolean('smtp_activo')->default(false);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dependencias')) {
            return;
        }

        Schema::table('dependencias', function (Blueprint $table) {
            $cols = [
                'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username',
                'smtp_password', 'smtp_from_address', 'smtp_from_name', 'smtp_activo',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('dependencias', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('encuestas')) {
            Schema::create('encuestas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();
                $table->foreignId('reporte_id')->constrained('reportes')->cascadeOnDelete()->unique();
                $table->tinyInteger('calificacion')->unsigned()->comment('1-5');
                $table->text('comentario')->nullable();
                $table->string('respondido_por', 150)->nullable()->comment('Nombre del cliente que califica');
                $table->timestamp('created_at')->nullable()->useCurrent();
            });
            // CHECK 1-5 (MySQL 8)
            try {
                \Illuminate\Support\Facades\DB::statement('ALTER TABLE encuestas ADD CONSTRAINT chk_calificacion CHECK (calificacion BETWEEN 1 AND 5)');
            } catch (\Throwable $e) {
            }
        }

        $cols = [
            'regimen_fiscal' => ['string', 10, 'Clave SAT, ej. 601, 612'],
            'uso_cfdi' => ['string', 10, 'Clave SAT, ej. G03, P01'],
            'calle' => ['string', 150, null],
            'colonia' => ['string', 100, null],
            'codigo_postal' => ['string', 10, null],
            'ciudad' => ['string', 100, null],
            'estado' => ['string', 100, null],
            'municipio' => ['string', 100, null],
            'metodo_pago' => ['string', 10, 'Clave SAT, ej. PUE, PPD'],
            'forma_pago' => ['string', 10, 'Clave SAT, ej. 03, 04'],
        ];
        foreach ($cols as $col => [$type, $len, $comment]) {
            if (! Schema::hasColumn('dependencias', $col)) {
                Schema::table('dependencias', function (Blueprint $table) use ($col, $len, $comment) {
                    $c = $table->string($col, $len)->nullable();
                    if ($comment) {
                        $c->comment($comment);
                    }
                });
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('encuestas');
        // columnas fiscales se dejan (no se hace drop para no perder datos)
    }
};

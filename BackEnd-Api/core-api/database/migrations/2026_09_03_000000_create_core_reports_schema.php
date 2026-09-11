<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Framework tables (if not exists)
        if (!Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
        }
        if (!Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration');
            });
        }
        if (!Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('queue');
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
                $table->index('queue');
            });
        }
        if (!Schema::hasTable('job_batches')) {
            Schema::create('job_batches', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name');
                $table->integer('total_jobs');
                $table->integer('pending_jobs');
                $table->integer('failed_jobs');
                $table->longText('failed_job_ids');
                $table->mediumText('options')->nullable();
                $table->integer('cancelled_at')->nullable();
                $table->integer('created_at');
                $table->integer('finished_at')->nullable();
            });
        }
        if (!Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }
        if (!Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }
        if (!Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->string('remember_token', 100)->nullable();
                $table->timestamps();
            });
        }

        // Core tables
        if (!Schema::hasTable('dependencias')) {
            Schema::create('dependencias', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('nombre', 150);
                $table->string('rfc', 20)->nullable();
                $table->enum('tipo_licencia', ['trial','mensual','anual'])->default('trial');
                $table->date('fecha_expiracion')->nullable();
                $table->integer('limite_usuarios')->default(10);
                $table->integer('limite_reportes_mensuales')->default(100);
                $table->string('correo_contacto', 150)->nullable();
                $table->string('correo_reportes', 150)->nullable();
                $table->string('telefono', 25)->nullable();
                $table->text('datos_facturacion')->nullable();
                $table->boolean('activa')->default(true);
                $table->timestamps();
                $table->softDeletes();
                $table->string('regimen_fiscal', 10)->nullable()->comment('Clave SAT');
                $table->string('uso_cfdi', 10)->nullable();
                $table->string('calle', 150)->nullable();
                $table->string('colonia', 100)->nullable();
                $table->string('codigo_postal', 10)->nullable();
                $table->string('ciudad', 100)->nullable();
                $table->string('estado', 100)->nullable();
                $table->string('municipio', 100)->nullable();
                $table->string('metodo_pago', 10)->nullable();
                $table->string('forma_pago', 10)->nullable();
            });
        }

        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->tinyInteger('id')->unsigned()->primary();
                $table->string('nombre', 50);
                $table->string('codigo', 50)->unique();
                $table->text('descripcion')->nullable();
                $table->boolean('es_base')->default(true);
            });
        }

        if (!Schema::hasTable('usuarios')) {
            Schema::create('usuarios', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();
                $table->string('numero_empleado', 50)->nullable();
                $table->tinyInteger('rol')->comment('0:Cliente,1:AdminTec,2:AdminCom,3:Tecnico');
                $table->string('nombre', 100);
                $table->string('apellidos', 100);
                $table->string('email', 150)->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->boolean('estado')->default(true);
                $table->timestamp('ultimo_acceso')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index('dependencia_id');
                $table->index('rol');
            });
        }

        if (!Schema::hasTable('areas')) {
            Schema::create('areas', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();
                $table->string('codigo', 20);
                $table->string('nombre', 150);
                $table->text('descripcion')->nullable();
                $table->boolean('activa')->default(true);
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['dependencia_id','codigo']);
            });
        }

        if (!Schema::hasTable('equipos')) {
            Schema::create('equipos', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();
                $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
                $table->string('codigo', 50);
                $table->string('nombre', 150);
                $table->string('marca', 100)->nullable();
                $table->string('modelo', 100)->nullable();
                $table->string('numero_serie', 100)->nullable();
                $table->date('fecha_instalacion')->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['dependencia_id','codigo']);
            });
        }

        if (!Schema::hasTable('tickets')) {
            Schema::create('tickets', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();
                $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
                $table->foreignId('equipo_id')->nullable()->constrained('equipos')->nullOnDelete();
                $table->string('folio', 50);
                $table->string('asunto', 200);
                $table->text('descripcion')->nullable();
                $table->string('solicitante', 150)->nullable();
                $table->string('cargo', 100)->nullable();
                $table->enum('prioridad', ['alta','media','baja'])->default('media');
                $table->enum('estatus', ['abierto','en_proceso','resuelto','cerrado'])->default('abierto');
                $table->timestamp('fecha_solicitud')->nullable()->useCurrent();
                $table->timestamp('fecha_atencion')->nullable();
                $table->timestamp('fecha_limite')->nullable();
                $table->integer('sla_horas')->nullable();
                $table->foreignId('usuario_asignado_id')->nullable()->constrained('usuarios')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['dependencia_id','folio']);
            });
        }

        if (!Schema::hasTable('servicios')) {
            Schema::create('servicios', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();
                $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
                $table->foreignId('equipo_id')->nullable()->constrained('equipos')->nullOnDelete();
                $table->string('folio', 50);
                $table->string('asunto', 200);
                $table->text('descripcion')->nullable();
                $table->string('solicitante', 150)->nullable();
                $table->string('cargo', 100)->nullable();
                $table->enum('categoria', ['preventivo','correctivo','instalacion','mejora','diagnostico'])->nullable();
                $table->foreignId('usuario_asignado_id')->nullable()->constrained('usuarios')->nullOnDelete();
                $table->date('fecha_asignacion')->nullable();
                $table->date('fecha_vencimiento')->nullable();
                $table->enum('estatus', ['programado','en_proceso','realizado','vencido'])->default('programado');
                $table->string('prioridad', 50)->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['dependencia_id','folio']);
            });
        }

        if (!Schema::hasTable('reportes')) {
            Schema::create('reportes', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();
                $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
                $table->foreignId('equipo_id')->nullable()->constrained('equipos')->nullOnDelete();
                $table->string('folio', 50);
                $table->foreignId('creado_por')->constrained('usuarios');
                $table->foreignId('responsable_id')->nullable()->constrained('usuarios')->nullOnDelete();
                $table->foreignId('ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
                $table->foreignId('servicio_id')->nullable()->constrained('servicios')->nullOnDelete();
                $table->enum('tipo', ['ticket','servicio','libre']);
                $table->enum('categoria', ['preventivo','correctivo','diagnostico','instalacion','mejora'])->nullable();
                $table->dateTime('fecha_inicio');
                $table->dateTime('fecha_fin');
                $table->integer('minutos_trabajados')->storedAs('TIMESTAMPDIFF(MINUTE, fecha_inicio, fecha_fin)');
                $table->text('desarrollo')->nullable();
                $table->enum('estatus', ['abierto','parcial','finalizado','descartado'])->default('abierto');
                $table->decimal('costo_mano_obra', 10, 2)->default(0);
                $table->decimal('costo_materiales', 10, 2)->default(0);
                $table->timestamps();
                $table->softDeletes();
                $table->dateTime('hora_salida')->nullable();
                $table->dateTime('hora_llegada')->nullable();
                $table->dateTime('hora_inicio_diagnostico')->nullable();
                $table->dateTime('hora_inicio_trabajo')->nullable();
                $table->dateTime('hora_fin_trabajo')->nullable();
                $table->dateTime('hora_regreso')->nullable();
                $table->boolean('es_retrabajo')->default(false);
                $table->foreignId('reporte_origen_id')->nullable()->constrained('reportes')->nullOnDelete();
                $table->enum('conformidad_estatus', ['pendiente','aprobado','rechazado'])->default('pendiente');
                $table->string('conformidad_firmado_por', 150)->nullable();
                $table->timestamp('conformidad_fecha')->nullable();
                $table->boolean('ftfr')->nullable();
                $table->unique(['dependencia_id','folio']);
            });
        }

        if (!Schema::hasTable('materiales_catalogo')) {
            Schema::create('materiales_catalogo', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();
                $table->string('nombre', 150);
                $table->text('descripcion')->nullable();
                $table->string('unidad_base', 50);
                $table->decimal('costo_unitario', 10, 2)->default(0);
                $table->decimal('stock_actual', 10, 2)->default(0);
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('reporte_ejecutores')) {
            Schema::create('reporte_ejecutores', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();
                $table->foreignId('reporte_id')->constrained('reportes')->cascadeOnDelete();
                $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
                $table->timestamp('created_at')->nullable()->useCurrent();
                $table->integer('minutos_invertidos')->nullable();
                $table->unique(['reporte_id','usuario_id']);
            });
        }

        if (!Schema::hasTable('reporte_materiales')) {
            Schema::create('reporte_materiales', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();
                $table->foreignId('reporte_id')->constrained('reportes')->cascadeOnDelete();
                $table->foreignId('material_id')->constrained('materiales_catalogo')->restrictOnDelete();
                $table->decimal('cantidad', 10, 2);
                $table->decimal('costo_unitario', 10, 2);
                $table->decimal('subtotal', 10, 2)->storedAs('cantidad * costo_unitario');
                $table->timestamp('created_at')->nullable()->useCurrent();
            });
        }

        if (!Schema::hasTable('reporte_evidencias')) {
            Schema::create('reporte_evidencias', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();
                $table->foreignId('reporte_id')->constrained('reportes')->cascadeOnDelete();
                $table->string('url');
                $table->string('tipo_archivo', 50)->nullable();
                $table->integer('peso_kb')->nullable();
                $table->foreignId('subido_por')->nullable()->constrained('usuarios')->nullOnDelete();
                $table->timestamp('fecha_subida')->nullable()->useCurrent();
            });
        }

        if (!Schema::hasTable('ticket_historial')) {
            Schema::create('ticket_historial', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();
                $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
                $table->string('estatus_anterior', 50)->nullable();
                $table->string('estatus_nuevo', 50)->nullable();
                $table->foreignId('cambiado_por')->constrained('usuarios');
                $table->timestamp('fecha_cambio')->nullable()->useCurrent();
            });
        }

        if (!Schema::hasTable('servicio_historial')) {
            Schema::create('servicio_historial', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();
                $table->foreignId('servicio_id')->constrained('servicios')->cascadeOnDelete();
                $table->string('estatus_anterior', 50)->nullable();
                $table->string('estatus_nuevo', 50)->nullable();
                $table->foreignId('cambiado_por')->constrained('usuarios');
                $table->timestamp('fecha_cambio')->nullable()->useCurrent();
            });
        }

        if (!Schema::hasTable('movimientos_inventario')) {
            Schema::create('movimientos_inventario', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();
                $table->foreignId('material_id')->constrained('materiales_catalogo')->restrictOnDelete();
                $table->enum('tipo', ['entrada','salida','ajuste']);
                $table->decimal('cantidad', 10, 2);
                $table->enum('referencia_tipo', ['reporte','compra','ajuste_manual']);
                $table->bigInteger('referencia_id')->unsigned()->nullable();
                $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
                $table->text('notas')->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();
            });
        }

        if (!Schema::hasTable('notificaciones')) {
            Schema::create('notificaciones', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();
                $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
                $table->string('tipo', 50);
                $table->string('titulo', 200)->nullable();
                $table->enum('referencia_tipo', ['ticket','servicio','reporte']);
                $table->bigInteger('referencia_id')->unsigned();
                $table->string('destinatario', 150);
                $table->string('asunto', 200);
                $table->text('cuerpo')->nullable();
                $table->timestamp('enviado_at')->nullable();
                $table->enum('estatus', ['pendiente','enviado','fallido'])->default('pendiente');
                $table->timestamp('leida_at')->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();
            });
        }

        if (!Schema::hasTable('encuestas')) {
            Schema::create('encuestas', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();
                $table->foreignId('reporte_id')->unique()->constrained('reportes')->cascadeOnDelete();
                $table->tinyInteger('calificacion')->unsigned()->comment('1-5');
                $table->text('comentario')->nullable();
                $table->string('respondido_por', 150)->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();
            });
        }

        if (!Schema::hasTable('suscripciones')) {
            Schema::create('suscripciones', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('dependencia_id')->unique()->constrained('dependencias')->cascadeOnDelete();
                $table->string('proveedor', 50)->default('interno');
                $table->string('proveedor_suscripcion_id', 150)->nullable();
                $table->enum('plan', ['trial','mensual','anual','empresarial'])->default('trial');
                $table->enum('estado', ['trial','activa','vencida','suspendida','cancelada'])->default('trial');
                $table->integer('limite_usuarios')->default(10);
                $table->integer('limite_reportes_mensuales')->default(100);
                $table->date('fecha_inicio');
                $table->date('fecha_expiracion')->nullable();
                $table->boolean('renovacion_automatica')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('bitacora_actividades')) {
            Schema::create('bitacora_actividades', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();
                $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
                $table->string('accion', 100);
                $table->string('modelo_tipo', 100);
                $table->bigInteger('modelo_id')->unsigned();
                $table->json('valores_anteriores')->nullable();
                $table->json('valores_nuevos')->nullable();
                $table->string('direccion_ip', 45)->nullable();
                $table->timestamp('creado_en')->nullable()->useCurrent();
            });
        }

        // Views
        $this->createViews();
    }

    private function createViews(): void
    {
        DB::statement("CREATE OR REPLACE VIEW vw_ticket_sla AS select t.id AS ticket_id,t.dependencia_id AS dependencia_id,t.prioridad AS prioridad,t.fecha_solicitud AS fecha_solicitud,t.fecha_limite AS fecha_limite,t.fecha_atencion AS fecha_atencion,(case when (t.fecha_atencion is null) then NULL when (t.fecha_limite is null) then NULL when (t.fecha_atencion <= t.fecha_limite) then 1 else 0 end) AS a_tiempo from tickets t where (t.deleted_at is null)");
        DB::statement("CREATE OR REPLACE VIEW vw_ticket_mttr AS select t.id AS ticket_id,t.dependencia_id AS dependencia_id,t.equipo_id AS equipo_id,t.fecha_solicitud AS fecha_solicitud,t.fecha_atencion AS fecha_atencion, timestampdiff(HOUR, t.fecha_solicitud, coalesce(t.fecha_atencion, now())) AS horas_reparacion from tickets t where (t.deleted_at is null and t.estatus in ('resuelto','cerrado'))");
        DB::statement("CREATE OR REPLACE VIEW vw_horas_hombre AS select r.id AS reporte_id,r.dependencia_id AS dependencia_id,r.fecha_inicio AS fecha_inicio,r.fecha_fin AS fecha_fin,r.minutos_trabajados AS minutos from reportes r where (r.deleted_at is null)");
        DB::statement("CREATE OR REPLACE VIEW vw_horas_por_tecnico AS select re.id AS ejecutor_registro_id,re.dependencia_id AS dependencia_id,re.reporte_id AS reporte_id,re.usuario_id AS usuario_id,r.fecha_inicio AS fecha_inicio,r.fecha_fin AS fecha_fin,coalesce(re.minutos_invertidos,r.minutos_trabajados) AS minutos from reporte_ejecutores re join reportes r on r.id = re.reporte_id where (r.deleted_at is null)");
        DB::statement("CREATE OR REPLACE VIEW vw_reportes_retrabajo AS select r.id AS reporte_id,r.dependencia_id AS dependencia_id,r.fecha_inicio AS fecha_inicio,r.es_retrabajo AS es_retrabajo,r.reporte_origen_id AS reporte_origen_id from reportes r where (r.deleted_at is null)");
        DB::statement("CREATE OR REPLACE VIEW vw_reportes_categoria AS select r.id AS reporte_id,r.dependencia_id AS dependencia_id,r.fecha_inicio AS fecha_inicio,r.categoria AS categoria,r.tipo AS tipo from reportes r where (r.deleted_at is null)");
        DB::statement("CREATE OR REPLACE VIEW vw_tiempos_operativos AS select r.id AS reporte_id,r.dependencia_id AS dependencia_id,r.fecha_inicio AS fecha_inicio, timestampdiff(HOUR, r.hora_salida, r.hora_llegada) AS t_atencion_horas, timestampdiff(HOUR, r.hora_llegada, r.hora_inicio_trabajo) AS t_diagnostico_horas, timestampdiff(HOUR, r.hora_salida, r.hora_regreso) AS t_utilizacion_horas from reportes r where (r.deleted_at is null)");
        DB::statement("CREATE OR REPLACE VIEW vw_volumen_operacion AS select r.id AS reporte_id,r.dependencia_id AS dependencia_id,r.fecha_inicio AS fecha_inicio,r.tipo AS tipo from reportes r where (r.deleted_at is null)");
        DB::statement("CREATE OR REPLACE VIEW vw_eficacia_calidad AS select r.id AS reporte_id,r.dependencia_id AS dependencia_id,r.fecha_inicio AS fecha_inicio,r.ftfr AS ftfr,(case when (r.conformidad_estatus = 'aprobado') then 1 when (r.conformidad_estatus = 'pendiente') then NULL else 0 end) AS conforme,(case when (r.estatus = 'descartado') then 1 else 0 end) AS descartado,(case when (r.estatus in ('abierto','parcial')) then 1 else 0 end) AS abierta from reportes r where (r.deleted_at is null)");
        DB::statement("CREATE OR REPLACE VIEW vw_proactivo_reactivo AS select r.id AS reporte_id,r.dependencia_id AS dependencia_id,r.fecha_inicio AS fecha_inicio,r.tipo AS tipo from reportes r where (r.deleted_at is null and r.tipo in ('servicio','ticket'))");
        DB::statement("CREATE OR REPLACE VIEW vw_conteo_operacion AS select d.id AS dependencia_id,(select count(0) from reportes r where r.dependencia_id = d.id and r.deleted_at is null) AS cantidad_reportes_generados,(select count(0) from servicios s where s.dependencia_id = d.id and s.deleted_at is null) AS cantidad_servicios_programados,(select count(0) from tickets t where t.dependencia_id = d.id and t.deleted_at is null) AS cantidad_tickets_levantados from dependencias d");
        DB::statement("CREATE OR REPLACE VIEW vw_servicios_vencidos AS select s.dependencia_id AS dependencia_id,count(0) AS cantidad_sp_vencidos from servicios s where (s.deleted_at is null and (s.estatus = 'vencido' or (s.fecha_vencimiento < curdate() and s.estatus <> 'realizado'))) group by s.dependencia_id");
        DB::statement("CREATE OR REPLACE VIEW vw_servicios_realizados_a_tiempo AS select s.dependencia_id AS dependencia_id,count(0) AS cantidad_sp_realizados_en_tiempo from servicios s where (s.deleted_at is null and s.estatus = 'realizado' and s.fecha_vencimiento >= cast(s.updated_at as date)) group by s.dependencia_id");
    }

    public function down(): void
    {
        $views = ['vw_ticket_sla','vw_ticket_mttr','vw_horas_hombre','vw_horas_por_tecnico','vw_reportes_retrabajo','vw_reportes_categoria','vw_tiempos_operativos','vw_volumen_operacion','vw_eficacia_calidad','vw_proactivo_reactivo','vw_conteo_operacion','vw_servicios_vencidos','vw_servicios_realizados_a_tiempo'];
        foreach ($views as $v) DB::statement("DROP VIEW IF EXISTS $v");
        Schema::dropIfExists('bitacora_actividades');
        Schema::dropIfExists('suscripciones');
        Schema::dropIfExists('encuestas');
        Schema::dropIfExists('notificaciones');
        Schema::dropIfExists('movimientos_inventario');
        Schema::dropIfExists('servicio_historial');
        Schema::dropIfExists('ticket_historial');
        Schema::dropIfExists('reporte_evidencias');
        Schema::dropIfExists('reporte_materiales');
        Schema::dropIfExists('reporte_ejecutores');
        Schema::dropIfExists('materiales_catalogo');
        Schema::dropIfExists('reportes');
        Schema::dropIfExists('servicios');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('equipos');
        Schema::dropIfExists('areas');
        Schema::dropIfExists('usuarios');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('dependencias');
    }
};

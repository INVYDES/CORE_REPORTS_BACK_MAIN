-- ===================================================================
-- CoreReports - Esquema completo de la base de datos
-- Generado desde la base de desarrollo (mysqldump --no-data)
-- Nota: las tablas `migrations` y `personal_access_tokens` se crean
-- automaticamente al ejecutar: php artisan migrate
-- ===================================================================
SET FOREIGN_KEY_CHECKS=0;
SET NAMES utf8mb4;

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `areas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dependencia_id` bigint unsigned NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text,
  `activa` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dependencia_id` (`dependencia_id`,`codigo`),
  KEY `idx_area_dependencia` (`dependencia_id`),
  CONSTRAINT `fk_area_dependencia` FOREIGN KEY (`dependencia_id`) REFERENCES `dependencias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bitacora_actividades` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dependencia_id` bigint unsigned NOT NULL,
  `usuario_id` bigint unsigned NOT NULL,
  `accion` varchar(100) NOT NULL,
  `modelo_tipo` varchar(100) NOT NULL,
  `modelo_id` bigint unsigned NOT NULL,
  `valores_anteriores` json DEFAULT NULL,
  `valores_nuevos` json DEFAULT NULL,
  `direccion_ip` varchar(45) DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `dependencia_id` (`dependencia_id`),
  KEY `modelo_tipo` (`modelo_tipo`,`modelo_id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `idx_bitacora_fecha` (`creado_en`),
  CONSTRAINT `bitacora_actividades_ibfk_1` FOREIGN KEY (`dependencia_id`) REFERENCES `dependencias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bitacora_actividades_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dependencias` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `rfc` varchar(20) DEFAULT NULL,
  `tipo_licencia` enum('trial','mensual','anual') NOT NULL DEFAULT 'trial',
  `fecha_expiracion` date DEFAULT NULL,
  `limite_usuarios` int DEFAULT '10',
  `limite_reportes_mensuales` int DEFAULT '100',
  `correo_contacto` varchar(150) DEFAULT NULL,
  `correo_reportes` varchar(150) DEFAULT NULL,
  `telefono` varchar(25) DEFAULT NULL,
  `datos_facturacion` text,
  `activa` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `regimen_fiscal` varchar(10) DEFAULT NULL COMMENT 'Clave SAT, ej. 601, 612',
  `uso_cfdi` varchar(10) DEFAULT NULL COMMENT 'Clave SAT, ej. G03, P01',
  `calle` varchar(150) DEFAULT NULL,
  `colonia` varchar(100) DEFAULT NULL,
  `codigo_postal` varchar(10) DEFAULT NULL,
  `ciudad` varchar(100) DEFAULT NULL,
  `estado` varchar(100) DEFAULT NULL,
  `municipio` varchar(100) DEFAULT NULL,
  `metodo_pago` varchar(10) DEFAULT NULL COMMENT 'Clave SAT, ej. PUE, PPD',
  `forma_pago` varchar(10) DEFAULT NULL COMMENT 'Clave SAT, ej. 03, 04',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `encuestas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dependencia_id` bigint unsigned NOT NULL,
  `reporte_id` bigint unsigned NOT NULL,
  `calificacion` tinyint unsigned NOT NULL COMMENT '1-5',
  `comentario` text,
  `respondido_por` varchar(150) DEFAULT NULL COMMENT 'Nombre del cliente que califica, puede no tener cuenta',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_encuesta_reporte` (`reporte_id`),
  KEY `idx_encuesta_dependencia` (`dependencia_id`),
  CONSTRAINT `fk_encuesta_dependencia` FOREIGN KEY (`dependencia_id`) REFERENCES `dependencias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_encuesta_reporte` FOREIGN KEY (`reporte_id`) REFERENCES `reportes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_calificacion` CHECK ((`calificacion` between 1 and 5))
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `equipos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dependencia_id` bigint unsigned NOT NULL,
  `area_id` bigint unsigned DEFAULT NULL,
  `codigo` varchar(50) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `marca` varchar(100) DEFAULT NULL,
  `modelo` varchar(100) DEFAULT NULL,
  `numero_serie` varchar(100) DEFAULT NULL,
  `fecha_instalacion` date DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dependencia_id` (`dependencia_id`,`codigo`),
  KEY `idx_equipo_dependencia` (`dependencia_id`),
  KEY `idx_equipo_area` (`area_id`),
  CONSTRAINT `fk_equipo_area` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_equipo_dependencia` FOREIGN KEY (`dependencia_id`) REFERENCES `dependencias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `materiales_catalogo` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dependencia_id` bigint unsigned NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text,
  `unidad_base` varchar(50) NOT NULL,
  `costo_unitario` decimal(10,2) DEFAULT '0.00',
  `stock_actual` decimal(10,2) DEFAULT '0.00',
  `activo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_material_dependencia` (`dependencia_id`),
  CONSTRAINT `fk_material_dependencia` FOREIGN KEY (`dependencia_id`) REFERENCES `dependencias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `movimientos_inventario` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dependencia_id` bigint unsigned NOT NULL,
  `material_id` bigint unsigned NOT NULL,
  `tipo` enum('entrada','salida','ajuste') NOT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `referencia_tipo` enum('reporte','compra','ajuste_manual') NOT NULL,
  `referencia_id` bigint unsigned DEFAULT NULL COMMENT 'ID del reporte u otra referencia según referencia_tipo',
  `usuario_id` bigint unsigned DEFAULT NULL COMMENT 'Quién registró el movimiento',
  `notas` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_mi_usuario` (`usuario_id`),
  KEY `idx_mi_material` (`material_id`),
  KEY `idx_mi_dependencia` (`dependencia_id`),
  CONSTRAINT `fk_mi_dependencia` FOREIGN KEY (`dependencia_id`) REFERENCES `dependencias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mi_material` FOREIGN KEY (`material_id`) REFERENCES `materiales_catalogo` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_mi_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notificaciones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dependencia_id` bigint unsigned NOT NULL,
  `usuario_id` bigint unsigned DEFAULT NULL,
  `tipo` varchar(50) NOT NULL COMMENT 'ticket_cerrado, servicio_vencido, reporte_finalizado, etc.',
  `titulo` varchar(200) DEFAULT NULL,
  `referencia_tipo` enum('ticket','servicio','reporte') NOT NULL,
  `referencia_id` bigint unsigned NOT NULL,
  `destinatario` varchar(150) NOT NULL,
  `asunto` varchar(200) NOT NULL,
  `cuerpo` text,
  `enviado_at` timestamp NULL DEFAULT NULL,
  `estatus` enum('pendiente','enviado','fallido') DEFAULT 'pendiente',
  `leida_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_dependencia_estatus` (`dependencia_id`,`estatus`),
  KEY `notificaciones_usuario_id_foreign` (`usuario_id`),
  CONSTRAINT `fk_notif_dependencia` FOREIGN KEY (`dependencia_id`) REFERENCES `dependencias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notificaciones_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reporte_ejecutores` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dependencia_id` bigint unsigned NOT NULL,
  `reporte_id` bigint unsigned NOT NULL,
  `usuario_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `minutos_invertidos` int DEFAULT NULL COMMENT 'Minutos que ESTE ejecutor invirtió en el reporte; NULL = usar el total del reporte',
  PRIMARY KEY (`id`),
  UNIQUE KEY `reporte_id` (`reporte_id`,`usuario_id`),
  KEY `idx_re_e_usuario` (`usuario_id`),
  KEY `idx_re_e_dependencia` (`dependencia_id`),
  KEY `idx_re_e_reporte_usuario` (`reporte_id`,`usuario_id`),
  CONSTRAINT `fk_re_e_dependencia` FOREIGN KEY (`dependencia_id`) REFERENCES `dependencias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_re_e_reporte` FOREIGN KEY (`reporte_id`) REFERENCES `reportes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_re_e_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reporte_evidencias` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dependencia_id` bigint unsigned NOT NULL,
  `reporte_id` bigint unsigned NOT NULL,
  `url` varchar(255) NOT NULL,
  `tipo_archivo` varchar(50) DEFAULT NULL,
  `peso_kb` int DEFAULT NULL,
  `subido_por` bigint unsigned DEFAULT NULL,
  `fecha_subida` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_re_dependencia` (`dependencia_id`),
  KEY `fk_re_usuario` (`subido_por`),
  KEY `idx_re_reporte` (`reporte_id`),
  CONSTRAINT `fk_re_dependencia` FOREIGN KEY (`dependencia_id`) REFERENCES `dependencias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_re_reporte` FOREIGN KEY (`reporte_id`) REFERENCES `reportes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_re_usuario` FOREIGN KEY (`subido_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reporte_materiales` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dependencia_id` bigint unsigned NOT NULL,
  `reporte_id` bigint unsigned NOT NULL,
  `material_id` bigint unsigned NOT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `costo_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) GENERATED ALWAYS AS ((`cantidad` * `costo_unitario`)) STORED,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rm_reporte` (`reporte_id`),
  KEY `idx_rm_dependencia` (`dependencia_id`),
  KEY `idx_rm_material` (`material_id`),
  KEY `idx_rm_dependencia_reporte` (`dependencia_id`,`reporte_id`),
  CONSTRAINT `fk_rm_dependencia` FOREIGN KEY (`dependencia_id`) REFERENCES `dependencias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rm_material` FOREIGN KEY (`material_id`) REFERENCES `materiales_catalogo` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_rm_reporte` FOREIGN KEY (`reporte_id`) REFERENCES `reportes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reportes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dependencia_id` bigint unsigned NOT NULL,
  `area_id` bigint unsigned DEFAULT NULL,
  `equipo_id` bigint unsigned DEFAULT NULL,
  `folio` varchar(50) NOT NULL,
  `creado_por` bigint unsigned NOT NULL,
  `responsable_id` bigint unsigned DEFAULT NULL,
  `ticket_id` bigint unsigned DEFAULT NULL,
  `servicio_id` bigint unsigned DEFAULT NULL,
  `tipo` enum('ticket','servicio','libre') NOT NULL,
  `categoria` enum('preventivo','correctivo','diagnostico','instalacion','mejora') DEFAULT NULL,
  `fecha_inicio` datetime NOT NULL,
  `fecha_fin` datetime NOT NULL,
  `minutos_trabajados` int GENERATED ALWAYS AS (timestampdiff(MINUTE,`fecha_inicio`,`fecha_fin`)) STORED,
  `desarrollo` text,
  `estatus` enum('abierto','parcial','finalizado','descartado') DEFAULT 'abierto',
  `costo_mano_obra` decimal(10,2) DEFAULT '0.00',
  `costo_materiales` decimal(10,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `hora_salida` datetime DEFAULT NULL COMMENT 'Salida hacia el sitio (inicio traslado ida)',
  `hora_llegada` datetime DEFAULT NULL COMMENT 'Llegada al sitio (fin traslado ida)',
  `hora_inicio_diagnostico` datetime DEFAULT NULL COMMENT 'Inicio de diagnóstico en sitio',
  `hora_inicio_trabajo` datetime DEFAULT NULL COMMENT 'Inicio de ejecución/trabajo',
  `hora_fin_trabajo` datetime DEFAULT NULL COMMENT 'Fin de ejecución/trabajo',
  `hora_regreso` datetime DEFAULT NULL COMMENT 'Llegada de vuelta (fin traslado vuelta)',
  `es_retrabajo` tinyint(1) NOT NULL DEFAULT '0',
  `reporte_origen_id` bigint unsigned DEFAULT NULL COMMENT 'Reporte anterior que este retrabajo corrige',
  `conformidad_estatus` enum('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
  `conformidad_firmado_por` varchar(150) DEFAULT NULL,
  `conformidad_fecha` timestamp NULL DEFAULT NULL,
  `ftfr` tinyint(1) DEFAULT NULL COMMENT 'NULL hasta que se determine; TRUE = resuelto a la primera',
  PRIMARY KEY (`id`),
  UNIQUE KEY `dependencia_id` (`dependencia_id`,`folio`),
  KEY `idx_reporte_dependencia` (`dependencia_id`),
  KEY `idx_reporte_ticket` (`ticket_id`),
  KEY `idx_reporte_servicio` (`servicio_id`),
  KEY `idx_reporte_creado_por` (`creado_por`),
  KEY `idx_reporte_responsable` (`responsable_id`),
  KEY `idx_reporte_fecha_inicio` (`fecha_inicio`),
  KEY `idx_reporte_estatus` (`estatus`),
  KEY `fk_reporte_origen` (`reporte_origen_id`),
  KEY `idx_reporte_area` (`area_id`),
  KEY `idx_reporte_equipo` (`equipo_id`),
  KEY `idx_reporte_categoria` (`categoria`),
  KEY `idx_reporte_estatus_dep` (`dependencia_id`,`estatus`),
  CONSTRAINT `fk_reporte_area` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_reporte_creador` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_reporte_dependencia` FOREIGN KEY (`dependencia_id`) REFERENCES `dependencias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reporte_equipo` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_reporte_origen` FOREIGN KEY (`reporte_origen_id`) REFERENCES `reportes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_reporte_responsable` FOREIGN KEY (`responsable_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_reporte_servicio` FOREIGN KEY (`servicio_id`) REFERENCES `servicios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_reporte_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` tinyint unsigned NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `descripcion` text,
  `es_base` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `servicio_historial` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dependencia_id` bigint unsigned NOT NULL,
  `servicio_id` bigint unsigned NOT NULL,
  `estatus_anterior` varchar(50) DEFAULT NULL,
  `estatus_nuevo` varchar(50) DEFAULT NULL,
  `cambiado_por` bigint unsigned NOT NULL,
  `fecha_cambio` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_sh_dependencia` (`dependencia_id`),
  KEY `fk_sh_servicio` (`servicio_id`),
  KEY `fk_sh_usuario` (`cambiado_por`),
  CONSTRAINT `fk_sh_dependencia` FOREIGN KEY (`dependencia_id`) REFERENCES `dependencias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sh_servicio` FOREIGN KEY (`servicio_id`) REFERENCES `servicios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sh_usuario` FOREIGN KEY (`cambiado_por`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `servicios` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dependencia_id` bigint unsigned NOT NULL,
  `area_id` bigint unsigned DEFAULT NULL,
  `equipo_id` bigint unsigned DEFAULT NULL,
  `folio` varchar(50) NOT NULL,
  `asunto` varchar(200) NOT NULL,
  `descripcion` text,
  `solicitante` varchar(150) DEFAULT NULL,
  `cargo` varchar(100) DEFAULT NULL,
  `categoria` enum('preventivo','correctivo','instalacion','mejora','diagnostico') DEFAULT NULL,
  `usuario_asignado_id` bigint unsigned DEFAULT NULL,
  `fecha_asignacion` date DEFAULT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `estatus` enum('programado','en_proceso','realizado','vencido') DEFAULT 'programado',
  `prioridad` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dependencia_id` (`dependencia_id`,`folio`),
  KEY `idx_servicio_dependencia_estatus` (`dependencia_id`,`estatus`),
  KEY `idx_servicio_vencimiento` (`fecha_vencimiento`),
  KEY `idx_servicio_asignado` (`usuario_asignado_id`),
  KEY `idx_servicio_categoria` (`categoria`),
  KEY `idx_servicio_fecha_asignacion` (`fecha_asignacion`),
  KEY `idx_servicio_area` (`area_id`),
  KEY `idx_servicio_equipo` (`equipo_id`),
  CONSTRAINT `fk_servicio_area` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_servicio_dependencia` FOREIGN KEY (`dependencia_id`) REFERENCES `dependencias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_servicio_equipo` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_servicio_usuario` FOREIGN KEY (`usuario_asignado_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `suscripciones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dependencia_id` bigint unsigned NOT NULL,
  `proveedor` varchar(50) DEFAULT 'interno',
  `proveedor_suscripcion_id` varchar(150) DEFAULT NULL,
  `plan` enum('trial','mensual','anual','empresarial') NOT NULL DEFAULT 'trial',
  `estado` enum('trial','activa','vencida','suspendida','cancelada') NOT NULL DEFAULT 'trial',
  `limite_usuarios` int NOT NULL DEFAULT '10',
  `limite_reportes_mensuales` int NOT NULL DEFAULT '100',
  `fecha_inicio` date NOT NULL,
  `fecha_expiracion` date DEFAULT NULL,
  `renovacion_automatica` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dependencia_id` (`dependencia_id`),
  KEY `idx_suscripcion_estado` (`estado`),
  KEY `idx_suscripcion_expiracion` (`fecha_expiracion`),
  CONSTRAINT `fk_suscripcion_dependencia` FOREIGN KEY (`dependencia_id`) REFERENCES `dependencias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ticket_historial` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dependencia_id` bigint unsigned NOT NULL,
  `ticket_id` bigint unsigned NOT NULL,
  `estatus_anterior` varchar(50) DEFAULT NULL,
  `estatus_nuevo` varchar(50) DEFAULT NULL,
  `cambiado_por` bigint unsigned NOT NULL,
  `fecha_cambio` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_th_dependencia` (`dependencia_id`),
  KEY `fk_th_ticket` (`ticket_id`),
  KEY `fk_th_usuario` (`cambiado_por`),
  CONSTRAINT `fk_th_dependencia` FOREIGN KEY (`dependencia_id`) REFERENCES `dependencias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_th_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_th_usuario` FOREIGN KEY (`cambiado_por`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tickets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dependencia_id` bigint unsigned NOT NULL,
  `area_id` bigint unsigned DEFAULT NULL,
  `equipo_id` bigint unsigned DEFAULT NULL,
  `folio` varchar(50) NOT NULL,
  `asunto` varchar(200) NOT NULL,
  `descripcion` text,
  `solicitante` varchar(150) DEFAULT NULL,
  `cargo` varchar(100) DEFAULT NULL,
  `prioridad` enum('alta','media','baja') DEFAULT 'media',
  `estatus` enum('abierto','en_proceso','resuelto','cerrado') DEFAULT 'abierto',
  `fecha_solicitud` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_atencion` timestamp NULL DEFAULT NULL,
  `fecha_limite` timestamp NULL DEFAULT NULL,
  `sla_horas` int DEFAULT NULL,
  `usuario_asignado_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dependencia_id` (`dependencia_id`,`folio`),
  KEY `idx_ticket_dependencia_estatus` (`dependencia_id`,`estatus`),
  KEY `idx_ticket_fecha_limite` (`fecha_limite`),
  KEY `idx_ticket_asignado` (`usuario_asignado_id`),
  KEY `idx_ticket_prioridad` (`prioridad`),
  KEY `idx_ticket_fecha_solicitud` (`fecha_solicitud`),
  KEY `idx_ticket_dependencia_fecha` (`dependencia_id`,`fecha_solicitud`),
  KEY `idx_ticket_area` (`area_id`),
  KEY `idx_ticket_equipo` (`equipo_id`),
  CONSTRAINT `fk_ticket_area` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ticket_dependencia` FOREIGN KEY (`dependencia_id`) REFERENCES `dependencias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ticket_equipo` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ticket_usuario` FOREIGN KEY (`usuario_asignado_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dependencia_id` bigint unsigned NOT NULL,
  `numero_empleado` varchar(50) DEFAULT NULL,
  `rol` tinyint NOT NULL COMMENT '0:Cliente,1:AdminTec,2:AdminCom,3:Tecnico',
  `nombre` varchar(100) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `estado` tinyint(1) DEFAULT '1',
  `ultimo_acceso` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_usuario_dependencia` (`dependencia_id`),
  KEY `idx_usuario_rol` (`rol`),
  KEY `idx_usuario_dependencia_rol` (`dependencia_id`,`rol`),
  KEY `idx_usuario_estado` (`estado`),
  KEY `idx_usuario_ultimo_acceso` (`ultimo_acceso`),
  CONSTRAINT `fk_usuario_dependencia` FOREIGN KEY (`dependencia_id`) REFERENCES `dependencias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vw_conteo_operacion` AS SELECT 
 1 AS `dependencia_id`,
 1 AS `cantidad_reportes_generados`,
 1 AS `cantidad_servicios_programados`,
 1 AS `cantidad_tickets_levantados`*/;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vw_eficacia_calidad` AS SELECT 
 1 AS `reporte_id`,
 1 AS `dependencia_id`,
 1 AS `fecha_inicio`,
 1 AS `ftfr`,
 1 AS `conforme`,
 1 AS `descartado`,
 1 AS `abierta`*/;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vw_horas_hombre` AS SELECT 
 1 AS `reporte_id`,
 1 AS `dependencia_id`,
 1 AS `fecha_inicio`,
 1 AS `fecha_fin`,
 1 AS `minutos`*/;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vw_horas_por_tecnico` AS SELECT 
 1 AS `ejecutor_registro_id`,
 1 AS `dependencia_id`,
 1 AS `reporte_id`,
 1 AS `usuario_id`,
 1 AS `fecha_inicio`,
 1 AS `fecha_fin`,
 1 AS `minutos`*/;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vw_proactivo_reactivo` AS SELECT 
 1 AS `reporte_id`,
 1 AS `dependencia_id`,
 1 AS `fecha_inicio`,
 1 AS `tipo`*/;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vw_reportes_categoria` AS SELECT 
 1 AS `reporte_id`,
 1 AS `dependencia_id`,
 1 AS `fecha_inicio`,
 1 AS `categoria`,
 1 AS `tipo`*/;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vw_reportes_retrabajo` AS SELECT 
 1 AS `reporte_id`,
 1 AS `dependencia_id`,
 1 AS `fecha_inicio`,
 1 AS `es_retrabajo`,
 1 AS `reporte_origen_id`*/;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vw_servicios_realizados_a_tiempo` AS SELECT 
 1 AS `dependencia_id`,
 1 AS `cantidad_sp_realizados_en_tiempo`*/;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vw_servicios_vencidos` AS SELECT 
 1 AS `dependencia_id`,
 1 AS `cantidad_sp_vencidos`*/;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vw_ticket_mttr` AS SELECT 
 1 AS `ticket_id`,
 1 AS `dependencia_id`,
 1 AS `equipo_id`,
 1 AS `fecha_solicitud`,
 1 AS `fecha_atencion`,
 1 AS `horas_reparacion`*/;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vw_ticket_sla` AS SELECT 
 1 AS `ticket_id`,
 1 AS `dependencia_id`,
 1 AS `prioridad`,
 1 AS `fecha_solicitud`,
 1 AS `fecha_limite`,
 1 AS `fecha_atencion`,
 1 AS `a_tiempo`*/;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vw_tiempos_operativos` AS SELECT 
 1 AS `reporte_id`,
 1 AS `dependencia_id`,
 1 AS `fecha_inicio`,
 1 AS `t_atencion_horas`,
 1 AS `t_diagnostico_horas`,
 1 AS `t_utilizacion_horas`*/;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vw_volumen_operacion` AS SELECT 
 1 AS `reporte_id`,
 1 AS `dependencia_id`,
 1 AS `fecha_inicio`,
 1 AS `tipo`*/;
SET character_set_client = @saved_cs_client;
/*!50001 DROP VIEW IF EXISTS `vw_conteo_operacion`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `vw_conteo_operacion` AS select `d`.`id` AS `dependencia_id`,(select count(0) from `reportes` `r` where ((`r`.`dependencia_id` = `d`.`id`) and (`r`.`deleted_at` is null))) AS `cantidad_reportes_generados`,(select count(0) from `servicios` `s` where ((`s`.`dependencia_id` = `d`.`id`) and (`s`.`deleted_at` is null))) AS `cantidad_servicios_programados`,(select count(0) from `tickets` `t` where ((`t`.`dependencia_id` = `d`.`id`) and (`t`.`deleted_at` is null))) AS `cantidad_tickets_levantados` from `dependencias` `d` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_eficacia_calidad`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `vw_eficacia_calidad` AS select `r`.`id` AS `reporte_id`,`r`.`dependencia_id` AS `dependencia_id`,`r`.`fecha_inicio` AS `fecha_inicio`,`r`.`ftfr` AS `ftfr`,(case when (`r`.`conformidad_estatus` = 'aprobado') then 1 when (`r`.`conformidad_estatus` = 'pendiente') then NULL else 0 end) AS `conforme`,(case when (`r`.`estatus` = 'descartado') then 1 else 0 end) AS `descartado`,(case when (`r`.`estatus` in ('abierto','parcial')) then 1 else 0 end) AS `abierta` from `reportes` `r` where (`r`.`deleted_at` is null) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_horas_hombre`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `vw_horas_hombre` AS select `r`.`id` AS `reporte_id`,`r`.`dependencia_id` AS `dependencia_id`,`r`.`fecha_inicio` AS `fecha_inicio`,`r`.`fecha_fin` AS `fecha_fin`,`r`.`minutos_trabajados` AS `minutos` from `reportes` `r` where (`r`.`deleted_at` is null) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_horas_por_tecnico`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `vw_horas_por_tecnico` AS select `re`.`id` AS `ejecutor_registro_id`,`re`.`dependencia_id` AS `dependencia_id`,`re`.`reporte_id` AS `reporte_id`,`re`.`usuario_id` AS `usuario_id`,`r`.`fecha_inicio` AS `fecha_inicio`,`r`.`fecha_fin` AS `fecha_fin`,coalesce(`re`.`minutos_invertidos`,`r`.`minutos_trabajados`) AS `minutos` from (`reporte_ejecutores` `re` join `reportes` `r` on((`r`.`id` = `re`.`reporte_id`))) where (`r`.`deleted_at` is null) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_proactivo_reactivo`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `vw_proactivo_reactivo` AS select `r`.`id` AS `reporte_id`,`r`.`dependencia_id` AS `dependencia_id`,`r`.`fecha_inicio` AS `fecha_inicio`,`r`.`tipo` AS `tipo` from `reportes` `r` where ((`r`.`deleted_at` is null) and (`r`.`tipo` in ('servicio','ticket'))) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_reportes_categoria`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `vw_reportes_categoria` AS select `r`.`id` AS `reporte_id`,`r`.`dependencia_id` AS `dependencia_id`,`r`.`fecha_inicio` AS `fecha_inicio`,`r`.`categoria` AS `categoria`,`r`.`tipo` AS `tipo` from `reportes` `r` where (`r`.`deleted_at` is null) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_reportes_retrabajo`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `vw_reportes_retrabajo` AS select `r`.`id` AS `reporte_id`,`r`.`dependencia_id` AS `dependencia_id`,`r`.`fecha_inicio` AS `fecha_inicio`,`r`.`es_retrabajo` AS `es_retrabajo`,`r`.`reporte_origen_id` AS `reporte_origen_id` from `reportes` `r` where (`r`.`deleted_at` is null) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_servicios_realizados_a_tiempo`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `vw_servicios_realizados_a_tiempo` AS select `s`.`dependencia_id` AS `dependencia_id`,count(0) AS `cantidad_sp_realizados_en_tiempo` from `servicios` `s` where ((`s`.`deleted_at` is null) and (`s`.`estatus` = 'realizado') and (`s`.`fecha_vencimiento` >= cast(`s`.`updated_at` as date))) group by `s`.`dependencia_id` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_servicios_vencidos`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `vw_servicios_vencidos` AS select `s`.`dependencia_id` AS `dependencia_id`,count(0) AS `cantidad_sp_vencidos` from `servicios` `s` where ((`s`.`deleted_at` is null) and ((`s`.`estatus` = 'vencido') or ((`s`.`fecha_vencimiento` < curdate()) and (`s`.`estatus` <> 'realizado')))) group by `s`.`dependencia_id` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_ticket_mttr`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `vw_ticket_mttr` AS select `t`.`id` AS `ticket_id`,`t`.`dependencia_id` AS `dependencia_id`,`t`.`equipo_id` AS `equipo_id`,`t`.`fecha_solicitud` AS `fecha_solicitud`,`t`.`fecha_atencion` AS `fecha_atencion`,(timestampdiff(MINUTE,`t`.`fecha_solicitud`,`t`.`fecha_atencion`) / 60.0) AS `horas_reparacion` from `tickets` `t` where ((`t`.`deleted_at` is null) and (`t`.`fecha_atencion` is not null)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_ticket_sla`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `vw_ticket_sla` AS select `t`.`id` AS `ticket_id`,`t`.`dependencia_id` AS `dependencia_id`,`t`.`prioridad` AS `prioridad`,`t`.`fecha_solicitud` AS `fecha_solicitud`,`t`.`fecha_limite` AS `fecha_limite`,`t`.`fecha_atencion` AS `fecha_atencion`,(case when (`t`.`fecha_atencion` is null) then NULL when (`t`.`fecha_limite` is null) then NULL when (`t`.`fecha_atencion` <= `t`.`fecha_limite`) then 1 else 0 end) AS `a_tiempo` from `tickets` `t` where (`t`.`deleted_at` is null) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_tiempos_operativos`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `vw_tiempos_operativos` AS select `r`.`id` AS `reporte_id`,`r`.`dependencia_id` AS `dependencia_id`,`r`.`fecha_inicio` AS `fecha_inicio`,(timestampdiff(MINUTE,`r`.`hora_salida`,`r`.`hora_llegada`) / 60.0) AS `t_atencion_horas`,(timestampdiff(MINUTE,`r`.`hora_inicio_diagnostico`,`r`.`hora_inicio_trabajo`) / 60.0) AS `t_diagnostico_horas`,(timestampdiff(MINUTE,`r`.`hora_salida`,`r`.`hora_regreso`) / 60.0) AS `t_utilizacion_horas` from `reportes` `r` where ((`r`.`deleted_at` is null) and (`r`.`hora_salida` is not null)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_volumen_operacion`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `vw_volumen_operacion` AS select `r`.`id` AS `reporte_id`,`r`.`dependencia_id` AS `dependencia_id`,`r`.`fecha_inicio` AS `fecha_inicio`,`r`.`tipo` AS `tipo` from `reportes` `r` where (`r`.`deleted_at` is null) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Datos base: roles del sistema (es_base)
INSERT INTO `roles` (`id`,`nombre`,`codigo`,`descripcion`,`es_base`) VALUES
 (0,'Cliente Default','default','Solo crea tickets y ve los suyos',1),
 (1,'Administrador Técnico','admin_tecnico','Supervisa operaciones y aprueba reportes',1),
 (2,'Administrador Comercial','admin_comercial','Gestiona licencias, facturación y KPIs',1),
 (3,'Técnico','tecnico','Ejecuta trabajos y genera reportes',1)
ON DUPLICATE KEY UPDATE `nombre`=VALUES(`nombre`), `codigo`=VALUES(`codigo`), `descripcion`=VALUES(`descripcion`), `es_base`=VALUES(`es_base`);
SET FOREIGN_KEY_CHECKS=1;

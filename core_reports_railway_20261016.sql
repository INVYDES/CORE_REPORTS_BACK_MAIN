-- MySQL dump 10.13  Distrib 8.0.30, for Win64 (x86_64)
--
-- Host: metro.proxy.rlwy.net    Database: railway
-- ------------------------------------------------------
-- Server version	9.7.2

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

--
-- Table structure for table `areas`
--

DROP TABLE IF EXISTS `areas`;
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

--
-- Dumping data for table `areas`
--

LOCK TABLES `areas` WRITE;
/*!40000 ALTER TABLE `areas` DISABLE KEYS */;
INSERT INTO `areas` VALUES (1,1,'FUND','Área de Fundición',NULL,1,'2026-09-05 06:03:20','2026-09-05 06:03:20',NULL),(2,1,'ENS-A','Línea de Ensamblaje A',NULL,1,'2026-09-05 06:03:20','2026-09-05 06:03:20',NULL),(3,1,'ALM','Almacén General',NULL,1,'2026-09-05 06:03:20','2026-09-05 06:03:20',NULL);
/*!40000 ALTER TABLE `areas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bitacora_actividades`
--

DROP TABLE IF EXISTS `bitacora_actividades`;
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bitacora_actividades`
--

LOCK TABLES `bitacora_actividades` WRITE;
/*!40000 ALTER TABLE `bitacora_actividades` DISABLE KEYS */;
INSERT INTO `bitacora_actividades` VALUES (1,1,1,'actualizado','App\\Models\\Ticket',1,'{\"id\": 1, \"cargo\": null, \"folio\": \"TKT-2026-001\", \"asunto\": \"Falla prensa hidraulica\", \"area_id\": 1, \"estatus\": \"resuelto\", \"equipo_id\": null, \"prioridad\": \"alta\", \"sla_horas\": null, \"created_at\": \"2026-09-05T06:05:47.000000Z\", \"deleted_at\": null, \"updated_at\": \"2026-09-05T08:47:15.000000Z\", \"descripcion\": \"Prueba real\", \"solicitante\": null, \"fecha_limite\": null, \"dependencia_id\": 1, \"fecha_atencion\": \"2026-09-05T06:05:47.000000Z\", \"fecha_solicitud\": \"2026-09-05T00:05:47.000000Z\", \"usuario_asignado_id\": null}','{\"updated_at\": \"2026-09-12 16:12:31\", \"usuario_asignado_id\": 3}','2806:268:487:105c:fd09:ab55:ea12:c345','2026-09-12 16:12:32');
/*!40000 ALTER TABLE `bitacora_actividades` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
INSERT INTO `cache` VALUES ('laravel-cache-09f5abc4dcbe25da5acabc4ebd2c7c8a','i:1;',1789170331),('laravel-cache-09f5abc4dcbe25da5acabc4ebd2c7c8a:timer','i:1789170330;',1789170331),('laravel-cache-1295d8decb6e59054739b9a316d0ade51f8a959d','i:1;',1789170880),('laravel-cache-1295d8decb6e59054739b9a316d0ade51f8a959d:timer','i:1789170880;',1789170880),('laravel-cache-21a24ca8f6e15019d4fb3d24aca8ab8a','i:3;',1789175162),('laravel-cache-21a24ca8f6e15019d4fb3d24aca8ab8a:timer','i:1789175162;',1789175162),('laravel-cache-2452b9a6eb2820e52426f30164946c7b','i:1;',1789228743),('laravel-cache-2452b9a6eb2820e52426f30164946c7b:timer','i:1789228743;',1789228743),('laravel-cache-29c0684b68e0ffbcfd24f3fee2f52bd5','i:2;',1792093689),('laravel-cache-29c0684b68e0ffbcfd24f3fee2f52bd5:timer','i:1792093689;',1792093689),('laravel-cache-2d82057085e17374acd8ed6aaf9739aa','i:3;',1789229737),('laravel-cache-2d82057085e17374acd8ed6aaf9739aa:timer','i:1789229737;',1789229737),('laravel-cache-30e607d56e58a3b4bd5dee9dd5fda8c37481720e','i:1;',1789183852),('laravel-cache-30e607d56e58a3b4bd5dee9dd5fda8c37481720e:timer','i:1789183852;',1789183852),('laravel-cache-356a192b7913b04c54574d18c28d46e6395428ab','i:15;',1791696352),('laravel-cache-356a192b7913b04c54574d18c28d46e6395428ab:timer','i:1791696352;',1791696352),('laravel-cache-4b8929b500f8a181571a46b73ff2def1e17f10e8','i:3;',1792093765),('laravel-cache-4b8929b500f8a181571a46b73ff2def1e17f10e8:timer','i:1792093765;',1792093765),('laravel-cache-5c785c036466adea360111aa28563bfd556b5fba','i:1;',1789170328),('laravel-cache-5c785c036466adea360111aa28563bfd556b5fba:timer','i:1789170327;',1789170328),('laravel-cache-5fccffad244d2fecedcb5e326231a629','i:1;',1789419414),('laravel-cache-5fccffad244d2fecedcb5e326231a629:timer','i:1789419413;',1789419413),('laravel-cache-65718213ec574c83d8c752618a87d72033b55ade','i:1;',1789419413),('laravel-cache-65718213ec574c83d8c752618a87d72033b55ade:timer','i:1789419413;',1789419413),('laravel-cache-692dcdc7833ed465a0fc0af8b473b41c','i:1;',1789183853),('laravel-cache-692dcdc7833ed465a0fc0af8b473b41c:timer','i:1789183853;',1789183853),('laravel-cache-75adfaa58da7bd7407b23a05c0ecaa49','i:1;',1789419414),('laravel-cache-75adfaa58da7bd7407b23a05c0ecaa49:timer','i:1789419414;',1789419414),('laravel-cache-77de68daecd823babbb58edb1c8e14d7106e83bb','i:6;',1789229923),('laravel-cache-77de68daecd823babbb58edb1c8e14d7106e83bb:timer','i:1789229923;',1789229923),('laravel-cache-a2f42e449dc0f4f78eff9a94620f13f999bc06ca','i:3;',1789175161),('laravel-cache-a2f42e449dc0f4f78eff9a94620f13f999bc06ca:timer','i:1789175161;',1789175161),('laravel-cache-a39dac205ec3e5f89ce75f07d4260e55','i:3;',1789175162),('laravel-cache-a39dac205ec3e5f89ce75f07d4260e55:timer','i:1789175161;',1789175162),('laravel-cache-aba1b0a82edaaeb5a9838f8ceb19267b','i:1;',1791696332),('laravel-cache-aba1b0a82edaaeb5a9838f8ceb19267b:timer','i:1791696332;',1791696332),('laravel-cache-af74faf901fb3c4774a42081133e5323','i:1;',1789170334),('laravel-cache-af74faf901fb3c4774a42081133e5323:timer','i:1789170333;',1789170333),('laravel-cache-d54d560a13c647210413346fc1852ccc','i:1;',1789183854),('laravel-cache-d54d560a13c647210413346fc1852ccc:timer','i:1789183854;',1789183854),('laravel-cache-d72c70db4a56a512fd44db4b4047713c368a033c','i:3;',1789229736),('laravel-cache-d72c70db4a56a512fd44db4b4047713c368a033c:timer','i:1789229735;',1789229736),('laravel-cache-d7b4b11fd29ab3069446fc623d2107aa','i:3;',1789229736),('laravel-cache-d7b4b11fd29ab3069446fc623d2107aa:timer','i:1789229736;',1789229736),('laravel-cache-da4b9237bacccdf19c0760cab7aec4a8359010b0','i:6;',1788576370),('laravel-cache-da4b9237bacccdf19c0760cab7aec4a8359010b0:timer','i:1788576370;',1788576370),('laravel-cache-dd4915e01b089b46a29f77dbfa522321f81d3dc9','i:1;',1791696331),('laravel-cache-dd4915e01b089b46a29f77dbfa522321f81d3dc9:timer','i:1791696331;',1791696331),('laravel-cache-e4242555b4aabfb4c2bfadf342dea8af','i:1;',1792093783),('laravel-cache-e4242555b4aabfb4c2bfadf342dea8af:timer','i:1792093782;',1792093783),('laravel-cache-f1eeb9cdef8dff72a3303957e06ba660','i:1;',1791696333),('laravel-cache-f1eeb9cdef8dff72a3303957e06ba660:timer','i:1791696332;',1791696333),('laravel-cache-f398f6a39cd1e62830f4014dc92191a2','i:3;',1792093766),('laravel-cache-f398f6a39cd1e62830f4014dc92191a2:timer','i:1792093766;',1792093766),('laravel-cache-f9baf63484097ef6ff00c62d0f2714fd','i:1;',1789177150),('laravel-cache-f9baf63484097ef6ff00c62d0f2714fd:timer','i:1789177149;',1789177149),('laravel-cache-fbb4058cb04cd932c74a727bd1ca1c50','i:2;',1792093765),('laravel-cache-fbb4058cb04cd932c74a727bd1ca1c50:timer','i:1792093765;',1792093765);
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dependencias`
--

DROP TABLE IF EXISTS `dependencias`;
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
  `logo` varchar(255) DEFAULT NULL,
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

--
-- Dumping data for table `dependencias`
--

LOCK TABLES `dependencias` WRITE;
/*!40000 ALTER TABLE `dependencias` DISABLE KEYS */;
INSERT INTO `dependencias` VALUES (1,'Automotriz del Bajío S.A. de C.V.','ABA-100520-H52','trial','2028-05-20',10,100,'contacto@autobajio.com','sistemas@autobajio.com','442-123-4567','Parque Industrial',NULL,1,'2026-09-05 06:03:20','2026-09-05 06:03:20',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `dependencias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `encuestas`
--

DROP TABLE IF EXISTS `encuestas`;
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

--
-- Dumping data for table `encuestas`
--

LOCK TABLES `encuestas` WRITE;
/*!40000 ALTER TABLE `encuestas` DISABLE KEYS */;
INSERT INTO `encuestas` VALUES (1,1,2,5,'Excelente servicio','Cliente Demo','2026-09-05 06:30:01');
/*!40000 ALTER TABLE `encuestas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `equipos`
--

DROP TABLE IF EXISTS `equipos`;
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

--
-- Dumping data for table `equipos`
--

LOCK TABLES `equipos` WRITE;
/*!40000 ALTER TABLE `equipos` DISABLE KEYS */;
/*!40000 ALTER TABLE `equipos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `licencias`
--

DROP TABLE IF EXISTS `licencias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `licencias` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('trial','mensual','anual','empresarial') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'mensual',
  `max_usuarios` int NOT NULL DEFAULT '10',
  `max_reportes_mensuales` int NOT NULL DEFAULT '100',
  `precio` decimal(10,2) NOT NULL DEFAULT '0.00',
  `precio_anual` decimal(10,2) DEFAULT NULL,
  `dias_prueba` int NOT NULL DEFAULT '7',
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `paypal_plan_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mercadopago_plan_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `licencias_codigo_unique` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `licencias`
--

LOCK TABLES `licencias` WRITE;
/*!40000 ALTER TABLE `licencias` DISABLE KEYS */;
/*!40000 ALTER TABLE `licencias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `materiales_catalogo`
--

DROP TABLE IF EXISTS `materiales_catalogo`;
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

--
-- Dumping data for table `materiales_catalogo`
--

LOCK TABLES `materiales_catalogo` WRITE;
/*!40000 ALTER TABLE `materiales_catalogo` DISABLE KEYS */;
INSERT INTO `materiales_catalogo` VALUES (1,1,'Tornillo M6',NULL,'pza',12.50,98.00,1,'2026-09-05 06:05:47','2026-09-05 06:05:47',NULL);
/*!40000 ALTER TABLE `materiales_catalogo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_09_05_000035_create_personal_access_tokens_table',2),(5,'2026_09_04_182926_create_encuestas_and_facturacion_columns',3),(6,'2026_09_04_183411_add_notificacion_inapp_columns',4),(7,'2026_09_03_000000_create_core_reports_schema',5),(8,'2026_09_12_000000_create_licencias_table',6),(9,'2026_09_20_000001_add_current_device_to_usuarios',7),(10,'2026_09_21_000000_add_logo_to_dependencias',8);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `movimientos_inventario`
--

DROP TABLE IF EXISTS `movimientos_inventario`;
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

--
-- Dumping data for table `movimientos_inventario`
--

LOCK TABLES `movimientos_inventario` WRITE;
/*!40000 ALTER TABLE `movimientos_inventario` DISABLE KEYS */;
INSERT INTO `movimientos_inventario` VALUES (1,1,1,'salida',2.00,'reporte',1,1,NULL,'2026-09-05 06:05:47');
/*!40000 ALTER TABLE `movimientos_inventario` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notificaciones`
--

DROP TABLE IF EXISTS `notificaciones`;
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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notificaciones`
--

LOCK TABLES `notificaciones` WRITE;
/*!40000 ALTER TABLE `notificaciones` DISABLE KEYS */;
INSERT INTO `notificaciones` VALUES (1,1,NULL,'ticket_asignado',NULL,'ticket',1,'admin.tec@core.com','Ticket asignado prueba','Se te asignó ticket TKT-001',NULL,'fallido','2026-09-05 06:53:11','2026-09-05 06:35:22'),(2,1,NULL,'ticket_asignado',NULL,'ticket',2,'tecnico@core.com','Ticket asignado','Ticket TKT-NOTIF-001 asignado',NULL,'pendiente','2026-09-05 06:56:05','2026-09-05 00:53:09'),(3,1,3,'ticket_resuelto','Ticket TKT-NOTIF-001 resuelto','ticket',2,'sistemas@autobajio.com','Ticket TKT-NOTIF-001 resuelto','El ticket TKT-NOTIF-001 (Prueba notif real) ha cambiado a resuelto.',NULL,'fallido','2026-09-05 06:56:05','2026-09-05 06:53:12'),(4,1,1,'reporte_finalizado','Reporte finalizado REP-NOTIF-001','reporte',3,'sistemas@autobajio.com','Reporte REP-NOTIF-001 finalizado','El reporte REP-NOTIF-001 ha sido finalizado por el técnico. Tipo: ticket, Categoría: correctivo',NULL,'fallido','2026-09-05 06:56:05','2026-09-05 06:53:13'),(5,1,NULL,'ticket_asignado',NULL,'ticket',1,'tecnico@core.com','Ticket asignado','Ticket TKT-2026-001 asignado',NULL,'pendiente','2026-09-12 01:20:44','2026-09-05 02:31:41'),(6,1,NULL,'ticket_resuelto','Ticket TKT-2026-001 resuelto','ticket',1,'sistemas@autobajio.com','Ticket TKT-2026-001 resuelto','El ticket TKT-2026-001 (Falla prensa hidraulica) ha cambiado a resuelto.',NULL,'fallido','2026-09-12 01:20:44','2026-09-05 08:47:15'),(7,1,1,'reporte_finalizado','Reporte finalizado REP-2026-001','reporte',1,'sistemas@autobajio.com','Reporte REP-2026-001 finalizado','El reporte REP-2026-001 ha sido finalizado por el técnico. Tipo: ticket, Categoría: correctivo',NULL,'fallido','2026-09-12 01:20:44','2026-09-05 08:47:17'),(8,1,3,'ticket_asignado',NULL,'ticket',1,'tecnico@core.com','Ticket TKT-2026-001 asignado','Se te asignó el ticket TKT-2026-001: Falla prensa hidraulica',NULL,'pendiente',NULL,'2026-09-12 16:12:32');
/*!40000 ALTER TABLE `notificaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
INSERT INTO `personal_access_tokens` VALUES (26,'App\\Models\\Usuario',2,'api','9cc939bd98870ff9ac230a04182ca859e21d6c92611904b6176f7601c90ee0e2','[\"*\"]','2026-09-05 08:45:34',NULL,'2026-09-05 08:43:52','2026-09-05 08:45:34'),(29,'App\\Models\\Usuario',2,'api','e303041d68a0d1210cd69c38fa34e92d6b857c6416ec7c288e0f1a4a2cae8eb6','[\"*\"]','2026-09-05 08:46:07',NULL,'2026-09-05 08:45:58','2026-09-05 08:46:07'),(59,'App\\Models\\Usuario',3,'9c772391-43b0-4597-aa96-2e081001395c','189863c8664f9cfc6e6df70018ed5a6c58b043aedf5654723bfbed0c35403394','[\"*\"]','2026-09-12 16:17:55',NULL,'2026-09-12 16:14:59','2026-09-12 16:17:55'),(63,'App\\Models\\Usuario',1,'014c4ed6-9eda-4f60-a3f6-8d8f500a00f6','e7c52b4df58d34bd60a50e792ada887b72b93c182923c1b882a278920e5f753e','[\"*\"]','2026-10-11 05:25:19',NULL,'2026-10-11 05:24:33','2026-10-11 05:25:19');
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reporte_ejecutores`
--

DROP TABLE IF EXISTS `reporte_ejecutores`;
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

--
-- Dumping data for table `reporte_ejecutores`
--

LOCK TABLES `reporte_ejecutores` WRITE;
/*!40000 ALTER TABLE `reporte_ejecutores` DISABLE KEYS */;
INSERT INTO `reporte_ejecutores` VALUES (1,1,1,3,'2026-09-05 00:05:47',NULL);
/*!40000 ALTER TABLE `reporte_ejecutores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reporte_evidencias`
--

DROP TABLE IF EXISTS `reporte_evidencias`;
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

--
-- Dumping data for table `reporte_evidencias`
--

LOCK TABLES `reporte_evidencias` WRITE;
/*!40000 ALTER TABLE `reporte_evidencias` DISABLE KEYS */;
/*!40000 ALTER TABLE `reporte_evidencias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reporte_materiales`
--

DROP TABLE IF EXISTS `reporte_materiales`;
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

--
-- Dumping data for table `reporte_materiales`
--

LOCK TABLES `reporte_materiales` WRITE;
/*!40000 ALTER TABLE `reporte_materiales` DISABLE KEYS */;
INSERT INTO `reporte_materiales` (`id`, `dependencia_id`, `reporte_id`, `material_id`, `cantidad`, `costo_unitario`, `created_at`) VALUES (1,1,1,1,2.00,12.50,'2026-09-05 00:05:47');
/*!40000 ALTER TABLE `reporte_materiales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reportes`
--

DROP TABLE IF EXISTS `reportes`;
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

--
-- Dumping data for table `reportes`
--

LOCK TABLES `reportes` WRITE;
/*!40000 ALTER TABLE `reportes` DISABLE KEYS */;
INSERT INTO `reportes` (`id`, `dependencia_id`, `area_id`, `equipo_id`, `folio`, `creado_por`, `responsable_id`, `ticket_id`, `servicio_id`, `tipo`, `categoria`, `fecha_inicio`, `fecha_fin`, `desarrollo`, `estatus`, `costo_mano_obra`, `costo_materiales`, `created_at`, `updated_at`, `deleted_at`, `hora_salida`, `hora_llegada`, `hora_inicio_diagnostico`, `hora_inicio_trabajo`, `hora_fin_trabajo`, `hora_regreso`, `es_retrabajo`, `reporte_origen_id`, `conformidad_estatus`, `conformidad_firmado_por`, `conformidad_fecha`, `ftfr`) VALUES (1,1,NULL,NULL,'REP-2026-001',1,NULL,1,NULL,'ticket','correctivo','2026-09-05 10:00:00','2026-09-05 14:00:00','Cambio de mangueras','finalizado',0.00,0.00,'2026-09-05 06:05:47','2026-09-05 08:47:33',NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,'pendiente',NULL,NULL,NULL),(2,1,NULL,NULL,'REP-ENC-001',1,NULL,NULL,NULL,'libre','correctivo','2026-09-04 10:00:00','2026-09-04 12:00:00','Prueba encuesta','finalizado',0.00,0.00,'2026-09-05 06:30:01','2026-09-05 06:30:01',NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,'pendiente',NULL,NULL,NULL),(3,1,NULL,NULL,'REP-NOTIF-001',1,NULL,2,NULL,'ticket','correctivo','2026-09-04 10:00:00','2026-09-04 12:00:00','Finalizado para notif','finalizado',0.00,0.00,'2026-09-05 06:53:12','2026-09-05 06:53:12',NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,'pendiente',NULL,NULL,NULL);
/*!40000 ALTER TABLE `reportes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
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

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (0,'Cliente Default','default','Solo crea tickets y ve los suyos',1),(1,'Administrador Técnico','admin_tecnico','Supervisa operaciones y aprueba reportes',1),(2,'Administrador Comercial','admin_comercial','Gestiona licencias, facturación y KPIs',1),(3,'Técnico','tecnico','Ejecuta trabajos y genera reportes',1);
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `servicio_historial`
--

DROP TABLE IF EXISTS `servicio_historial`;
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

--
-- Dumping data for table `servicio_historial`
--

LOCK TABLES `servicio_historial` WRITE;
/*!40000 ALTER TABLE `servicio_historial` DISABLE KEYS */;
/*!40000 ALTER TABLE `servicio_historial` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `servicios`
--

DROP TABLE IF EXISTS `servicios`;
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

--
-- Dumping data for table `servicios`
--

LOCK TABLES `servicios` WRITE;
/*!40000 ALTER TABLE `servicios` DISABLE KEYS */;
INSERT INTO `servicios` VALUES (1,1,1,NULL,'SRV-2026-001','Mantenimiento preventivo',NULL,NULL,NULL,'preventivo',NULL,'2026-09-06','2026-09-10','programado',NULL,'2026-09-05 06:05:47','2026-09-05 06:05:47',NULL);
/*!40000 ALTER TABLE `servicios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('FI2L9AzHkaZEEBVeoeK0bElGdJklzGUTsqVX6IUi',NULL,'169.254.169.126','curl/8.21.0','YTozOntzOjY6Il90b2tlbiI7czo0MDoidjJkeDlvMHlsZ0J3bktKekFPU254UG1SMVRnMGZtcjNHbnIxOTg1SSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NTA6Imh0dHA6Ly9jc3JlY29yZHMtMTA5NTk5NTg4NjkxOC51cy1jZW50cmFsMS5ydW4uYXBwIjtzOjU6InJvdXRlIjtzOjI3OiJnZW5lcmF0ZWQ6OkdVamNzSzZHQWpyQmNGSEwiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1788913547),('fMvCgPpxPI3GUaQ1hAvshKjuNuls9TS7SM9S4Rol',NULL,'2806:10a6:7:20fc:3d54:5d7a:470b:9b68','curl/8.21.0','YTozOntzOjY6Il90b2tlbiI7czo0MDoiakpwZ0dVNTNqS3R6NnJWWWxmcVU3U3AwTjRseUJJU2xVcTVDSlo3VCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NTg6Imh0dHBzOi8vY3NyZWNvcmRzLTEwOTU5OTU4ODY5MTgudXMtY2VudHJhbDEucnVuLmFwcC9oZWFsdGgiO3M6NToicm91dGUiO3M6Mjc6ImdlbmVyYXRlZDo6a09GUlpFNjBwWHRFc0toRiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1791696045),('hBF5f7zQgzLUfknKO8E6ZHJ3fCWkhblU3nu4F4uw',NULL,'169.254.169.126','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiR2lhRHZtcjFuRzhSTHVSTmx6S2liQ0hUcjFVZGxqcXg4MlNHVXpqSyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NDA6Imh0dHA6Ly9jc3JlY29yZHMtMm5hM3duemQ0YS11Yy5hLnJ1bi5hcHAiO3M6NToicm91dGUiO3M6Mjc6ImdlbmVyYXRlZDo6UDBFd0swaW5sNjJFQW16UyI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1789162463),('n4qctZUxZZiUhbvCyZh5KNWgF2qt8iR9rPtCvuAs',NULL,'169.254.169.126','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoidkY5WGxxZDdYcnNJelVMZjZwcXJ0VjRWa1o5anZ3c2x2N2Jnelh0NyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NDA6Imh0dHA6Ly9jc3JlY29yZHMtMm5hM3duemQ0YS11Yy5hLnJ1bi5hcHAiO3M6NToicm91dGUiO3M6Mjc6ImdlbmVyYXRlZDo6UDBFd0swaW5sNjJFQW16UyI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1789162463),('nkvQoDhgslZkqfHkoHv0Rjou9qH2e0N3bqDnBUQT',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.134.0 Chrome/148.0.7778.280 Electron/42.8.1 Safari/537.36','YToyOntzOjY6Il90b2tlbiI7czo0MDoic29oZlFnU1hneFUxTVNZNjFXQ09NNzhPc1Q4d0ROclZWNHdMeWlDdSI7czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1788567267),('oMfoPjmzm6Lb4HW86DVXKf8HrSlK6ZAajLsmsuX1',NULL,'2806:10a6:7:20fc:3d54:5d7a:470b:9b68','curl/8.21.0','YTozOntzOjY6Il90b2tlbiI7czo0MDoic1JRNHg3N1pEcHhIWVY2ZEpNTkJ0b0tTdzJGbWpZekxpM2FSb0s1NyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NTg6Imh0dHBzOi8vY3NyZWNvcmRzLTEwOTU5OTU4ODY5MTgudXMtY2VudHJhbDEucnVuLmFwcC9oZWFsdGgiO3M6NToicm91dGUiO3M6Mjc6ImdlbmVyYXRlZDo6c3NTajVWbWpXaXdqaVh6ZyI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1791696414),('S3VNqZ6LcmUap29VmVWnhQpvsqPP38NGuL7SK2dQ',NULL,'2806:10a6:7:20fc:3d54:5d7a:470b:9b68','curl/8.21.0','YTozOntzOjY6Il90b2tlbiI7czo0MDoidmpaclBQMXZpSExtTWt1VDBQN3NwTG9FNnFmTjVLZFdQOHBrQnl2ZSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NTg6Imh0dHBzOi8vY3NyZWNvcmRzLTEwOTU5OTU4ODY5MTgudXMtY2VudHJhbDEucnVuLmFwcC9oZWFsdGgiO3M6NToicm91dGUiO3M6Mjc6ImdlbmVyYXRlZDo6S0pjMm1mSEhKYVd3V3JSQiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1791695392),('uM09dr4NXj0VlSF9PD86RCyjfDSMRJYIR096ppo1',NULL,'2806:10a6:7:20fc:3d54:5d7a:470b:9b68','curl/8.21.0','YTozOntzOjY6Il90b2tlbiI7czo0MDoicGkxd3NXS2phdWxodjdlQ1B5M1Ayd1lzS01wOUhoOGpxcVVaaXI4RiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NTE6Imh0dHBzOi8vY3NyZWNvcmRzLTEwOTU5OTU4ODY5MTgudXMtY2VudHJhbDEucnVuLmFwcCI7czo1OiJyb3V0ZSI7czoyNzoiZ2VuZXJhdGVkOjpMaGpzdnptbUxqbXNsYUJ2Ijt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1791695393);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `suscripciones`
--

DROP TABLE IF EXISTS `suscripciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `suscripciones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dependencia_id` bigint unsigned NOT NULL,
  `licencia_id` bigint unsigned DEFAULT NULL,
  `proveedor` varchar(50) DEFAULT 'interno',
  `proveedor_suscripcion_id` varchar(150) DEFAULT NULL,
  `mercadopago_payment_id` varchar(100) DEFAULT NULL,
  `mercadopago_preference_id` varchar(100) DEFAULT NULL,
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
  KEY `suscripciones_licencia_id_foreign` (`licencia_id`),
  CONSTRAINT `fk_suscripcion_dependencia` FOREIGN KEY (`dependencia_id`) REFERENCES `dependencias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `suscripciones_licencia_id_foreign` FOREIGN KEY (`licencia_id`) REFERENCES `licencias` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suscripciones`
--

LOCK TABLES `suscripciones` WRITE;
/*!40000 ALTER TABLE `suscripciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `suscripciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ticket_historial`
--

DROP TABLE IF EXISTS `ticket_historial`;
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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ticket_historial`
--

LOCK TABLES `ticket_historial` WRITE;
/*!40000 ALTER TABLE `ticket_historial` DISABLE KEYS */;
INSERT INTO `ticket_historial` VALUES (1,1,1,'abierto','resuelto',1,'2026-09-05 06:05:47'),(2,1,1,'abierto','resuelto',1,'2026-09-05 06:05:47'),(3,1,2,'abierto','en_proceso',1,'2026-09-05 06:53:09'),(4,1,2,'en_proceso','resuelto',1,'2026-09-05 06:53:12'),(5,1,2,'en_proceso','resuelto',1,'2026-09-05 06:53:13'),(6,1,1,'resuelto','en_proceso',1,'2026-09-05 08:47:14'),(7,1,1,'resuelto','en_proceso',1,'2026-09-05 08:47:14'),(8,1,1,'en_proceso','resuelto',1,'2026-09-05 08:47:15'),(9,1,1,'en_proceso','resuelto',1,'2026-09-05 08:47:17');
/*!40000 ALTER TABLE `ticket_historial` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tickets`
--

DROP TABLE IF EXISTS `tickets`;
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

--
-- Dumping data for table `tickets`
--

LOCK TABLES `tickets` WRITE;
/*!40000 ALTER TABLE `tickets` DISABLE KEYS */;
INSERT INTO `tickets` VALUES (1,1,1,NULL,'TKT-2026-001','Falla prensa hidraulica','Prueba real',NULL,NULL,'alta','resuelto','2026-09-05 00:05:47','2026-09-05 06:05:47',NULL,NULL,3,'2026-09-05 06:05:47','2026-09-12 16:12:31',NULL),(2,1,NULL,NULL,'TKT-NOTIF-001','Prueba notif real','test',NULL,NULL,'alta','resuelto','2026-09-05 00:53:09','2026-09-05 06:53:13',NULL,NULL,3,'2026-09-05 06:53:09','2026-09-05 06:53:13',NULL);
/*!40000 ALTER TABLE `tickets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
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
  `current_device` varchar(36) DEFAULT NULL,
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

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,1,'EMP001',1,'Admin','Tecnico','admin.tec@core.com',NULL,'$2y$10$coxnnNdNrgTTjHhQgA/GWun/GWkGqGB0orLXJbsf8y2ZXBKLR13Qu',1,'014c4ed6-9eda-4f60-a3f6-8d8f500a00f6','2026-10-11 05:24:33',NULL,'2026-09-05 06:03:21','2026-10-11 05:24:33',NULL),(2,1,'EMP002',2,'Admin','Comercial','admin.com@core.com',NULL,'$2y$12$w3QJD4QHiBKkI1rYTWOxwOIMJl7RSlxFmT0shWRjysskAmfW76.Mq',1,NULL,'2026-09-05 08:45:58',NULL,'2026-09-05 06:03:21','2026-09-11 23:43:07',NULL),(3,1,'EMP003',3,'Juan','Tecnico','tecnico@core.com',NULL,'$2y$12$RQ2iMmieeIjl2WcaPZhw8u3HvS5JQP.oVp/RLJOA7fMUkxx4j.8za',1,'9c772391-43b0-4597-aa96-2e081001395c','2026-09-12 16:14:58',NULL,'2026-09-05 06:03:21','2026-09-12 16:14:58',NULL),(4,1,'EMP004',0,'Cliente','Demo','cliente@core.com',NULL,'$2y$12$ZsAC2CVUuXubgGUgMiExNOL91A1Owd1E9EgMfXnrTClfBV7THMc7O',1,NULL,NULL,NULL,'2026-09-05 06:03:21','2026-09-11 23:43:09',NULL);
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary view structure for view `vw_conteo_operacion`
--

DROP TABLE IF EXISTS `vw_conteo_operacion`;
/*!50001 DROP VIEW IF EXISTS `vw_conteo_operacion`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vw_conteo_operacion` AS SELECT 
 1 AS `dependencia_id`,
 1 AS `cantidad_reportes_generados`,
 1 AS `cantidad_servicios_programados`,
 1 AS `cantidad_tickets_levantados`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `vw_eficacia_calidad`
--

DROP TABLE IF EXISTS `vw_eficacia_calidad`;
/*!50001 DROP VIEW IF EXISTS `vw_eficacia_calidad`*/;
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

--
-- Temporary view structure for view `vw_horas_hombre`
--

DROP TABLE IF EXISTS `vw_horas_hombre`;
/*!50001 DROP VIEW IF EXISTS `vw_horas_hombre`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vw_horas_hombre` AS SELECT 
 1 AS `reporte_id`,
 1 AS `dependencia_id`,
 1 AS `fecha_inicio`,
 1 AS `fecha_fin`,
 1 AS `minutos`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `vw_horas_por_tecnico`
--

DROP TABLE IF EXISTS `vw_horas_por_tecnico`;
/*!50001 DROP VIEW IF EXISTS `vw_horas_por_tecnico`*/;
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

--
-- Temporary view structure for view `vw_proactivo_reactivo`
--

DROP TABLE IF EXISTS `vw_proactivo_reactivo`;
/*!50001 DROP VIEW IF EXISTS `vw_proactivo_reactivo`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vw_proactivo_reactivo` AS SELECT 
 1 AS `reporte_id`,
 1 AS `dependencia_id`,
 1 AS `fecha_inicio`,
 1 AS `tipo`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `vw_reportes_categoria`
--

DROP TABLE IF EXISTS `vw_reportes_categoria`;
/*!50001 DROP VIEW IF EXISTS `vw_reportes_categoria`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vw_reportes_categoria` AS SELECT 
 1 AS `reporte_id`,
 1 AS `dependencia_id`,
 1 AS `fecha_inicio`,
 1 AS `categoria`,
 1 AS `tipo`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `vw_reportes_retrabajo`
--

DROP TABLE IF EXISTS `vw_reportes_retrabajo`;
/*!50001 DROP VIEW IF EXISTS `vw_reportes_retrabajo`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vw_reportes_retrabajo` AS SELECT 
 1 AS `reporte_id`,
 1 AS `dependencia_id`,
 1 AS `fecha_inicio`,
 1 AS `es_retrabajo`,
 1 AS `reporte_origen_id`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `vw_servicios_realizados_a_tiempo`
--

DROP TABLE IF EXISTS `vw_servicios_realizados_a_tiempo`;
/*!50001 DROP VIEW IF EXISTS `vw_servicios_realizados_a_tiempo`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vw_servicios_realizados_a_tiempo` AS SELECT 
 1 AS `dependencia_id`,
 1 AS `cantidad_sp_realizados_en_tiempo`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `vw_servicios_vencidos`
--

DROP TABLE IF EXISTS `vw_servicios_vencidos`;
/*!50001 DROP VIEW IF EXISTS `vw_servicios_vencidos`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vw_servicios_vencidos` AS SELECT 
 1 AS `dependencia_id`,
 1 AS `cantidad_sp_vencidos`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `vw_ticket_mttr`
--

DROP TABLE IF EXISTS `vw_ticket_mttr`;
/*!50001 DROP VIEW IF EXISTS `vw_ticket_mttr`*/;
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

--
-- Temporary view structure for view `vw_ticket_sla`
--

DROP TABLE IF EXISTS `vw_ticket_sla`;
/*!50001 DROP VIEW IF EXISTS `vw_ticket_sla`*/;
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

--
-- Temporary view structure for view `vw_tiempos_operativos`
--

DROP TABLE IF EXISTS `vw_tiempos_operativos`;
/*!50001 DROP VIEW IF EXISTS `vw_tiempos_operativos`*/;
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

--
-- Temporary view structure for view `vw_volumen_operacion`
--

DROP TABLE IF EXISTS `vw_volumen_operacion`;
/*!50001 DROP VIEW IF EXISTS `vw_volumen_operacion`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vw_volumen_operacion` AS SELECT 
 1 AS `reporte_id`,
 1 AS `dependencia_id`,
 1 AS `fecha_inicio`,
 1 AS `tipo`*/;
SET character_set_client = @saved_cs_client;

--
-- Dumping events for database 'railway'
--

--
-- Dumping routines for database 'railway'
--

--
-- Final view structure for view `vw_conteo_operacion`
--

/*!50001 DROP VIEW IF EXISTS `vw_conteo_operacion`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013  SQL SECURITY DEFINER */
/*!50001 VIEW `vw_conteo_operacion` AS select `d`.`id` AS `dependencia_id`,(select count(0) from `reportes` `r` where ((`r`.`dependencia_id` = `d`.`id`) and (`r`.`deleted_at` is null))) AS `cantidad_reportes_generados`,(select count(0) from `servicios` `s` where ((`s`.`dependencia_id` = `d`.`id`) and (`s`.`deleted_at` is null))) AS `cantidad_servicios_programados`,(select count(0) from `tickets` `t` where ((`t`.`dependencia_id` = `d`.`id`) and (`t`.`deleted_at` is null))) AS `cantidad_tickets_levantados` from `dependencias` `d` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_eficacia_calidad`
--

/*!50001 DROP VIEW IF EXISTS `vw_eficacia_calidad`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013  SQL SECURITY DEFINER */
/*!50001 VIEW `vw_eficacia_calidad` AS select `r`.`id` AS `reporte_id`,`r`.`dependencia_id` AS `dependencia_id`,`r`.`fecha_inicio` AS `fecha_inicio`,`r`.`ftfr` AS `ftfr`,(case when (`r`.`conformidad_estatus` = 'aprobado') then 1 when (`r`.`conformidad_estatus` = 'pendiente') then NULL else 0 end) AS `conforme`,(case when (`r`.`estatus` = 'descartado') then 1 else 0 end) AS `descartado`,(case when (`r`.`estatus` in ('abierto','parcial')) then 1 else 0 end) AS `abierta` from `reportes` `r` where (`r`.`deleted_at` is null) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_horas_hombre`
--

/*!50001 DROP VIEW IF EXISTS `vw_horas_hombre`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013  SQL SECURITY DEFINER */
/*!50001 VIEW `vw_horas_hombre` AS select `r`.`id` AS `reporte_id`,`r`.`dependencia_id` AS `dependencia_id`,`r`.`fecha_inicio` AS `fecha_inicio`,`r`.`fecha_fin` AS `fecha_fin`,`r`.`minutos_trabajados` AS `minutos` from `reportes` `r` where (`r`.`deleted_at` is null) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_horas_por_tecnico`
--

/*!50001 DROP VIEW IF EXISTS `vw_horas_por_tecnico`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013  SQL SECURITY DEFINER */
/*!50001 VIEW `vw_horas_por_tecnico` AS select `re`.`id` AS `ejecutor_registro_id`,`re`.`dependencia_id` AS `dependencia_id`,`re`.`reporte_id` AS `reporte_id`,`re`.`usuario_id` AS `usuario_id`,`r`.`fecha_inicio` AS `fecha_inicio`,`r`.`fecha_fin` AS `fecha_fin`,coalesce(`re`.`minutos_invertidos`,`r`.`minutos_trabajados`) AS `minutos` from (`reporte_ejecutores` `re` join `reportes` `r` on((`r`.`id` = `re`.`reporte_id`))) where (`r`.`deleted_at` is null) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_proactivo_reactivo`
--

/*!50001 DROP VIEW IF EXISTS `vw_proactivo_reactivo`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013  SQL SECURITY DEFINER */
/*!50001 VIEW `vw_proactivo_reactivo` AS select `r`.`id` AS `reporte_id`,`r`.`dependencia_id` AS `dependencia_id`,`r`.`fecha_inicio` AS `fecha_inicio`,`r`.`tipo` AS `tipo` from `reportes` `r` where ((`r`.`deleted_at` is null) and (`r`.`tipo` in ('servicio','ticket'))) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_reportes_categoria`
--

/*!50001 DROP VIEW IF EXISTS `vw_reportes_categoria`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013  SQL SECURITY DEFINER */
/*!50001 VIEW `vw_reportes_categoria` AS select `r`.`id` AS `reporte_id`,`r`.`dependencia_id` AS `dependencia_id`,`r`.`fecha_inicio` AS `fecha_inicio`,`r`.`categoria` AS `categoria`,`r`.`tipo` AS `tipo` from `reportes` `r` where (`r`.`deleted_at` is null) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_reportes_retrabajo`
--

/*!50001 DROP VIEW IF EXISTS `vw_reportes_retrabajo`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013  SQL SECURITY DEFINER */
/*!50001 VIEW `vw_reportes_retrabajo` AS select `r`.`id` AS `reporte_id`,`r`.`dependencia_id` AS `dependencia_id`,`r`.`fecha_inicio` AS `fecha_inicio`,`r`.`es_retrabajo` AS `es_retrabajo`,`r`.`reporte_origen_id` AS `reporte_origen_id` from `reportes` `r` where (`r`.`deleted_at` is null) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_servicios_realizados_a_tiempo`
--

/*!50001 DROP VIEW IF EXISTS `vw_servicios_realizados_a_tiempo`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013  SQL SECURITY DEFINER */
/*!50001 VIEW `vw_servicios_realizados_a_tiempo` AS select `s`.`dependencia_id` AS `dependencia_id`,count(0) AS `cantidad_sp_realizados_en_tiempo` from `servicios` `s` where ((`s`.`deleted_at` is null) and (`s`.`estatus` = 'realizado') and (`s`.`fecha_vencimiento` >= cast(`s`.`updated_at` as date))) group by `s`.`dependencia_id` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_servicios_vencidos`
--

/*!50001 DROP VIEW IF EXISTS `vw_servicios_vencidos`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013  SQL SECURITY DEFINER */
/*!50001 VIEW `vw_servicios_vencidos` AS select `s`.`dependencia_id` AS `dependencia_id`,count(0) AS `cantidad_sp_vencidos` from `servicios` `s` where ((`s`.`deleted_at` is null) and ((`s`.`estatus` = 'vencido') or ((`s`.`fecha_vencimiento` < curdate()) and (`s`.`estatus` <> 'realizado')))) group by `s`.`dependencia_id` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_ticket_mttr`
--

/*!50001 DROP VIEW IF EXISTS `vw_ticket_mttr`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013  SQL SECURITY DEFINER */
/*!50001 VIEW `vw_ticket_mttr` AS select `t`.`id` AS `ticket_id`,`t`.`dependencia_id` AS `dependencia_id`,`t`.`equipo_id` AS `equipo_id`,`t`.`fecha_solicitud` AS `fecha_solicitud`,`t`.`fecha_atencion` AS `fecha_atencion`,timestampdiff(HOUR,`t`.`fecha_solicitud`,coalesce(`t`.`fecha_atencion`,now())) AS `horas_reparacion` from `tickets` `t` where ((`t`.`deleted_at` is null) and (`t`.`estatus` in ('resuelto','cerrado'))) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_ticket_sla`
--

/*!50001 DROP VIEW IF EXISTS `vw_ticket_sla`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013  SQL SECURITY DEFINER */
/*!50001 VIEW `vw_ticket_sla` AS select `t`.`id` AS `ticket_id`,`t`.`dependencia_id` AS `dependencia_id`,`t`.`prioridad` AS `prioridad`,`t`.`fecha_solicitud` AS `fecha_solicitud`,`t`.`fecha_limite` AS `fecha_limite`,`t`.`fecha_atencion` AS `fecha_atencion`,(case when (`t`.`fecha_atencion` is null) then NULL when (`t`.`fecha_limite` is null) then NULL when (`t`.`fecha_atencion` <= `t`.`fecha_limite`) then 1 else 0 end) AS `a_tiempo` from `tickets` `t` where (`t`.`deleted_at` is null) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_tiempos_operativos`
--

/*!50001 DROP VIEW IF EXISTS `vw_tiempos_operativos`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013  SQL SECURITY DEFINER */
/*!50001 VIEW `vw_tiempos_operativos` AS select `r`.`id` AS `reporte_id`,`r`.`dependencia_id` AS `dependencia_id`,`r`.`fecha_inicio` AS `fecha_inicio`,timestampdiff(HOUR,`r`.`hora_salida`,`r`.`hora_llegada`) AS `t_atencion_horas`,timestampdiff(HOUR,`r`.`hora_llegada`,`r`.`hora_inicio_trabajo`) AS `t_diagnostico_horas`,timestampdiff(HOUR,`r`.`hora_salida`,`r`.`hora_regreso`) AS `t_utilizacion_horas` from `reportes` `r` where (`r`.`deleted_at` is null) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_volumen_operacion`
--

/*!50001 DROP VIEW IF EXISTS `vw_volumen_operacion`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013  SQL SECURITY DEFINER */
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

-- Dump completed on 2026-10-16  4:38:23

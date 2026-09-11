-- 04_Encuestas_y_Facturacion.sql
-- Backlog 1 y 2: encuestas de satisfacción + datos fiscales de dependencias
-- Ejecutar después de Tablas.sql y 02/03 complementos. Idempotente con IF NOT EXISTS donde aplica.

USE CoreReports;

-- 1. Encuestas de satisfacción (separada de conformidad)
CREATE TABLE IF NOT EXISTS encuestas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dependencia_id BIGINT UNSIGNED NOT NULL,
    reporte_id BIGINT UNSIGNED NOT NULL,

    calificacion TINYINT UNSIGNED NOT NULL COMMENT '1-5',
    comentario TEXT,

    respondido_por VARCHAR(150) NULL COMMENT 'Nombre del cliente que califica, puede no tener cuenta',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_encuesta_dependencia
        FOREIGN KEY (dependencia_id) REFERENCES dependencias(id) ON DELETE CASCADE,
    CONSTRAINT fk_encuesta_reporte
        FOREIGN KEY (reporte_id) REFERENCES reportes(id) ON DELETE CASCADE,

    UNIQUE KEY uq_encuesta_reporte (reporte_id),
    CONSTRAINT chk_calificacion CHECK (calificacion BETWEEN 1 AND 5),
    INDEX idx_encuesta_dependencia (dependencia_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 2. Datos fiscales de dependencias - columnas explícitas
-- Si ya existen, estas alteraciones son no-op (MySQL 8 ignora ADD COLUMN IF NOT EXISTS en 8.0.23+;
-- para compatibilidad se usa procedimiento con INFORMATION_SCHEMA).

SET @tbl = 'dependencias';
SET @db = DATABASE();

-- regimen_fiscal
SET @col = 'regimen_fiscal';
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME=@col)=0,
    'ALTER TABLE dependencias ADD COLUMN regimen_fiscal VARCHAR(10) NULL COMMENT ''Clave SAT, ej. 601, 612''',
    'SELECT ''regimen_fiscal ya existe''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- uso_cfdi
SET @col = 'uso_cfdi';
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME=@col)=0,
    'ALTER TABLE dependencias ADD COLUMN uso_cfdi VARCHAR(10) NULL COMMENT ''Clave SAT, ej. G03, P01''',
    'SELECT ''uso_cfdi ya existe''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- calle
SET @col = 'calle';
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME=@col)=0,
    'ALTER TABLE dependencias ADD COLUMN calle VARCHAR(150) NULL',
    'SELECT ''calle ya existe''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- colonia
SET @col = 'colonia';
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME=@col)=0,
    'ALTER TABLE dependencias ADD COLUMN colonia VARCHAR(100) NULL',
    'SELECT ''colonia ya existe''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- codigo_postal
SET @col = 'codigo_postal';
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME=@col)=0,
    'ALTER TABLE dependencias ADD COLUMN codigo_postal VARCHAR(10) NULL',
    'SELECT ''codigo_postal ya existe''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ciudad
SET @col = 'ciudad';
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME=@col)=0,
    'ALTER TABLE dependencias ADD COLUMN ciudad VARCHAR(100) NULL',
    'SELECT ''ciudad ya existe''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- estado
SET @col = 'estado';
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME=@col)=0,
    'ALTER TABLE dependencias ADD COLUMN estado VARCHAR(100) NULL',
    'SELECT ''estado ya existe''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- municipio
SET @col = 'municipio';
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME=@col)=0,
    'ALTER TABLE dependencias ADD COLUMN municipio VARCHAR(100) NULL',
    'SELECT ''municipio ya existe''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- metodo_pago
SET @col = 'metodo_pago';
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME=@col)=0,
    'ALTER TABLE dependencias ADD COLUMN metodo_pago VARCHAR(10) NULL COMMENT ''Clave SAT, ej. PUE, PPD''',
    'SELECT ''metodo_pago ya existe''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- forma_pago
SET @col = 'forma_pago';
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME=@col)=0,
    'ALTER TABLE dependencias ADD COLUMN forma_pago VARCHAR(10) NULL COMMENT ''Clave SAT, ej. 03, 04''',
    'SELECT ''forma_pago ya existe''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- datos_facturacion TEXT se conserva como notas adicionales; no se elimina.
-- Si hay datos previos en datos_facturacion como texto libre, migrarlos manualmente antes de normalizar.

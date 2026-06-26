-- ============================================================
-- GPSP — Tabla de Pre-Registro de Vehículos IVMS v2
-- Versión: V2.0 | Fecha: 2026-06-21
-- Compatible: MySQL 8.0.46 (Ubuntu) / PHP 8.3.6
-- Campos ADAS alineados con manual Howen Hero-ME32-04
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS `preregistro_ivms` (

  -- Identificación
  `id`                      INT            NOT NULL AUTO_INCREMENT,
  `codigo_registro`         VARCHAR(25)    NOT NULL COMMENT 'Cód. único: PREG-YYYYMMDD-XXXXXX',

  -- Datos del vehículo
  `placa`                   VARCHAR(20)    NOT NULL,
  `codigo_camion`           VARCHAR(50)    DEFAULT NULL COMMENT 'Código interno cliente (ej. Orica)',
  `marca`                   VARCHAR(80)    NOT NULL,
  `modelo`                  VARCHAR(80)    NOT NULL,
  `anio`                    SMALLINT       DEFAULT NULL,
  `flota`                   VARCHAR(120)   DEFAULT NULL COMMENT 'Flota u operación',

  -- Telemetría
  `imei_galileosky`         VARCHAR(20)    DEFAULT NULL,
  `chip_galileosky`         VARCHAR(30)    DEFAULT NULL COMMENT 'ICCID o número SIM',
  `imei_mdvr`               VARCHAR(20)    DEFAULT NULL COMMENT 'IMEI MDVR Howen Hero-ME32-04',
  `chip_mdvr`               VARCHAR(30)    DEFAULT NULL,
  `imei_iridium`            VARCHAR(20)    DEFAULT NULL,
  `chip_tablet`             VARCHAR(30)    DEFAULT NULL COMMENT 'SIM Tablet MDT Hero-AT5 V2',
  `mac_sensor_angulo`       VARCHAR(20)    DEFAULT NULL COMMENT 'MAC Bluetooth sensor DU-BLE',

  -- Medidas generales del camión
  `medida_largo`            DECIMAL(6,2)   DEFAULT NULL COMMENT 'Largo total (metros)',
  `medida_ancho`            DECIMAL(6,2)   DEFAULT NULL COMMENT 'Ancho total (metros)',
  `medida_alto`             DECIMAL(6,2)   DEFAULT NULL COMMENT 'Alto total (metros)',
  `medida_notas`            TEXT           DEFAULT NULL,

  -- Calibración ADAS — Howen Hero-ME32-04
  -- Todos los campos en centímetros (cm) salvo píxeles
  `adas_altura_camara`      DECIMAL(7,2)   DEFAULT NULL
    COMMENT 'Camera Height: distancia del suelo al centro de la cámara ADAS (cm) — Fig.5 Howen',
  `adas_ancho_vehiculo`     DECIMAL(7,2)   DEFAULT NULL
    COMMENT 'Car Width: ancho entre bordes externos de neumáticos (cm) — Fig.4 Howen',
  `adas_camara_parachoque`  DECIMAL(7,2)   DEFAULT NULL
    COMMENT 'Camera2Bumper: distancia cámara → parachoque delantero (cm) — Fig.5 Howen',
  `adas_camara_eje`         DECIMAL(7,2)   DEFAULT NULL
    COMMENT 'Camera2Axle: distancia cámara → eje delantero (cm, negativo si eje queda detrás) — Fig.6 Howen',
  `adas_camera_center`      DECIMAL(7,2)   DEFAULT '0.00'
    COMMENT 'Camera Center: offset lateral desde centro; siempre 0 según manual Howen',
  `adas_horizonte_px`       SMALLINT       DEFAULT NULL
    COMMENT 'Horizonte (píxel): se obtiene durante calibración en software Howen, no se mide físicamente',
  `adas_linea_central_px`   SMALLINT       DEFAULT NULL
    COMMENT 'Línea Central (píxel): se obtiene durante calibración, no se mide físicamente',

  -- Control
  `estado`                  ENUM('pendiente','en_proceso','completado','cancelado')
                                           NOT NULL DEFAULT 'pendiente',
  `registrado_por`          VARCHAR(120)   DEFAULT NULL,
  `ip_origen`               VARCHAR(45)    DEFAULT NULL,
  `observaciones`           TEXT           DEFAULT NULL,

  -- Auditoría
  `created_at`              TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`              TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_codigo_registro` (`codigo_registro`),
  UNIQUE KEY `uk_placa`           (`placa`),
  KEY `idx_imei_galileosky`       (`imei_galileosky`),
  KEY `idx_imei_mdvr`             (`imei_mdvr`),
  KEY `idx_estado`                (`estado`),
  KEY `idx_created_at`            (`created_at`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Pre-registro de vehículos IVMS — GPSPhonnex / Orica v2';


-- ============================================================
-- Script ALTER para actualizar tabla existente (si ya existe v1)
-- Ejecutar SOLO si ya tienes la tabla de la versión anterior
-- ============================================================
/*
ALTER TABLE `preregistro_ivms`
  -- Renombrar campo antiguo si existía
  -- ADD COLUMN IF NOT EXISTS ... usa sintaxis MariaDB; en MySQL 8 usar:
  ADD COLUMN `adas_camera_center`    DECIMAL(7,2) DEFAULT '0.00' AFTER `adas_camara_eje`,
  ADD COLUMN `adas_horizonte_px`     SMALLINT     DEFAULT NULL   AFTER `adas_camera_center`,
  ADD COLUMN `adas_linea_central_px` SMALLINT     DEFAULT NULL   AFTER `adas_horizonte_px`;
*/

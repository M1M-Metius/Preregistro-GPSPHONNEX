-- ============================================================
-- GPSP — Columnas de fotos individuales (8 fotos)
-- Versión: V4.1 | 2026-06-23
-- Una foto por cada campo de telemetría
-- ============================================================

ALTER TABLE `preregistro_ivms`
  ADD COLUMN IF NOT EXISTS `foto_imei_galileosky`   VARCHAR(255) DEFAULT NULL COMMENT 'Foto etiqueta IMEI Galileosky'    AFTER `mac_sensor_angulo`,
  ADD COLUMN IF NOT EXISTS `foto_chip_galileosky`   VARCHAR(255) DEFAULT NULL COMMENT 'Foto etiqueta Chip/SIM Galileosky' AFTER `foto_imei_galileosky`,
  ADD COLUMN IF NOT EXISTS `foto_imei_mdvr`         VARCHAR(255) DEFAULT NULL COMMENT 'Foto etiqueta IMEI MDVR'          AFTER `foto_chip_galileosky`,
  ADD COLUMN IF NOT EXISTS `foto_chip_mdvr`         VARCHAR(255) DEFAULT NULL COMMENT 'Foto etiqueta Chip/SIM MDVR'      AFTER `foto_imei_mdvr`,
  ADD COLUMN IF NOT EXISTS `foto_imei_iridium`      VARCHAR(255) DEFAULT NULL COMMENT 'Foto etiqueta IMEI Iridium'       AFTER `foto_chip_mdvr`,
  ADD COLUMN IF NOT EXISTS `foto_chip_tablet`       VARCHAR(255) DEFAULT NULL COMMENT 'Foto etiqueta Chip/SIM Tablet'    AFTER `foto_imei_iridium`,
  ADD COLUMN IF NOT EXISTS `foto_mac_sensor_angulo` VARCHAR(255) DEFAULT NULL COMMENT 'Foto etiqueta MAC Sensor DU-BLE'  AFTER `foto_chip_tablet`,
  ADD COLUMN IF NOT EXISTS `foto_vehiculo`          VARCHAR(255) DEFAULT NULL COMMENT 'Foto placa / vehículo'            AFTER `foto_mac_sensor_angulo`;

-- Crear carpeta de uploads en el servidor:
-- mkdir -p /var/www/html/tu-carpeta/uploads/fotos_ivms
-- chown www-data:www-data /var/www/html/tu-carpeta/uploads
-- chmod 755 /var/www/html/tu-carpeta/uploads

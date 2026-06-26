-- ============================================================
-- GPSP — Actualizaciones BD v3.0 | 2026-06-22
-- Ejecutar en la BD 'gps' en el servidor
-- ============================================================

-- 1. Agregar columna pais_iso a preregistro_ivms (si no existe)
ALTER TABLE `preregistro_ivms`
  ADD COLUMN IF NOT EXISTS `pais_iso` VARCHAR(3) DEFAULT NULL
    COMMENT 'País de origen: PE=Perú, CL=Chile, BR=Brasil'
    AFTER `flota`;

-- 2. Agregar columna updated_at (si no existe)
ALTER TABLE `preregistro_ivms`
  ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP NOT NULL
    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    AFTER `created_at`;

-- Índice para filtrar por país
ALTER TABLE `preregistro_ivms`
  ADD INDEX IF NOT EXISTS `idx_pais_iso` (`pais_iso`);

-- ============================================================
-- 3. Crear usuarios por país (ajusta las contraseñas)
--    Las contraseñas están en MD5 — el PHP las migrará a
--    password_hash automáticamente en el primer login.
--
--    Contraseñas iniciales sugeridas (cámbialas antes de compartir):
--      chilepreivms  → Chile2026!
--      perupreivms   → Peru2026!
--      brasilpreivms → Brasil2026!
-- ============================================================

-- us_tipo: 3 = técnico/usuario país (solo ve su país)
-- id_emp: usar el que corresponda a cada operación Orica

INSERT INTO `usuario`
  (no_nombre, n_pass, id_usua, id_emp, us_tipo, fe_crea, id_est_usu, failed_attempts)
VALUES
  ('Pre-Registro Chile',
   MD5('Chile2026!'),
   'chilepreivms',
   '5857',   -- id_emp Chile Orica — ajusta si es diferente
   3, CURDATE(), 'activo', 0),

  ('Pre-Registro Perú',
   MD5('Peru2026!'),
   'perupreivms',
   '5802',   -- id_emp Perú Orica
   3, CURDATE(), 'activo', 0),

  ('Pre-Registro Brasil',
   MD5('Brasil2026!'),
   'brasilpreivms',
   '5876',   -- id_emp Brasil Orica
   3, CURDATE(), 'activo', 0);

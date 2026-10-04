-- Sabrosísimo Mix CMS
-- UPDATE DB COMPLETO - Validación de correo, API futura y protección antispam
-- Compatible con la base REAL existente del proyecto.
--
-- IMPORTANTE:
-- * Este script NO modifica la estructura de admin_roles.
-- * La tabla admin_roles de este proyecto usa únicamente: id, role_name.
-- * NO usa columnas ajenas como role_key, description, is_system o active.
-- * No elimina tablas ni datos existentes.
-- * Puede ejecutarse más de una vez de forma segura.

SET NAMES utf8mb4;

-- --------------------------------------------------------
-- 1. Rate limit persistente para endpoints públicos
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS public_rate_limits (
  bucket_key CHAR(64) NOT NULL,
  bucket VARCHAR(80) NOT NULL,
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  window_started_at DATETIME NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (bucket_key),
  KEY idx_public_rate_limits_bucket (bucket),
  KEY idx_public_rate_limits_window (window_started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- 2. Auditoría técnica de validaciones de correo
--    No almacena el correo en texto plano.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS email_validation_events (
  id BIGINT NOT NULL AUTO_INCREMENT,
  email_hash CHAR(64) NOT NULL,
  domain VARCHAR(253) NOT NULL DEFAULT '',
  result_status VARCHAR(40) NOT NULL,
  source VARCHAR(40) NOT NULL DEFAULT 'form',
  dns_checked TINYINT(1) NOT NULL DEFAULT 0,
  external_checked TINYINT(1) NOT NULL DEFAULT 0,
  external_provider VARCHAR(120) DEFAULT NULL,
  latency_ms INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_email_validation_created (created_at),
  KEY idx_email_validation_status (result_status),
  KEY idx_email_validation_domain (domain),
  KEY idx_email_validation_hash (email_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- 3. Configuración para API externa de validación de email
--    settings ya existe en la base actual con:
--      setting_key VARCHAR(150) PRIMARY KEY
--      setting_value LONGTEXT NULL
-- --------------------------------------------------------
INSERT INTO settings (setting_key, setting_value) VALUES
('email_validation_api_enabled', '0'),
('email_validation_api_name', ''),
('email_validation_api_url', ''),
('email_validation_api_method', 'GET'),
('email_validation_api_auth', 'bearer'),
('email_validation_api_key', ''),
('email_validation_api_key_name', 'api_key'),
('email_validation_api_email_field', 'email'),
('email_validation_api_timeout', '4')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

-- --------------------------------------------------------
-- 4. Permiso de analítica requerido por el CMS
--    Se usa el esquema REAL: permission_key + label.
--    No se toca admin_roles ni se requieren columnas nuevas.
-- --------------------------------------------------------
INSERT INTO admin_permissions (permission_key, label)
VALUES ('analytics.view', 'Website analytics')
ON DUPLICATE KEY UPDATE label = VALUES(label);

INSERT IGNORE INTO admin_role_permissions (role_id, permission_id)
SELECT 1, id
FROM admin_permissions
WHERE permission_key = 'analytics.view';

-- Fin de actualización.

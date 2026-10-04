-- Sabrosísimo Mix CMS - actualización acumulativa de seguridad de sesiones
-- Compatible con instalaciones existentes. No elimina usuarios ni cambia contraseñas.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS admin_sessions (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT NOT NULL,
  session_hash CHAR(64) NOT NULL UNIQUE,
  issued_at BIGINT UNSIGNED NOT NULL,
  last_activity_at BIGINT UNSIGNED NOT NULL,
  absolute_expires_at BIGINT UNSIGNED NOT NULL,
  user_agent_hash CHAR(64) NULL,
  revoked_at BIGINT UNSIGNED NULL,
  revoke_reason VARCHAR(60) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_admin_sessions_admin(admin_id),
  INDEX idx_admin_sessions_activity(last_activity_at),
  INDEX idx_admin_sessions_absolute(absolute_expires_at),
  CONSTRAINT fk_admin_sessions_admin FOREIGN KEY(admin_id) REFERENCES admin_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Las sesiones PHP anteriores no se migran deliberadamente.
-- Después de desplegar esta versión, los administradores deben autenticarse de nuevo
-- para que se cree un registro de sesión con vencimiento por inactividad y absoluto.

SABROSÍSIMO MIX CMS - SEGURIDAD DE AUTENTICACIÓN

Actualización de base de datos:
UPDATE_DB_AUTH_SECURITY_COMPLETO.sql

Cambios principales:
- 60 minutos de inactividad máxima.
- 12 horas de duración absoluta por sesión.
- Sesiones administrativas registradas y revocables en admin_sessions.
- Cookie de sesión HttpOnly, SameSite=Lax y Secure bajo HTTPS.
- PHP session.use_strict_mode habilitado cuando está disponible.
- /admin/ reanuda únicamente una sesión todavía válida.
- /admin/login.php fuerza una autenticación nueva y no restaura automáticamente una sesión antigua.
- AJAX/API reciben HTTP 401 cuando la sesión deja de ser válida.
- Logout revoca la sesión, elimina cookies y destruye la sesión PHP.
- Cambiar/restablecer contraseña revoca las sesiones administrativas previas.
- “Recordar usuario” conserva únicamente el identificador; nunca guarda contraseña ni token de autenticación.

IMPORTANTE:
Ejecutar el SQL en instalaciones existentes antes o junto con el despliegue. El código también intenta crear la tabla si el usuario MySQL tiene permiso CREATE.

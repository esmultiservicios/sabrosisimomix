SABROSÍSIMO MIX CMS - ACTUALIZACIÓN DE BASE DE DATOS

ARCHIVO A EJECUTAR EN LA BASE EXISTENTE:
UPDATE_DB_EMAIL_ANTISPAM_COMPLETO.sql

También se conserva una copia en:
install/UPDATE_DB_EMAIL_ANTISPAM_COMPLETO.sql

COMPATIBILIDAD:
- Preparado contra la estructura real proporcionada de esmultiservicios_sabrosisimo_mix.
- NO modifica admin_roles.
- NO usa role_key, description, is_system ni active.
- NO elimina tablas ni datos existentes.
- Puede ejecutarse más de una vez.
- Agrega public_rate_limits.
- Agrega email_validation_events.
- Agrega configuración futura de API de validación de correo en settings.
- Conserva analytics.view usando admin_permissions(permission_key, label).

IMPORTANTE:
Haz un respaldo de la base antes de ejecutar cualquier actualización en producción.

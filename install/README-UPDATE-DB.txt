SABROSÍSIMO MIX CMS - ACTUALIZACIÓN DE BASE DE DATOS

Archivo a ejecutar en instalaciones existentes:
  install/UPDATE_DB_EMAIL_ANTISPAM_COMPLETO.sql

Este SQL fue ajustado contra la estructura real de la base del proyecto.

IMPORTANTE:
- admin_roles usa únicamente id y role_name.
- Este update NO usa role_key.
- Este update NO usa description, is_system ni active en admin_roles.
- No borra tablas ni registros existentes.
- Puede ejecutarse nuevamente sin duplicar la configuración.
- Agrega únicamente la infraestructura necesaria para validación de correo,
  rate limiting persistente y auditoría técnica antispam.

Si phpMyAdmin muestra un error relacionado con role_key, NO corresponde a este
archivo. No ejecutes SQL de otro proyecto/Core CMS sobre esta base.

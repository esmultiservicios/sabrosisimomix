# Sabrosísimo Mix — CMS Premium

Proyecto creado sobre la estructura del CMS Core recibida, conservando sin modificar los archivos `admin/admin.css`, `admin/admin.js` y `admin/estimates.php` del paquete original y agregando el resto de la infraestructura necesaria para ejecutarlo.

## Requisitos
- PHP 8.1+
- MySQL/MariaDB
- PDO MySQL, OpenSSL, cURL, Fileinfo, JSON, Session, Filter, Hash
- ZIP recomendado para verificar adjuntos Office en respuestas de cotización

## Instalación
1. Subir todo el contenido al hosting.
2. Dar permisos de escritura a `config/` y `uploads/` durante la instalación.
3. Abrir el dominio. El sistema redirige a `/install/`.
4. Completar MySQL y crear el administrador.
5. En correo elegir:
   - **Configurar después**
   - **SMTP**: solo muestra campos SMTP
   - **Microsoft Graph**: solo muestra campos Graph
6. Puedes **Probar configuración de correo** sin guardar y, cuando todo esté correcto, usar **Guardar e instalar**.
7. Después de instalar, el correo sigue editable en **Administración → Email** con botón de prueba.

## Contenido inicial
La portada se creó a partir de la información disponible del cliente:
- Sabrosísimo Mix
- “Sabor y Servicio es nuestra pasión”
- San Pedro Sula
- 3273-5251 / 8809-9003
- Reuniones, festejos familiares, eventos empresariales y en casa
- Saltarines, taqueadas, pupusas, pastelitos de maíz, pastelitos de harina, snacks, nieves y palomitas de maíz

Las dos capturas entregadas se utilizaron únicamente como referencia. Se incluyó un recorte temporal del arte/logo disponible. Cuando el cliente entregue logos y fotografías originales pueden reemplazarse desde el proyecto sin cambiar la estructura.

## Módulos administrativos incluidos
- Dashboard
- Page Content
- Services
- Projects / Gallery
- Media Library
- Service Areas
- Tips
- Videos
- Estimate Requests avanzado
- SMTP / Microsoft Graph
- Settings
- Website Health

## Seguridad
- Contraseñas con `password_hash`
- CSRF en formularios
- PDO con consultas preparadas
- Credenciales de correo cifradas con AES-256-GCM usando una clave local generada al instalar
- Carpetas `config/` y `core/` bloqueadas por `.htaccess`
- Ejecución PHP bloqueada dentro de `uploads/`

## Control seguro de instalación (`config/install.lock`)

Este proyecto usa `config/install.lock` como bloqueo de instalación.

- Después de una instalación correcta, el CMS crea automáticamente `config/install.lock`.
- Mientras el archivo exista, `/install/` queda bloqueado y redirige al login del administrador.
- Para reinstalar desde cero **no es necesario borrar la base de datos manualmente**: elimina únicamente `config/install.lock` y abre nuevamente el sitio.
- Si `config/config.php` sigue presente, el asistente entra en **modo reinstalación**, reutiliza la conexión existente y elimina/recrea únicamente las tablas pertenecientes a este CMS.
- El instalador no ejecuta `DROP DATABASE` y no elimina tablas ajenas al CMS.
- El nuevo `config/install.lock` se genera solamente cuando toda la instalación finaliza correctamente.

## Acceso y recuperación de cuenta
- Al terminar la instalación, el login recibe precargado el correo del administrador creado.
- "Recordar usuario" guarda únicamente el identificador de acceso durante 30 días; nunca almacena la contraseña.
- Recuperación de contraseña disponible desde `admin/forgot-password.php` con token hash, vencimiento de 60 minutos y uso único.
- Si SMTP o Microsoft Graph está configurado al instalar, se envía un correo de bienvenida con la plantilla HTML central del CMS. Un fallo de ese correo no revierte la instalación.
- `showNotify`, `Swal.fire` compatible y `CMSDialog` son locales bajo `assets/vendor/`; no requieren CDN.

## Git Version Control de cPanel

El proyecto incluye `.cpanel.yml` y `.gitignore` preparados para despliegue manual con **Update from Remote** y **Deploy HEAD Commit**.

El despliegue evita comodines globales, no copia `.git`, no elimina contenido runtime y preserva archivos sensibles de producción como `config/config.php`, `config/install.lock`, `.env`, uploads, `.user.ini`, `php.ini`, `.well-known/`, logs, caché y backups.

El `.htaccess` raíz continúa versionado como referencia del proyecto, pero el despliegue solo lo copia si no existe todavía en producción para no destruir bloques que cPanel, MultiPHP o SSL puedan administrar.

Consulta `DEPLOYMENT-CPANEL.md` antes del primer despliegue.

## Editor de texto enriquecido local

Los campos de contenido basados en `textarea` se mejoran automáticamente con el editor premium local de `assets/vendor/richtext-local.js` y `assets/vendor/richtext-local.css`. El editor funciona sin CDN, sincroniza el HTML seguro con el `textarea` real antes de enviar el formulario y permite negrita, cursiva, subrayado, tachado, listas, citas, alineación, enlaces, deshacer/rehacer y limpieza de formato.

Por seguridad, el servidor vuelve a sanear el contenido mediante `rich_text_sanitize()` antes de almacenarlo. El campo de código de Floating Widgets se mantiene deliberadamente como editor de código y no como Rich Text para no alterar snippets HTML/JavaScript.


## Actualización SEO, Turnstile y Floating Widgets

- `admin/seo.php` centraliza título/meta descripción, Google Site Verification, indexación, imagen social, `robots.txt`, `sitemap.xml` y Cloudflare Turnstile.
- Cloudflare Turnstile se aplica al formulario público únicamente cuando está activado y existen Site Key + Secret Key válidas.
- `admin/widgets.php` permite mantener WhatsApp y agregar múltiples widgets externos por código de instalación o URL embebible. Los widgets externos se fuerzan al lado contrario de WhatsApp para evitar cruces.
- Los cambios usan la tabla genérica `settings`; no requieren migración ni cambio de esquema SQL.

## Protección avanzada del formulario público

El formulario de cotización valida el correo en frontend y backend antes de aceptar una solicitud. Incluye formato, sugerencias de dominios frecuentes, existencia del dominio, registros MX, bloqueo de proveedores temporales, honeypot, rate limit por IP/sesión, detección básica de contenido automatizado y soporte para Cloudflare Turnstile.

Existe además un endpoint interno `validate-email.php` para la validación en tiempo real. Desde **Admin > SEO Manager** puede activarse opcionalmente un servicio externo de validación de buzón mediante URL/API Key. Si ese proveedor externo falla o queda fuera de línea, el sistema aplica fallback y no bloquea automáticamente a un cliente legítimo.

No se envía ningún correo de confirmación al visitante para verificar su dirección.

## Validación externa de correo y actualización de base de datos

El filtro antispam queda preparado para integrar un proveedor externo de validación de buzón sin enviar correos al visitante. Desde **Admin > SEO Manager** se puede configurar nombre del proveedor, URL, método GET/POST, autenticación Bearer, X-API-Key, parámetro, API Key, nombre del campo de correo y timeout. Si el proveedor falla, la validación local continúa y el cliente legítimo no se bloquea automáticamente.

Para instalaciones existentes se incluye `install/UPDATE_DB_EMAIL_ANTISPAM_COMPLETO.sql`. La actualización crea únicamente las estructuras auxiliares de rate limit y auditoría de validaciones, además de registrar los valores de configuración de la API si todavía no existen. No elimina ni reemplaza información actual.

El código también crea estas tablas de forma segura con `CREATE TABLE IF NOT EXISTS` cuando se usa el formulario, de modo que una instalación existente puede seguir funcionando aunque el SQL todavía no se haya ejecutado manualmente, siempre que el usuario MySQL tenga permiso `CREATE`.

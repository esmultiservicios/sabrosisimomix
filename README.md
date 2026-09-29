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

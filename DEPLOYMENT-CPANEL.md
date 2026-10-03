# Despliegue seguro con Git Version Control de cPanel

Este proyecto incluye un `.cpanel.yml` versionado en la raíz para utilizar **Update from Remote** y **Deploy HEAD Commit**.

## Document Root configurado

El despliegue está preparado para:

```text
/home/esmultiservicios/sabrosisimomix.esmultiservicios.com/
```

Antes del primer despliegue, confirma en **cPanel → Domains** que ese valor coincide exactamente con el **Document Root** del subdominio. Si cPanel muestra otra ruta, corrige únicamente la línea `DEPLOYPATH` de `.cpanel.yml`, haz commit y vuelve a actualizar el repositorio administrado por cPanel.

## Qué despliega

El despliegue copia de forma explícita:

- `index.php`
- `quote.php`
- `admin/`
- `assets/`
- `core/`
- `install/`
- `config/`
- `uploads/`

No usa `*`, por lo que no copia `.git`, `.gitignore` ni `.cpanel.yml` al Document Root.

La copia de carpetas es incremental: no usa `--delete` y no elimina archivos runtime existentes.

## Archivos de producción que se preservan

El repositorio ignora y el despliegue no elimina:

- `config/config.php`
- `config/install.lock`
- `.env` y secretos
- archivos subidos dentro de `uploads/`
- `.user.ini`
- `php.ini`
- `.well-known/`
- `error_log`
- logs
- cache
- temporales
- backups

El `.htaccess` raíz permanece versionado, pero el despliegue automático solo lo copia si todavía no existe en producción. Esto evita sobreescribir bloques que cPanel, MultiPHP o SSL puedan haber agregado automáticamente.

## Si cPanel modifica `.htaccess` dentro del repositorio administrado

Primero revisa el cambio:

```bash
git diff -- .htaccess
```

Si confirmas que son cambios automáticos del hosting y quieres conservar el archivo versionado sin que esas modificaciones locales ensucien el working tree:

```bash
git update-index --skip-worktree .htaccess
```

Para volver a permitir que Git detecte cambios locales del archivo:

```bash
git update-index --no-skip-worktree .htaccess
```

## Requisitos de cPanel para desplegar

El repositorio administrado por cPanel debe tener:

- `.cpanel.yml` válido y versionado en la raíz.
- al menos una rama.
- working tree limpio.

Comprobaciones:

```bash
git status --short
git ls-files .cpanel.yml
git status
```

El resultado esperado de `git status --short` es vacío y `git status` debe terminar con:

```text
nothing to commit, working tree clean
```

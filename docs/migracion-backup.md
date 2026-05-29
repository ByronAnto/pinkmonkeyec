# Backup y migración — Pink Monkey Ecuador

Guía para **respaldar** la tienda y **migrarla a otro servidor**. Lo más importante: hay **3 piezas** y solo una vive en git.

## Las 3 piezas (qué se mueve y cómo)

| Pieza | Dónde vive | Cómo se migra |
|-------|------------|---------------|
| **Código** (PHP, tema, estilos) | GitHub (`ByronAnto/pinkmonkeyec`) | `git clone` / el runner despliega solo en el server nuevo |
| **Base de datos** (productos, categorías, bloques de la home, config, pedidos, clientes) | Volumen Docker `db-data` (contenedor MySQL) | `mysqldump` → importar en el nuevo |
| **Archivos subidos** (fotos de producto, logo, favicon) | `storage/app/public/` | `tar`/`rsync` de esa carpeta → al nuevo |

> ⚠️ **Las fotos NO van en git ni en la imagen Docker.** La BD solo guarda la *ruta* (`product/17/foto.webp`), no la foto. Si migras solo la BD, las imágenes salen rotas. Hay que copiar `storage/app/public/` aparte.

## Qué persiste en un deploy normal (NO se pierde)
- **Base de datos** → volumen Docker `db-data` (los deploys nunca la borran).
- **Archivos subidos** → `storage/app/public/` (el checkout usa `clean: false`, conserva lo subido).
- Un deploy solo actualiza **código** y, si cambió el `Dockerfile`, **reconstruye la imagen**.
- `docker compose down -v` SÍ borraría el volumen de BD → **nunca usar `-v` en producción.**

---

## Backup (respaldo periódico)

Ejecutar en el servidor (ajusta el nombre del contenedor db y la password — están en el `.env` del deploy):

```bash
# Variables
DB_PASS=$(grep '^DB_PASSWORD=' ~/pinkmonkeyec/actions-runner/_work/pinkmonkeyec/pinkmonkeyec/.env | cut -d= -f2-)
STAMP=$(date +%Y%m%d-%H%M%S)
mkdir -p ~/backups

# 1) Dump de la base de datos
docker exec -i pinkmonkeyec-db-1 mysqldump -upinkmonkey -p"$DB_PASS" \
  --no-tablespaces --single-transaction --add-drop-table pinkmonkey \
  > ~/backups/pinkmonkey-db-$STAMP.sql

# 2) Tar de las fotos/archivos subidos
docker exec pinkmonkeyec-app-1 sh -c 'tar -czf - -C storage/app/public .' \
  > ~/backups/pinkmonkey-media-$STAMP.tgz

echo "Backup listo: ~/backups/pinkmonkey-{db,media}-$STAMP.*"
```

Resultado: 2 archivos con fecha (`...-db-FECHA.sql` y `...-media-FECHA.tgz`). Guárdalos fuera del server (otro disco / nube) para que sirvan también ante pérdida del server.

---

## Migración a un servidor NUEVO

### 1. Preparar el server nuevo
- Docker + Docker Compose instalados.
- (Opcional) Self-hosted runner registrado al repo, o desplegar manualmente.
- Crear el `.env` de producción (mismos valores; **el `APP_KEY` DEBE ser el mismo** que el origen, o los datos cifrados fallan).

### 2. Traer el código
```bash
git clone https://github.com/ByronAnto/pinkmonkeyec.git
# o dejar que el runner despliegue con un push a main
```

### 3. Levantar el stack
```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml build
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
docker compose ... run --rm app composer install --no-dev --optimize-autoloader
docker compose ... run --rm --no-deps app npm install && npm run build
docker compose ... run --rm app php artisan migrate --force
```

### 4. Restaurar BD + fotos (lo que hace que se vea igual)
```bash
# Base de datos (reemplaza lo que haya)
docker exec -i pinkmonkeyec-db-1 mysql -upinkmonkey -p"$DB_PASS" pinkmonkey \
  < pinkmonkey-db-FECHA.sql

# Fotos / archivos subidos
docker cp pinkmonkey-media-FECHA.tgz pinkmonkeyec-app-1:/tmp/media.tgz
docker exec pinkmonkeyec-app-1 sh -c 'tar -xzf /tmp/media.tgz -C storage/app/public && rm /tmp/media.tgz'

# Limpiar caché + permisos
docker exec -u www-data -e HOME=/tmp pinkmonkeyec-app-1 php artisan optimize:clear
docker exec pinkmonkeyec-app-1 sh -c 'chown -R www-data:www-data storage bootstrap/cache && chmod -R 775 storage bootstrap/cache'
```

### 5. DNS + Caddy
- Apuntar el registro A de `pinkmonkeyec.it-services.center` a la IP del server nuevo.
- Agregar el bloque a Caddy (sin tocar otros sitios) y `caddy reload`:
  ```
  pinkmonkeyec.it-services.center {
      reverse_proxy 127.0.0.1:8080
  }
  ```
- Verificar: `curl -I https://pinkmonkeyec.it-services.center` → 200 + cert válido.

### 6. Verificar
- Home con branding PinkMonkey, productos con fotos, admin accesible.
- Si las imágenes salen rotas → faltó restaurar `storage/app/public/` o los permisos.

---

## Notas
- **APP_KEY igual en origen y destino** es obligatorio para datos cifrados (sesiones/tokens). Hoy producción usa el mismo `APP_KEY` que el entorno de desarrollo a propósito.
- Recomendado endurecer producción con un **volumen Docker nombrado** para `storage/app/public` (desacopla las fotos del checkout del runner) — pendiente.
- El histórico de este proceso (dev → producción 2026-05-29) siguió exactamente estos pasos: dump BD + tar media + import + Caddy.

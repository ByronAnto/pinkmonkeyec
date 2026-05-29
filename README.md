# Pink Monkey Ecuador — Tienda Online

E-commerce de ropa deportiva basado en **Bagisto 2.4.4** (Laravel 12 / PHP 8.3), dockerizado.

## Stack
- Bagisto 2.4.4 · PHP 8.3-FPM · Nginx · MySQL 8 · Redis 7
- Docker Compose (local) · GitHub Actions self-hosted runner (deploy) · Caddy (TLS)

## Desarrollo local

```bash
cp .env.example .env            # ajustar credenciales si quieres
docker compose up -d db redis   # espera ~15s a que MySQL arranque
# Instalación inicial (solo la primera vez) — usa --network=host por una limitación de red del bridge en este host:
docker run --rm --network=host -v "$PWD/src":/var/www/html -w /var/www/html pinkmonkey-app:latest composer create-project bagisto/bagisto . --no-interaction --no-scripts
cp .env src/.env
KEY=$(docker compose run --rm --no-deps app php artisan key:generate --show); sed -i "s|^APP_KEY=.*|APP_KEY=$KEY|" .env src/.env
docker compose run --rm app php artisan migrate --force
docker compose run --rm app php artisan db:seed --force
docker compose run --rm app php artisan storage:link
docker run --rm --network=host -v "$PWD/src":/var/www/html -w /var/www/html pinkmonkey-app:latest npm install
docker compose run --rm --no-deps app npm run build
docker compose up -d
# Permisos de escritura para Bagisto (necesario tras instalar):
docker compose exec -T app sh -c 'chown -R www-data:www-data storage bootstrap/cache && chmod -R 775 storage bootstrap/cache'
```

- Tienda: http://localhost:8080
- Admin: http://localhost:8080/admin  (usuario por defecto `admin@example.com` / `admin123` — **cámbialo**)
- Correos de prueba (Mailpit): http://localhost:8025

## Pagos
- Efectivo contra entrega (Cash on Delivery)
- Transferencia bancaria (Money Transfer)

## Branding
Logos en `assets/brand/`. Tema/marca en `docker/branding/pinkmonkey.css`. Paleta: `#D64C68` magenta, `#F599A4` rosa, `#4C4C4C` gris. Fuentes: Syne + Manrope.

## Despliegue
Push a `main` → el self-hosted runner de la VM Oracle (ARM64) construye y levanta. Ver `deploy/runner-setup.md`.

## Troubleshooting
- **`apt`/`composer`/`npm` fallan al resolver dominios dentro del contenedor:** el bridge de Docker de esta máquina no tiene salida a internet; usa `--network=host` en esos comandos (no aplica en la VM de producción).
- **HTTP 500 recién instalado:** permisos de `storage/` y `bootstrap/cache` (ver comando chown arriba).

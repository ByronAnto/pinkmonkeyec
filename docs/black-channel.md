# Black Monkey — segundo canal (multi-store)

Black Monkey Sportwear vive como un **segundo canal** dentro de la misma instalación
de Bagisto que sirve Pink Monkey. Un solo Bagisto, dos marcas:

| Marca        | Channel | Hostname                          | Tema (CSS)        | Root cat | Locale | Moneda |
|--------------|---------|-----------------------------------|-------------------|----------|--------|--------|
| Pink Monkey  | 1       | `http://localhost:8080`           | `pinkmonkey.css`  | 1        | es     | USD    |
| Black Monkey | 2       | `blackmonkeyec.it-services.center`  | `blackmonkey.css` | 6        | es     | USD    |

Bagisto resuelve el canal por el **hostname del request**. En producción cada marca
apunta su dominio a la misma app; en local se prueba con un header `Host:`.

## Qué es código (git) vs qué son datos (DB, los crea el seeder)

**Código (versionado en git):**
- `docker/branding/blackmonkey.css` + copia publicada en `src/public/blackmonkey.css`
- `src/packages/.../components/layouts/index.blade.php` — `<head>` channel-aware
  (carga Bebas Neue + Oswald + Inter + `blackmonkey.css` para el canal `black`,
  o Manrope + Syne + `pinkmonkey.css` para el resto).
- `docker/branding/seed-black.php` — seeder idempotente
- `assets/brand/black-monkey-logo.jpeg` — logo de marca

**Datos (filas en la DB, NO versionados — los crea `seed-black.php`):**
- El canal `black` (tabla `channels` + `channel_translations` + pivotes locale/currency)
- La categoría raíz "Black Monkey Root" (id 6) y sus hijas Hoodies/Joggers/Tanks/Gorras
- 8 productos demo (`bm-*`) asignados al canal 2 + categorías Black, qty 100, new+featured
- Los bloques de homepage (`theme_customizations` con `channel_id=2`): hero dark
  "ENTRENA / COMO BESTIA", category_carousel dinámico, product_carousel "Lo más vendido"
- El logo/favicon del canal en `storage/app/public/channel/2/black-monkey-logo.jpeg`

## Reproducir los DATOS de Black en producción (después del deploy)

El deploy publica el código. Para crear el canal + catálogo + bloques en la DB de
prod, correr el seeder **una vez** (es idempotente, se puede re-correr sin duplicar):

```bash
# 1) Stagear el logo dentro del contenedor (el seeder lo lee de aquí)
docker compose cp assets/brand/black-monkey-logo.jpeg app:/tmp/black-monkey-logo.jpeg
docker compose exec -T app sh -c '
  mkdir -p storage/app/public/channel/_black &&
  cp /tmp/black-monkey-logo.jpeg storage/app/public/channel/_black/black-monkey-logo.jpeg &&
  chown -R www-data:www-data storage/app/public/channel/_black'

# 2) Publicar el CSS dark a public/ (si el deploy no lo hace ya)
docker compose exec -T app sh -c '
  cp /var/www/html/docker/branding/blackmonkey.css /var/www/html/public/blackmonkey.css 2>/dev/null || true'
#   (en este repo public/ es root-owned; en prod ajustar al pipeline de assets)

# 3) Copiar y correr el seeder dentro del contenedor
docker compose cp docker/branding/seed-black.php app:/tmp/seed-black.php
docker compose exec -T -u www-data -e HOME=/tmp app php artisan tinker /tmp/seed-black.php

# 4) Limpiar cachés y arreglar permisos
docker compose exec -T -u www-data -e HOME=/tmp app php artisan view:clear
docker compose exec -T -u www-data -e HOME=/tmp app php artisan cache:clear
docker compose exec -T app sh -c '
  chown -R www-data:www-data storage bootstrap/cache &&
  chmod -R 775 storage bootstrap/cache'
```

El seeder reindexa el inventario (`UpdateCreateInventoryIndex`) para que los
productos sean vendibles (qty 100).

**Prerrequisitos** (ya presentes en este install): locale `es` y moneda `USD`.

## Verificación

```bash
# Black resuelve dark (vía Host header, sin DNS)
curl -s -H "Host: blackmonkeyec.it-services.center" http://localhost:8080 \
  | grep -oE "ENTRENA|COMO BESTIA|blackmonkey.css|Bebas|BLACK MONKEY SPORTWEAR"
curl -s -o /dev/null -w "%{http_code}\n" -H "Host: blackmonkeyec.it-services.center" http://localhost:8080

# Productos de Black por API (canal aislado, no se mezclan con Pink)
curl -s -H "Host: blackmonkeyec.it-services.center" "http://localhost:8080/api/products?featured=1"

# Pink intacto
curl -s http://localhost:8080 | grep -oE "pinkmonkey.css|TU MEJOR VERSIÓN"
```

## Diseño aprobado

`/.superpowers/brainstorm/2526647-1780100597/content/black-animado.html`
Colores: bg `#0c0c0e`, cards `#16161a`, bordes `#26262c`, plata `#cfcfd4`,
blanco `#fff`, gris `#8a8a92`. Fonts: Bebas Neue + Oswald (titulares), Inter (cuerpo).
CTA blanco angular + CTA ghost. Hero gradiente radial `#23232a → #0c0c0e`.

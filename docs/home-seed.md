# PinkMonkey — Homepage rebuild + demo catalog (reproducible)

Rebuilds the Bagisto storefront homepage so it matches the approved prototype
(`.superpowers/brainstorm/.../home-interactive-v2.html`) and seeds a demo catalog.

The catalog (products, categories, images) and the homepage layout
(`theme_customizations`) are **database rows**, not git-tracked files. The two
idempotent seed scripts below reproduce everything from a fresh install.

## Prerequisites

- Stack running (`docker compose up -d`), DB migrated/seeded (`php artisan bagisto:install`).
- `bagisto/laravel-datafaker` present in `vendor/` (ships with Bagisto dev deps).
- PHP GD extension in the `app` image (used for placeholder images) — already present.

## 1. Fonts (already in CSS, no script)

`docker/branding/pinkmonkey.css` (and its served copy `src/public/pinkmonkey.css`)
map the theme's Tailwind font classes to the brand fonts:

```css
.font-poppins, .font-sans, body, button, input, select, textarea { font-family:'Manrope', system-ui, sans-serif !important; }
.font-dmserif, h1, h2, h3, .text-3xl, .text-2xl { font-family:'Syne','Manrope',sans-serif !important; }
```

The shop `<head>` already loads Manrope + Syne from Google Fonts and includes
`pinkmonkey.css` after the theme CSS.

Verify: `curl -s http://localhost:8080/pinkmonkey.css | grep -c font-poppins` → `1`.

## 2. Seed catalog (4 categories + 8 products)

Script: `docker/branding/seed-home.php`. Idempotent (skips existing slugs/SKUs).

```bash
docker compose cp docker/branding/seed-home.php app:/var/www/html/seed-home.php
docker compose exec -T app php artisan tinker /var/www/html/seed-home.php
docker compose exec -T app php artisan indexer:index
docker compose exec -T app sh -c 'rm -f /var/www/html/seed-home.php'
docker compose exec -T app sh -c 'chown -R www-data:www-data storage bootstrap/cache && chmod -R 775 storage bootstrap/cache'
```

Creates:
- Categories (locale `es`, under root): **Mujer, Hombre, Accesorios, Calzado** (`/mujer`, etc.).
- 8 simple products, channel 1, status enabled, qty 100, `new=1` + `featured=1`,
  each with a GD-generated soft-pink placeholder PNG and one category:
  Top Deportivo Pro $24.90, Legging High Waist $32.00, Buzo Oversize $39.90,
  Short Running $18.50, Gorra Active $12.00, Medias Pro $9.90,
  Sujetador Deportivo $21.00, Camiseta Dry-Fit $19.90.

## 3. Rebuild homepage blocks

Script: `docker/branding/seed-homepage-blocks.php`. Idempotent (matches blocks by name).

```bash
docker compose cp docker/branding/seed-homepage-blocks.php app:/var/www/html/seed-homepage-blocks.php
docker compose exec -T app php artisan tinker /var/www/html/seed-homepage-blocks.php
docker compose exec -T app sh -c 'rm -f /var/www/html/seed-homepage-blocks.php'
docker compose exec -T app sh -c 'php artisan view:clear && php artisan cache:clear'
```

Rewrites `theme_customizations` for channel 1 / theme `default`, top to bottom:

1. **PinkMonkey Envios** (`static_content`) — gray ship bar
   "Envíos a todo Ecuador · Paga con transferencia o efectivo contra entrega" + payment pills.
2. **PinkMonkey Hero** (`static_content`) — magenta gradient (135°, #F599A4→#D64C68),
   eyebrow "NUEVA COLECCIÓN 2026", title "TU MEJOR VERSIÓN EMPIEZA HOY",
   subtitle, white pill CTA "Comprar ahora" → `/mujer`.
3. **PinkMonkey Categorias** (`static_content`) — 4 soft-pink (#ffe9ef) tiles:
   Calzado→`/calzado`, Leggings→`/mujer`, Tops→`/mujer`, Accesorios→`/accesorios`.
4. **PinkMonkey Lo mas vendido** (`product_carousel`) — title "Lo más vendido",
   filters `featured=1&limit=12&sort=name-asc` (shows the 8 seeded products).

All pre-existing demo blocks (`image_carousel`, the "Mejores Colecciones" /
"Colecciones Audaces" / "Contenedor de Juegos" static blocks) are set `status=0`.
`footer_links` and `services_content` are kept.

## 4. Verify

```bash
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080            # 200
curl -s http://localhost:8080 | grep -oE "TU MEJOR VERSIÓN|NUEVA COLECCIÓN 2026|Comprar ahora"
docker compose exec -T app php artisan tinker --execute='echo \Webkul\Product\Models\Product::count();'  # 8
```

## Reset (to re-seed from scratch)

```bash
docker compose exec -T app php artisan tinker --execute='
\Webkul\Product\Models\Product::query()->get()->each(fn($p)=>$p->delete());
\Webkul\Product\Models\ProductImage::query()->delete();
\DB::table("product_categories")->delete();'
docker compose exec -T app sh -c 'rm -rf storage/app/public/product/*'
```
(Categories and blocks are re-used idempotently; delete them manually if needed.)

## IMPORTANTE: reconstruir el índice de inventario tras seed
Bagisto 2.x calcula el stock vendible desde `product_inventory_indices` (un índice), no desde `product_inventories`. Si creas productos/inventario por seeder o faker (sin eventos), el índice queda vacío y los productos salen como "no vendibles" (no se pueden agregar al carrito) aunque tengan stock.

Solución (correr tras el seed):
```bash
docker compose exec -T -u www-data -e HOME=/tmp app php artisan tinker --execute='\
\Webkul\Product\Jobs\UpdateCreateInventoryIndex::dispatchSync(\Webkul\Product\Models\Product::pluck("id")->toArray());'
```
Verificar: `SELECT COUNT(*), SUM(qty) FROM product_inventory_indices;` debe mostrar filas > 0.

## Tiles de categorías dinámicos (admin-controlled) — Task 3.5

El bloque de "Compra por categoría" en el home ahora es **dinámico**: activar o
desactivar una categoría en el admin la muestra u oculta automáticamente.

- **`theme_customizations` id 10** (antes `static_content` "PinkMonkey Categorias") se
  reconvirtió a `type = 'category_carousel'`. Es una **fila de BD, no versionada en git**;
  si se reinstala/reseedea hay que reaplicarla:
  ```sql
  UPDATE theme_customizations SET type='category_carousel' WHERE id=10;
  UPDATE theme_customization_translations
    SET options='{"title":"Compra por categoría","filters":{"parent_id":1}}'
    WHERE theme_customization_id=10 AND locale='es';
  ```
- El carousel consume `shop.api.categories.index` (`/api/categories?parent_id=1`), que
  **solo devuelve categorías con `status=1`**. Por eso el toggle activo/inactivo del admin
  se refleja en el home sin tocar código.
- `packages/Webkul/Shop/src/Resources/views/components/categories/carousel.blade.php` se
  reestilizó: en vez de círculos con logo, renderiza **tiles rosa** (`.pm-cat-tile` en una
  grilla `.pm-cat-tiles`) con el nombre de la categoría. CSS en `docker/branding/pinkmonkey.css`
  (copia servida en `src/public/pinkmonkey.css`).
- Tiles **solo-nombre** (no usan imagen de categoría), igual al look hardcodeado previo.
- Verificación del toggle: con Accesorios inactivo la API lista Mujer/Hombre/Calzado; al
  activarlo (id=4 status=1) aparece Accesorios; al desactivarlo desaparece.

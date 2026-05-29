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

<?php
/**
 * PinkMonkey Ecuador — Rebuild theme_customizations to match the approved prototype.
 *
 * Run inside the app container:
 *   docker compose exec -T app php artisan tinker docker/branding/seed-homepage-blocks.php
 *
 * Idempotent: matches blocks by a stable marker name and upserts their es translation.
 * Blocks built top-to-bottom: Hero -> Category tiles -> "Lo más vendido" product carousel.
 * Disables the irrelevant default demo blocks.
 */

use Webkul\Theme\Models\ThemeCustomization;
use Webkul\Theme\Models\ThemeCustomizationTranslation;

$channel   = core()->getCurrentChannel();
$channelId = $channel->id;
$themeCode = $channel->theme ?? 'default';
$locale    = 'es';

/* Helper: upsert a customization block + its es translation. */
function pm_block(string $name, string $type, int $sort, array $options, int $channelId, string $themeCode, string $locale): ThemeCustomization
{
    $block = ThemeCustomization::firstOrNew([
        'name'       => $name,
        'theme_code' => $themeCode,
        'channel_id' => $channelId,
    ]);
    $block->type       = $type;
    $block->sort_order = $sort;
    $block->status     = 1;
    $block->save();

    $tr = ThemeCustomizationTranslation::where('theme_customization_id', $block->id)
        ->where('locale', $locale)
        ->first() ?: new ThemeCustomizationTranslation;
    $tr->theme_customization_id = $block->id;
    $tr->locale                 = $locale;
    $tr->options                = $options;
    $tr->save();

    return $block;
}

/* ---------------------------------------------------------------------------
 * Disable all existing default demo blocks first (keep footer_links + services).
 * ------------------------------------------------------------------------- */
ThemeCustomization::where('channel_id', $channelId)
    ->whereIn('type', ['image_carousel', 'static_content', 'category_carousel', 'product_carousel'])
    ->update(['status' => 0]);

/* ---------------------------------------------------------------------------
 * 1) HERO — magenta gradient, eyebrow, big title, subtitle, white pill CTA.
 * ------------------------------------------------------------------------- */
$heroCss = <<<CSS
.pm-hero{background:linear-gradient(135deg,#F599A4 0%,#D64C68 100%);color:#fff;text-align:center;padding:64px 20px;position:relative;overflow:hidden;font-family:'Manrope',system-ui,sans-serif}
.pm-hero::before,.pm-hero::after{content:"";position:absolute;border-radius:50%;background:rgba(255,255,255,.10)}
.pm-hero::before{width:280px;height:280px;top:-90px;right:-60px}
.pm-hero::after{width:200px;height:200px;bottom:-80px;left:-50px}
.pm-hero .pm-eyebrow{font-size:12px;letter-spacing:4px;opacity:.92;font-weight:700;position:relative}
.pm-hero h1{font-family:'Syne','Manrope',sans-serif;font-size:clamp(30px,5vw,52px);line-height:1.02;margin:14px 0 10px;position:relative;font-weight:800;color:#fff}
.pm-hero p{opacity:.95;font-size:16px;position:relative;margin:0}
.pm-hero .pm-cta{display:inline-flex;align-items:center;gap:8px;margin-top:24px;background:#fff;color:#D64C68;font-weight:800;padding:14px 30px;border-radius:999px;font-size:15px;position:relative;transition:transform .2s ease,box-shadow .2s ease;text-decoration:none}
.pm-hero .pm-cta:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(0,0,0,.18)}
CSS;

$heroHtml = <<<HTML
<section class="pm-hero">
  <div class="pm-eyebrow">NUEVA COLECCIÓN 2026</div>
  <h1>TU MEJOR VERSIÓN<br>EMPIEZA HOY</h1>
  <p>Ropa deportiva diseñada para moverte mejor</p>
  <a href="/mujer" class="pm-cta">Comprar ahora →</a>
</section>
HTML;

pm_block('PinkMonkey Hero', ThemeCustomization::STATIC_CONTENT, 1,
    ['css' => $heroCss, 'html' => $heroHtml], $channelId, $themeCode, $locale);

/* ---------------------------------------------------------------------------
 * 2) CATEGORY TILES — Calzado / Leggings / Tops / Accesorios (soft pink cards).
 * ------------------------------------------------------------------------- */
$catsCss = <<<CSS
.pm-cats-wrap{max-width:1120px;margin:34px auto;padding:0 20px}
.pm-cats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;font-family:'Manrope',system-ui,sans-serif}
.pm-cat{background:#ffe9ef;border-radius:16px;padding:26px 12px;text-align:center;font-weight:700;font-size:15px;color:#4C4C4C;transition:transform .2s ease,background .2s ease;display:flex;flex-direction:column;align-items:center;gap:10px;text-decoration:none}
.pm-cat:hover{transform:translateY(-3px);background:#ffdde6}
.pm-cat .pm-cat-ico{font-size:26px;line-height:1}
@media(max-width:860px){.pm-cats{grid-template-columns:repeat(2,1fr)}}
CSS;

$catsHtml = <<<HTML
<div class="pm-cats-wrap">
  <div class="pm-cats">
    <a class="pm-cat" href="/calzado"><span class="pm-cat-ico">👟</span>Calzado</a>
    <a class="pm-cat" href="/mujer"><span class="pm-cat-ico">🩳</span>Leggings</a>
    <a class="pm-cat" href="/mujer"><span class="pm-cat-ico">👕</span>Tops</a>
    <a class="pm-cat" href="/accesorios"><span class="pm-cat-ico">🧢</span>Accesorios</a>
  </div>
</div>
HTML;

pm_block('PinkMonkey Categorias', ThemeCustomization::STATIC_CONTENT, 2,
    ['css' => $catsCss, 'html' => $catsHtml], $channelId, $themeCode, $locale);

/* ---------------------------------------------------------------------------
 * 3) "Lo más vendido" — product carousel pulling featured products.
 * ------------------------------------------------------------------------- */
pm_block('PinkMonkey Lo mas vendido', ThemeCustomization::PRODUCT_CAROUSEL, 3,
    [
        'title'   => 'Lo más vendido',
        'filters' => ['featured' => '1', 'limit' => '12', 'sort' => 'name-asc'],
    ],
    $channelId, $themeCode, $locale);

/* ---------------------------------------------------------------------------
 * 4) Topbar note + payment pills (static content, brand gray).
 * ------------------------------------------------------------------------- */
$footCss = <<<CSS
.pm-shipbar{background:#4C4C4C;color:#fff;font-size:13px;text-align:center;padding:10px 16px;letter-spacing:.2px;font-family:'Manrope',system-ui,sans-serif}
.pm-pays{max-width:1120px;margin:30px auto;padding:0 20px;display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:center;font-family:'Manrope',system-ui,sans-serif}
.pm-pays .pm-pill{background:#ffe9ef;color:#D64C68;padding:7px 16px;border-radius:999px;font-size:13px;font-weight:700}
CSS;

$footHtml = <<<HTML
<div class="pm-shipbar">Envíos a todo Ecuador · Paga con transferencia o efectivo contra entrega</div>
<div class="pm-pays">
  <span class="pm-pill">Transferencia</span>
  <span class="pm-pill">Contra entrega</span>
  <span class="pm-pill">Envíos nacionales</span>
</div>
HTML;

pm_block('PinkMonkey Envios', ThemeCustomization::STATIC_CONTENT, 0,
    ['css' => $footCss, 'html' => $footHtml], $channelId, $themeCode, $locale);

echo "BLOCKS:\n";
foreach (ThemeCustomization::where('channel_id', $channelId)->orderBy('sort_order')->get() as $b) {
    echo "  #{$b->id} {$b->type} '{$b->name}' status={$b->status} sort={$b->sort_order}\n";
}
echo "BLOCKS_DONE\n";

<?php
/**
 * Black Monkey Sportwear — idempotent channel + catalog + homepage seeder.
 *
 * Adds a SECOND brand as a new Bagisto CHANNEL (multi-store) alongside Pink Monkey.
 * One Bagisto, two brands: Pink stays channel 1 (localhost:8080), Black is a new
 * channel on hostname blackmonkeyec.it-services.center with its OWN dark theme,
 * OWN root category + catalog, and OWN homepage blocks.
 *
 * Run inside the app container (as www-data to keep perms sane):
 *   docker compose exec -T -u www-data -e HOME=/tmp app \
 *     php artisan tinker docker/branding/seed-black.php
 * (or pipe it:  ... php artisan tinker < docker/branding/seed-black.php )
 *
 * Idempotent: re-running matches the channel by code, categories by slug,
 * products by SKU, and homepage blocks by (name, theme_code, channel_id).
 *
 * Prereqs assumed to already exist (true on this install): locale `es` and
 * currency `USD`. If they don't, create them in admin first.
 */

use Webkul\Core\Models\Channel;
use Webkul\Core\Models\Locale;
use Webkul\Core\Models\Currency;
use Webkul\Category\Repositories\CategoryRepository;
use Webkul\Category\Models\Category;
use Webkul\Faker\Helpers\Product as ProductFaker;
use Webkul\Product\Models\Product as ProductModel;
use Webkul\Product\Models\ProductImage;
use Webkul\Theme\Models\ThemeCustomization;
use Webkul\Theme\Models\ThemeCustomizationTranslation;
use Webkul\Product\Jobs\UpdateCreateInventoryIndex;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$BRAND_CODE     = 'black';
$BRAND_NAME     = 'Black Monkey';
$BRAND_HOSTNAME = 'blackmonkeyec.it-services.center';
$LOCALE_CODE    = 'es';
$CURRENCY_CODE  = 'USD';
$THEME_CODE     = 'default';

app()->setLocale($LOCALE_CODE);

$locale   = Locale::where('code', $LOCALE_CODE)->firstOrFail();
$currency = Currency::where('code', $CURRENCY_CODE)->firstOrFail();
$catRepo  = app(CategoryRepository::class);

/* ===========================================================================
 * 1) ROOT CATEGORY for Black (parent_id NULL, like Pink's root id=1).
 * ========================================================================= */
$rootSlug = 'black-monkey-root';
$root = Category::query()
    ->whereNull('parent_id')
    ->whereHas('translations', fn ($q) => $q->where('slug', $rootSlug))
    ->first();

if (! $root) {
    $root = $catRepo->create([
        'locale'       => 'all',
        'name'         => 'Black Monkey Root',
        'slug'         => $rootSlug,
        'parent_id'    => null,           // top-level root
        'status'       => 1,
        'position'     => 1,
        'display_mode' => 'products_and_description',
        'description'  => 'Black Monkey Sportwear — root',
    ]);
    echo "Root category created: #{$root->id}\n";
} else {
    echo "Root category exists: #{$root->id}\n";
}
$rootId = $root->id;

/* ===========================================================================
 * 2) CHANNEL "black" (mirrors channel 1 config; dark theme via blackmonkey.css).
 * ========================================================================= */
$channel = Channel::where('code', $BRAND_CODE)->first();

if (! $channel) {
    $channel = new Channel;
    $channel->code             = $BRAND_CODE;
    $channel->theme            = $THEME_CODE;
    $channel->hostname         = $BRAND_HOSTNAME;
    $channel->root_category_id = $rootId;
    $channel->default_locale_id = $locale->id;
    $channel->base_currency_id  = $currency->id;
    $channel->is_maintenance_on = 0;
    $channel->save();
    echo "Channel created: #{$channel->id} ({$BRAND_CODE})\n";
} else {
    // keep config in sync on re-run
    $channel->theme            = $THEME_CODE;
    $channel->hostname         = $BRAND_HOSTNAME;
    $channel->root_category_id = $rootId;
    $channel->default_locale_id = $locale->id;
    $channel->base_currency_id  = $currency->id;
    $channel->save();
    echo "Channel exists: #{$channel->id} ({$BRAND_CODE})\n";
}
$channelId = $channel->id;

// translation (name) for the es locale
$channel->translations()->updateOrCreate(
    ['channel_id' => $channelId, 'locale' => $LOCALE_CODE],
    [
        'name'        => $BRAND_NAME,
        'description' => 'Black Monkey Sportwear — ropa deportiva para los que no se rinden.',
        'home_seo'    => json_encode([
            'meta_title'       => 'Black Monkey Sportwear',
            'meta_keywords'    => 'black monkey, ropa deportiva, gym, hoodies, joggers',
            'meta_description' => 'Ropa deportiva para los que no se rinden. Black Monkey Sportwear.',
        ]),
    ]
);

// attach locale + currency (pivot)
$channel->locales()->syncWithoutDetaching([$locale->id]);
$channel->currencies()->syncWithoutDetaching([$currency->id]);

/* ---------------------------------------------------------------------------
 * 2b) Logo + favicon (per-channel). Black logo is shipped to the container at
 *     storage/app/public/channel/<id>/ by the deploy step / commands below.
 * ------------------------------------------------------------------------- */
$logoSrc = storage_path('app/public/channel/_black/black-monkey-logo.jpeg');
$logoRel = "channel/{$channelId}/black-monkey-logo.jpeg";
if (file_exists($logoSrc)) {
    Storage::disk('public')->put($logoRel, file_get_contents($logoSrc));
    $channel->logo    = $logoRel;
    $channel->favicon = $logoRel; // reuse logo as favicon
    $channel->save();
    echo "Channel logo/favicon set: {$logoRel}\n";
} else {
    echo "WARN: black logo not staged at {$logoSrc}; set channel.logo/favicon manually.\n";
}

/* ===========================================================================
 * 3) CHILD CATEGORIES under Black root: Hoodies / Joggers / Tanks / Gorras.
 * ========================================================================= */
$categories = [
    ['name' => 'Hoodies', 'slug' => 'black-hoodies'],
    ['name' => 'Joggers', 'slug' => 'black-joggers'],
    ['name' => 'Tanks',   'slug' => 'black-tanks'],
    ['name' => 'Gorras',  'slug' => 'black-gorras'],
];

$catIds = [];
foreach ($categories as $c) {
    $existing = Category::query()
        ->whereHas('translations', fn ($q) => $q->where('slug', $c['slug']))
        ->first();
    if ($existing) {
        $catIds[$c['slug']] = $existing->id;
        echo "Category exists: {$c['slug']} (#{$existing->id})\n";
        continue;
    }
    $cat = $catRepo->create([
        'locale'       => 'all',
        'name'         => $c['name'],
        'slug'         => $c['slug'],
        'parent_id'    => $rootId,
        'status'       => 1,
        'position'     => 1,
        'display_mode' => 'products_and_description',
        'description'  => 'Black Monkey Sportwear — '.$c['name'],
    ]);
    $catIds[$c['slug']] = $cat->id;
    echo "Category created: {$c['slug']} (#{$cat->id})\n";
}

/* ===========================================================================
 * 4) Dark GD placeholder image generator (matches the mockup card look).
 * ========================================================================= */
function bm_placeholder(string $label): string
{
    $w = 600; $h = 600;
    $im = imagecreatetruecolor($w, $h);
    $bg     = imagecolorallocate($im, 22, 22, 26);   // #16161a card
    $bgDeep = imagecolorallocate($im, 12, 12, 14);   // #0c0c0e
    $line   = imagecolorallocate($im, 38, 38, 44);   // #26262c
    $silver = imagecolorallocate($im, 207, 207, 212);// #cfcfd4
    imagefilledrectangle($im, 0, 0, $w, $h, $bgDeep);
    // diagonal-ish dark gradient block
    imagefilledrectangle($im, 40, 40, $w - 40, $h - 40, $bg);
    imagerectangle($im, 40, 40, $w - 40, $h - 40, $line);
    // initial letter in silver, scaled up
    $initial = mb_strtoupper(mb_substr($label, 0, 1));
    $fontSize = 5;
    $tw = imagefontwidth($fontSize) * strlen($initial);
    $th = imagefontheight($fontSize);
    $tmp = imagecreatetruecolor($tw, $th);
    imagefilledrectangle($tmp, 0, 0, $tw, $th, $bg);
    imagestring($tmp, $fontSize, 0, 0, $initial, $silver);
    $scale = 12;
    imagecopyresized($im, $tmp, ($w - $tw * $scale) / 2, ($h - $th * $scale) / 2, 0, 0, $tw * $scale, $th * $scale, $tw, $th);
    imagedestroy($tmp);

    ob_start();
    imagepng($im);
    $data = ob_get_clean();
    imagedestroy($im);
    return $data;
}

/* ===========================================================================
 * 5) PRODUCTS (8 simple) assigned to the Black channel + Black categories.
 * ========================================================================= */
$products = [
    ['name' => 'Hoodie Beast Oversize',  'price' => 42.90, 'cat' => 'black-hoodies'],
    ['name' => 'Jogger Tech Negro',      'price' => 34.90, 'cat' => 'black-joggers'],
    ['name' => 'Tank Iron Cut',          'price' => 19.90, 'cat' => 'black-tanks'],
    ['name' => 'Short Hardcore',         'price' => 22.50, 'cat' => 'black-joggers'],
    ['name' => 'Gorra BM Snapback',      'price' => 16.00, 'cat' => 'black-gorras'],
    ['name' => 'Hoodie Grunge',          'price' => 45.00, 'cat' => 'black-hoodies'],
    ['name' => 'Camiseta Compression',   'price' => 24.90, 'cat' => 'black-tanks'],
    ['name' => 'Pants Gym Pro',          'price' => 38.00, 'cat' => 'black-joggers'],
];

$blackProductIds = [];
foreach ($products as $p) {
    $sku    = 'bm-'.Str::slug($p['name']);
    $urlKey = 'bm-'.Str::slug($p['name']);

    $existingProduct = ProductModel::where('sku', $sku)->first();
    if ($existingProduct) {
        $blackProductIds[] = $existingProduct->id;
        // make sure channel + category links are present on re-run
        $existingProduct->channels()->syncWithoutDetaching([$channelId]);
        $existingProduct->categories()->syncWithoutDetaching([$catIds[$p['cat']]]);
        echo "Product exists: {$sku} (#{$existingProduct->id})\n";
        continue;
    }

    $faker = new ProductFaker([
        'attribute_value' => [
            'sku'                  => ['text_value' => $sku],
            'name'                 => ['text_value' => $p['name'], 'locale' => $LOCALE_CODE],
            'url_key'              => ['text_value' => $urlKey, 'locale' => $LOCALE_CODE],
            'price'                => ['float_value' => $p['price']],
            'weight'               => ['text_value' => 1],
            'short_description'    => ['text_value' => 'Black Monkey Sportwear · '.$p['name'], 'locale' => $LOCALE_CODE],
            'description'          => ['text_value' => '<p>'.$p['name'].' — entrena como bestia. Black Monkey Sportwear.</p>', 'locale' => $LOCALE_CODE],
            'meta_title'           => ['text_value' => $p['name'], 'locale' => $LOCALE_CODE],
            'meta_description'     => ['text_value' => $p['name'], 'locale' => $LOCALE_CODE],
            'meta_keywords'        => ['text_value' => 'black monkey, ropa deportiva', 'locale' => $LOCALE_CODE],
            'new'                  => ['boolean_value' => true],
            'featured'             => ['boolean_value' => true],
            'visible_individually' => ['boolean_value' => true],
            'guest_checkout'       => ['boolean_value' => true],
            'status'               => ['boolean_value' => true, 'channel' => $BRAND_CODE],
            'manage_stock'         => ['boolean_value' => true, 'channel' => $BRAND_CODE],
        ],
    ]);

    $created = $faker->getSimpleProductFactory()->count(1)->create();
    $product = $created->first();

    // enforce clean human-readable SKU (faker assigns a uuid otherwise)
    DB::table('products')->where('id', $product->id)->update(['sku' => $sku]);
    $product->attribute_values()->where('attribute_id', 1)->update(['text_value' => $sku]);
    $product = ProductModel::find($product->id);

    // inventory qty
    $product->inventories()->update(['qty' => 100]);

    // assign ONLY to the Black channel + Black category
    $product->channels()->sync([$channelId]);
    $product->categories()->syncWithoutDetaching([$catIds[$p['cat']]]);

    // dark placeholder image
    $png = bm_placeholder($p['name']);
    $dir = 'product/'.$product->id;
    $filename = $dir.'/'.$urlKey.'.png';
    Storage::disk('public')->put($filename, $png);
    ProductImage::create([
        'type'       => 'images',
        'path'       => $filename,
        'product_id' => $product->id,
        'position'   => 1,
    ]);

    \Illuminate\Support\Facades\Event::dispatch('catalog.product.update.after', $product);

    $blackProductIds[] = $product->id;
    echo "Product created: {$p['name']} \${$p['price']} (#{$product->id})\n";
}

/* ---------------------------------------------------------------------------
 * 5b) Reindex inventory so Black products are saleable (totalQuantity > 0).
 * ------------------------------------------------------------------------- */
if (! empty($blackProductIds)) {
    UpdateCreateInventoryIndex::dispatchSync($blackProductIds);
    echo "Inventory reindexed for ".count($blackProductIds)." products.\n";
}

/* ===========================================================================
 * 6) HOMEPAGE BLOCKS for the Black channel (channel_id = Black).
 *    Mirrors Pink's structure: static hero + dynamic category_carousel + product_carousel.
 * ========================================================================= */
function bm_block(string $name, string $type, int $sort, array $options, int $channelId, string $themeCode, string $locale): ThemeCustomization
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
        ->where('locale', $locale)->first() ?: new ThemeCustomizationTranslation;
    $tr->theme_customization_id = $block->id;
    $tr->locale                 = $locale;
    $tr->options                = $options;
    $tr->save();

    return $block;
}

// Disable any pre-existing dynamic blocks on the Black channel before rebuilding.
ThemeCustomization::where('channel_id', $channelId)
    ->whereIn('type', ['image_carousel', 'static_content', 'category_carousel', 'product_carousel'])
    ->update(['status' => 0]);

/* 6.0) Topbar shipping note (dark). */
$shipCss = <<<CSS
.bm-shipbar{background:#000;color:#8a8a92;font-size:11.5px;letter-spacing:2px;text-align:center;padding:9px 16px;text-transform:uppercase;font-family:'Oswald',system-ui,sans-serif}
CSS;
$shipHtml = <<<HTML
<div class="bm-shipbar">Envíos a todo Ecuador — Transferencia · Contra entrega</div>
HTML;
bm_block('Black Envios', ThemeCustomization::STATIC_CONTENT, 0,
    ['css' => $shipCss, 'html' => $shipHtml], $channelId, $THEME_CODE, $LOCALE_CODE);

/* 6.1) HERO — dark radial gradient, eyebrow, big condensed title (2nd line outline),
 *       subtitle, white angular CTA + ghost CTA. */
$heroCss = <<<CSS
.bm-hero{position:relative;background:radial-gradient(circle at 75% 50%,#23232a 0%,#0c0c0e 60%);border-bottom:1px solid #26262c;color:#cfcfd4;text-align:center;padding:72px 20px;overflow:hidden;font-family:'Inter',system-ui,sans-serif}
.bm-hero .bm-eyebrow{font-family:'Oswald',sans-serif;letter-spacing:5px;font-size:12px;color:#8a8a92;text-transform:uppercase}
.bm-hero h1{font-family:'Bebas Neue','Oswald',sans-serif;font-size:clamp(50px,7vw,104px);line-height:.9;margin:16px 0 10px;text-transform:uppercase;color:#fff;letter-spacing:1px}
.bm-hero h1 .out{-webkit-text-stroke:1.5px #cfcfd4;color:transparent}
.bm-hero p{color:#8a8a92;font-size:15px;letter-spacing:1px;margin:0}
.bm-hero .bm-cta{display:inline-flex;align-items:center;gap:10px;margin-top:28px;background:#fff;color:#000;font-family:'Oswald',sans-serif;font-weight:700;letter-spacing:2px;text-transform:uppercase;padding:15px 38px;font-size:14px;transition:transform .2s ease,box-shadow .2s ease;text-decoration:none;clip-path:polygon(8px 0,100% 0,100% calc(100% - 8px),calc(100% - 8px) 100%,0 100%,0 8px)}
.bm-hero .bm-cta:hover{transform:translateY(-2px);box-shadow:0 14px 34px rgba(255,255,255,.14)}
.bm-hero .bm-cta.ghost{background:transparent;color:#fff;border:1.5px solid #26262c;margin-left:12px;clip-path:none}
CSS;
$heroHtml = <<<HTML
<section class="bm-hero">
  <div class="bm-eyebrow">BLACK MONKEY SPORTWEAR · 2026</div>
  <h1>ENTRENA<br><span class="out">COMO BESTIA</span></h1>
  <p>ROPA DEPORTIVA PARA LOS QUE NO SE RINDEN</p>
  <div>
    <a href="/black-hoodies" class="bm-cta">Comprar ahora →</a>
    <a href="/black-joggers" class="bm-cta ghost">Ver colección</a>
  </div>
</section>
HTML;
bm_block('Black Hero', ThemeCustomization::STATIC_CONTENT, 1,
    ['css' => $heroCss, 'html' => $heroHtml], $channelId, $THEME_CODE, $LOCALE_CODE);

/* 6.2) CATEGORY TILES — dynamic category_carousel (admin-controlled), filtered to
 *       Black's root children. Dark card styling injected via a sibling static block. */
$catStyleCss = <<<CSS
.bm-cat-tiles,.pm-cat-tiles{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;max-width:1140px;margin:40px auto;padding:0 22px}
.bm-cat-tile,.pm-cat-tile{background:#16161a;border:1px solid #26262c;border-radius:4px;padding:26px 12px;text-align:center;font-family:'Oswald',sans-serif;font-weight:600;letter-spacing:2px;text-transform:uppercase;font-size:14px;color:#cfcfd4;text-decoration:none;transition:.2s;display:flex;align-items:center;justify-content:center;min-height:90px}
.bm-cat-tile:hover,.pm-cat-tile:hover{border-color:#cfcfd4;color:#fff;transform:translateY(-3px)}
@media(max-width:860px){.bm-cat-tiles,.pm-cat-tiles{grid-template-columns:repeat(2,1fr)}}
CSS;
bm_block('Black Cat Styles', ThemeCustomization::STATIC_CONTENT, 2,
    ['css' => $catStyleCss, 'html' => '<!-- dark category tile styles for Black -->'], $channelId, $THEME_CODE, $LOCALE_CODE);

bm_block('Black Categorias', ThemeCustomization::CATEGORY_CAROUSEL, 3,
    [
        'title'   => 'Categorías',
        'filters' => ['parent_id' => $rootId],
    ],
    $channelId, $THEME_CODE, $LOCALE_CODE);

/* 6.3) "LO MÁS VENDIDO" — product carousel of Black's featured products. */
bm_block('Black Lo mas vendido', ThemeCustomization::PRODUCT_CAROUSEL, 4,
    [
        'title'   => 'Lo más vendido',
        'filters' => ['featured' => '1', 'limit' => '12', 'sort' => 'name-asc'],
    ],
    $channelId, $THEME_CODE, $LOCALE_CODE);

/* ===========================================================================
 * 7) Summary.
 * ========================================================================= */
echo "\n=== BLACK MONKEY SEED SUMMARY ===\n";
echo "CHANNEL_ID={$channelId}\n";
echo "HOSTNAME={$channel->hostname}\n";
echo "ROOT_CATEGORY_ID={$rootId}\n";
echo "BLACK_CATEGORY_COUNT=".count($catIds)."\n";
echo "BLACK_PRODUCT_COUNT=".count($blackProductIds)."\n";
echo "BLOCKS:\n";
foreach (ThemeCustomization::where('channel_id', $channelId)->orderBy('sort_order')->get() as $b) {
    echo "  #{$b->id} {$b->type} '{$b->name}' status={$b->status} sort={$b->sort_order}\n";
}
echo "SEED_BLACK_DONE\n";

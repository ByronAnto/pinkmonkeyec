<?php
/**
 * PinkMonkey Ecuador — Idempotent demo catalog + homepage seeder.
 *
 * Run inside the app container:
 *   docker compose exec -T app php artisan tinker docker/branding/seed-home.php
 * (or pipe it: docker compose exec -T app php artisan tinker < docker/branding/seed-home.php)
 *
 * Creates 4 categories (es locale), 8 simple products with brand names/prices,
 * soft-pink GD placeholder images, marks them new+featured, then rebuilds the
 * theme_customizations so the home matches the approved prototype.
 */

use Webkul\Category\Repositories\CategoryRepository;
use Webkul\Faker\Helpers\Product as ProductFaker;
use Webkul\Product\Models\Product as ProductModel;
use Webkul\Product\Models\ProductImage;
use Webkul\Theme\Models\ThemeCustomization;
use Webkul\Theme\Models\ThemeCustomizationTranslation;
use Illuminate\Support\Facades\Storage;

$channel   = core()->getCurrentChannel();
$channelId = $channel->id;
$locale    = 'es';
app()->setLocale($locale);

$catRepo = app(CategoryRepository::class);

/* ---------------------------------------------------------------------------
 * 1) Categories (children of root id=1)
 * ------------------------------------------------------------------------- */
$rootId = $channel->root_category_id ?: 1;

$categories = [
    ['name' => 'Mujer',       'slug' => 'mujer'],
    ['name' => 'Hombre',      'slug' => 'hombre'],
    ['name' => 'Accesorios',  'slug' => 'accesorios'],
    ['name' => 'Calzado',     'slug' => 'calzado'],
];

$catIds = [];
foreach ($categories as $c) {
    $existing = \Webkul\Category\Models\Category::query()
        ->whereHas('translations', fn ($q) => $q->where('slug', $c['slug']))
        ->first();

    if ($existing) {
        $catIds[$c['slug']] = $existing->id;
        echo "Category exists: {$c['slug']} (#{$existing->id})\n";
        continue;
    }

    $cat = $catRepo->create([
        'locale'      => 'all',
        'name'        => $c['name'],
        'slug'        => $c['slug'],
        'parent_id'   => $rootId,
        'status'      => 1,
        'description' => 'Ropa deportiva Pink Monkey — '.$c['name'],
        'position'    => 1,
        'display_mode'=> 'products_and_description',
    ]);
    $catIds[$c['slug']] = $cat->id;
    echo "Category created: {$c['slug']} (#{$cat->id})\n";
}

/* ---------------------------------------------------------------------------
 * 2) Placeholder image generator (soft pink PNG with product initial)
 * ------------------------------------------------------------------------- */
function pm_placeholder(string $label): string
{
    $w = 600; $h = 600;
    $im = imagecreatetruecolor($w, $h);
    // soft-pink gradient-ish background
    $bg   = imagecolorallocate($im, 255, 233, 239); // #ffe9ef
    $accent = imagecolorallocate($im, 214, 76, 104); // magenta
    imagefilledrectangle($im, 0, 0, $w, $h, $bg);
    // a magenta circle as motif
    imagefilledellipse($im, $w / 2, $h / 2 - 30, 220, 220, imagecolorallocatealpha($im, 245, 153, 164, 40));
    // initial letter
    $initial = mb_strtoupper(mb_substr($label, 0, 1));
    // use built-in large font scaled by drawing big
    $fontSize = 5;
    $tw = imagefontwidth($fontSize) * strlen($initial);
    $th = imagefontheight($fontSize);
    // scale up by creating a temp and resampling
    $tmp = imagecreatetruecolor($tw, $th);
    imagefilledrectangle($tmp, 0, 0, $tw, $th, $bg);
    imagestring($tmp, $fontSize, 0, 0, $initial, $accent);
    $scale = 12;
    imagecopyresized($im, $tmp, ($w - $tw * $scale) / 2, ($h - $th * $scale) / 2, 0, 0, $tw * $scale, $th * $scale, $tw, $th);
    imagedestroy($tmp);

    ob_start();
    imagepng($im);
    $data = ob_get_clean();
    imagedestroy($im);
    return $data;
}

/* ---------------------------------------------------------------------------
 * 3) Products (simple) via datafaker with controlled attribute values
 * ------------------------------------------------------------------------- */
$products = [
    ['name' => 'Top Deportivo Pro',    'price' => 24.90, 'cat' => 'mujer'],
    ['name' => 'Legging High Waist',   'price' => 32.00, 'cat' => 'mujer'],
    ['name' => 'Buzo Oversize',        'price' => 39.90, 'cat' => 'hombre'],
    ['name' => 'Short Running',        'price' => 18.50, 'cat' => 'hombre'],
    ['name' => 'Gorra Active',         'price' => 12.00, 'cat' => 'accesorios'],
    ['name' => 'Medias Pro',           'price' => 9.90,  'cat' => 'accesorios'],
    ['name' => 'Sujetador Deportivo',  'price' => 21.00, 'cat' => 'mujer'],
    ['name' => 'Camiseta Dry-Fit',     'price' => 19.90, 'cat' => 'hombre'],
];

foreach ($products as $i => $p) {
    $sku = 'pm-'.\Illuminate\Support\Str::slug($p['name']);
    $urlKey = \Illuminate\Support\Str::slug($p['name']);

    if (ProductModel::where('sku', $sku)->exists()) {
        echo "Product exists: {$sku}\n";
        continue;
    }

    $faker = new ProductFaker([
        'attribute_value' => [
            'sku'                  => ['text_value' => $sku],
            'name'                 => ['text_value' => $p['name'], 'locale' => $locale],
            'url_key'              => ['text_value' => $urlKey, 'locale' => $locale],
            'price'                => ['float_value' => $p['price']],
            'weight'               => ['text_value' => 1],
            'short_description'    => ['text_value' => 'Ropa deportiva Pink Monkey · '.$p['name'], 'locale' => $locale],
            'description'          => ['text_value' => '<p>'.$p['name'].' — diseñada para moverte mejor. Pink Monkey Ecuador.</p>', 'locale' => $locale],
            'meta_title'           => ['text_value' => $p['name'], 'locale' => $locale],
            'meta_description'     => ['text_value' => $p['name'], 'locale' => $locale],
            'meta_keywords'        => ['text_value' => 'pink monkey, deportiva', 'locale' => $locale],
            'new'                  => ['boolean_value' => true],
            'featured'             => ['boolean_value' => true],
            'visible_individually' => ['boolean_value' => true],
            'guest_checkout'       => ['boolean_value' => true],
            'status'               => ['boolean_value' => true, 'channel' => $channel->code],
            'manage_stock'         => ['boolean_value' => true, 'channel' => $channel->code],
        ],
    ]);

    $created = $faker->getSimpleProductFactory()->count(1)->create();
    $product = $created->first();

    // enforce a clean, human-readable SKU (faker assigns a uuid otherwise)
    \Illuminate\Support\Facades\DB::table('products')->where('id', $product->id)->update(['sku' => $sku]);
    $product->attribute_values()
        ->where('attribute_id', 1) // sku attribute
        ->update(['text_value' => $sku]);
    $product = ProductModel::find($product->id);

    // set inventory qty
    $product->inventories()->update(['qty' => 100]);

    // attach to category
    $product->categories()->syncWithoutDetaching([$catIds[$p['cat']]]);
    // also attach to root so listing pages find it
    $product->channels()->sync([$channelId]);

    // placeholder image
    $png = pm_placeholder($p['name']);
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

    echo "Product created: {$p['name']} \${$p['price']} (#{$product->id})\n";
}

echo "PRODUCT_COUNT=".ProductModel::where('type', 'simple')->count()."\n";

echo "SEED_DONE\n";

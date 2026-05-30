<?php
// Actualiza los bloques hero (Pink id 9, Black id 14) al diseño de 2 columnas
// con el monito de protagonista. Idempotente. Correr en dev y prod.

use Illuminate\Support\Facades\DB;

// slug de una categoría real por canal para el CTA
$blackSlug = optional(DB::table('category_translations')
    ->join('categories','categories.id','=','category_translations.category_id')
    ->where('categories.parent_id', 6)->where('category_translations.locale','es')
    ->first(['category_translations.slug']))->slug ?? '';

/* ---------- PINK (bloque 9) ---------- */
$pinkCss = <<<CSS
.pm-hero{background:radial-gradient(circle at 78% 50%,#ffd9e4 0%,#F599A4 35%,#D64C68 100%);color:#fff;overflow:hidden}
.pm-hero-in{max-width:1140px;margin:0 auto;display:grid;grid-template-columns:1.1fr .9fr;align-items:center;gap:20px;min-height:480px;padding:48px 22px}
.pm-hero .pm-eyebrow{font-size:12px;letter-spacing:4px;font-weight:700;opacity:.95;font-family:'Manrope',sans-serif}
.pm-hero h1{font-family:'Syne','Manrope',sans-serif;font-size:clamp(38px,6vw,76px);line-height:.95;margin:14px 0 8px;font-weight:800;color:#fff}
.pm-hero .pm-sub{opacity:.95;font-size:16px;margin-top:6px;font-family:'Manrope',sans-serif}
.pm-hero .pm-cta{display:inline-flex;align-items:center;gap:9px;margin-top:24px;background:#fff;color:#D64C68;font-weight:800;padding:14px 32px;border-radius:999px;font-size:15px;text-decoration:none;font-family:'Manrope',sans-serif;transition:transform .2s,box-shadow .2s}
.pm-hero .pm-cta:hover{transform:translateY(-2px);box-shadow:0 12px 30px rgba(0,0,0,.18)}
.pm-hero-monkey{display:grid;place-items:center;position:relative}
.pm-hero-monkey .pm-card{background:#fff;border-radius:24px;padding:24px 28px;box-shadow:0 24px 60px rgba(140,30,60,.30)}
.pm-hero-monkey img{width:100%;max-width:270px;display:block}
@media(max-width:860px){.pm-hero-in{grid-template-columns:1fr;text-align:center}.pm-hero-monkey{order:-1}.pm-hero-monkey img{max-width:190px}}
CSS;
$pinkHtml = '<section class="pm-hero"><div class="pm-hero-in">'
 .'<div><div class="pm-eyebrow">NUEVA COLECCIÓN 2026</div>'
 .'<h1>TU MEJOR VERSIÓN EMPIEZA HOY</h1>'
 .'<div class="pm-sub">Ropa deportiva diseñada para moverte mejor</div>'
 .'<a class="pm-cta" href="/mujer">Comprar ahora →</a></div>'
 .'<div class="pm-hero-monkey"><div class="pm-card"><img src="/storage/channel/1/pink-monkey-logo.png" alt="Pink Monkey"></div></div>'
 .'</div></section>';

/* ---------- BLACK (bloque 14) ---------- */
$blackCss = <<<CSS
.bm-hero{background:radial-gradient(circle at 75% 50%,#23232a 0%,#0c0c0e 60%);overflow:hidden;border-bottom:1px solid #26262c}
.bm-hero-in{max-width:1140px;margin:0 auto;display:grid;grid-template-columns:1.1fr .9fr;align-items:center;gap:20px;min-height:500px;padding:48px 22px}
.bm-hero .bm-eyebrow{font-family:'Oswald',sans-serif;letter-spacing:5px;font-size:12px;color:#8a8a92;text-transform:uppercase}
.bm-hero h1{font-family:'Bebas Neue','Oswald',sans-serif;color:#fff;font-size:clamp(46px,7vw,96px);line-height:.9;margin:14px 0 8px;text-transform:uppercase}
.bm-hero h1 .bm-out{-webkit-text-stroke:1.5px #cfcfd4;color:transparent}
.bm-hero .bm-sub{color:#8a8a92;font-size:15px;letter-spacing:1px;margin-top:6px;font-family:'Inter',sans-serif}
.bm-hero .bm-cta{display:inline-flex;align-items:center;gap:10px;margin-top:26px;background:#fff;color:#000;font-family:'Oswald',sans-serif;font-weight:700;letter-spacing:2px;text-transform:uppercase;padding:15px 36px;font-size:14px;text-decoration:none;transition:.2s}
.bm-hero .bm-cta:hover{transform:translateY(-2px);box-shadow:0 14px 34px rgba(255,255,255,.14)}
.bm-hero-monkey{display:grid;place-items:center;position:relative}
.bm-hero-monkey:before{content:"";position:absolute;width:110%;height:110%;border-radius:50%;background:radial-gradient(circle,rgba(255,255,255,.1) 0%,rgba(255,255,255,0) 60%)}
.bm-hero-monkey img{width:100%;max-width:380px;position:relative;mix-blend-mode:screen;filter:drop-shadow(0 0 34px rgba(255,255,255,.14))}
@media(max-width:860px){.bm-hero-in{grid-template-columns:1fr;text-align:center}.bm-hero-monkey{order:-1}.bm-hero-monkey img{max-width:240px}}
CSS;
$blackHtml = '<section class="bm-hero"><div class="bm-hero-in">'
 .'<div><div class="bm-eyebrow">Black Monkey Sportwear · 2026</div>'
 .'<h1>ENTRENA<br><span class="bm-out">COMO BESTIA</span></h1>'
 .'<div class="bm-sub">ROPA DEPORTIVA PARA LOS QUE NO SE RINDEN</div>'
 .'<a class="bm-cta" href="/'.$blackSlug.'">Comprar ahora →</a></div>'
 .'<div class="bm-hero-monkey"><img src="/storage/channel/2/black-monkey-logo.jpeg" alt="Black Monkey"></div>'
 .'</div></section>';

function setHero($id,$css,$html){
  $rows = DB::table('theme_customization_translations')->where('theme_customization_id',$id)->get();
  foreach($rows as $r){
    DB::table('theme_customization_translations')->where('id',$r->id)
      ->update(['options'=>json_encode(['css'=>$css,'html'=>$html], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
  }
  echo "Hero #$id actualizado (".count($rows)." translations)\n";
}
setHero(9,$pinkCss,$pinkHtml);
setHero(14,$blackCss,$blackHtml);
echo "Black CTA slug: ".($blackSlug?:'(vacio)')."\n";
echo "LISTO\n";

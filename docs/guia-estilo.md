# Guía de estilo — Pink Monkey Ecuador

**Regla:** cualquier cambio de estilo/diseño de la tienda debe basarse en (1) el **manual de marca del cliente** y (2) **lo que construimos hoy** (2026-05-29). No improvisar colores, fuentes ni layout fuera de esto.

## Manual de marca del cliente
Archivo oficial: `assets/brand/pink-monkey-brand-manual.png` · Logos: `assets/brand/pink-monkey-logo.png`

### Colores (paleta oficial)
| Color | Hex | Uso |
|-------|-----|-----|
| Magenta | `#D64C68` | Principal: botones, precios, acentos, badges, CTA |
| Magenta oscuro | `#b83954` | Hover de botones |
| Rosa claro | `#F599A4` | Secundario: degradado del hero, detalles |
| Rosa suave | `#ffe9ef` / `#ffdde6` | Fondos de tiles, halo del logo, hovers |
| Gris | `#4C4C4C` | Texto principal, barras, footer (en vez de negro puro) |
| Blanco | `#fff` | Fondos |

### Tipografías
- **Syne** (600–800) → títulos / display (hero, encabezados)
- **Manrope** (400–800) → cuerpo, botones, navegación
- Yanesca → solo el logotipo (es asset, no fuente web)
- Importante: el tema usa clases `font-poppins`/`font-dmserif`; las mapeamos a Manrope/Syne en `pinkmonkey.css`. **No** reintroducir Poppins/DM Serif.

### Logo
- Logo oficial con **halo rosa** (resplandor radial `#ffe9ef`) detrás, leve escala en hover. Versión móvil más compacta (≤640px).
- Favicon = la **carita del monito** (recortada del logo).
- Proteger siempre la fuente de iconos `bagisto-shop` del override de tipografías (`[class^="icon-"]{font-family:"bagisto-shop"!important}`).

## Lo que construimos hoy (referencia visual aprobada)
- **Estilo general:** "Vibrant & Block-based", iconos SVG (no emoji como iconos estructurales), hover 150–300ms, `cursor-pointer`, responsive (375/768/1024/1440), respetar `prefers-reduced-motion`, contraste AA.
- **Home:** barra de envíos gris → **hero** con degradado magenta (`135deg, #F599A4 → #D64C68`) "NUEVA COLECCIÓN 2026 / TU MEJOR VERSIÓN EMPIEZA HOY" + CTA pill blanco → **tiles de categorías** dinámicos (rosados, controlados desde el admin) → **"Lo más vendido"** → footer gris con pills de pago.
- **Prototipo aprobado:** el diseño de referencia que validó el cliente (hero + tiles + cards de producto con precio en magenta y badges).

## Dónde se aplica el estilo
- CSS de marca: `docker/branding/pinkmonkey.css` (fuente) → publicado en `src/public/pinkmonkey.css` (servido, cargado en el `<head>` del tema).
- 4 archivos del tema personalizados (ver `docs/upgrade-bagisto.md`).

## Cómo proponer un cambio de estilo
1. Partir de esta paleta/tipografías y del look aprobado. Si el cliente manda nuevo material, actualizar primero `assets/brand/` y esta guía.
2. Editar `pinkmonkey.css` (o el blade del tema correspondiente).
3. `git push` a `main` → el runner despliega → verificar en https://pinkmonkeyec.it-services.center.
4. Contenido (textos del hero, productos, categorías) se cambia desde el **admin**, no por código.

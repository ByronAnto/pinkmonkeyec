# Actualizar versión de Bagisto — Pink Monkey

Guía para subir Bagisto a una versión nueva sin romper el branding ni los datos.

## Punto de partida (lo que tenemos)
- Bagisto **2.4.4** instalado vía `composer create-project` (paquetes en `packages/Webkul/` como repos `type: path`, parte de nuestro repo).
- Solo **4 archivos del core** modificados (todos del tema, para branding):
  1. `src/packages/Webkul/Shop/src/Resources/views/components/categories/carousel.blade.php` — tiles de categorías dinámicos
  2. `src/packages/Webkul/Shop/src/Resources/views/components/layouts/header/desktop/bottom.blade.php` — logo con halo
  3. `src/packages/Webkul/Shop/src/Resources/views/components/layouts/header/mobile/index.blade.php` — logo con halo (móvil)
  4. `src/packages/Webkul/Shop/src/Resources/views/components/layouts/index.blade.php` — fuentes Syne/Manrope + `pinkmonkey.css`
- Branding fuera del core (no choca con upgrades): `src/public/pinkmonkey.css`, logo, favicon, seeders.

## Checklist de upgrade

1. **Backup primero** (BD + fotos) — ver `docs/migracion-backup.md`. Los upgrades corren migraciones que cambian el esquema.
2. **Trabajar en una rama** (`upgrade/bagisto-X.Y`), nunca directo en `main`.
3. **Probar en STAGING** (con copia de los datos de prod), no en producción directo.
4. **Seguir la guía oficial** de la versión destino: https://devdocs.bagisto.com → Upgrade. Cada salto tiene pasos propios.
5. **Re-fusionar los 4 archivos del tema** si la versión nueva los cambió (ver lista arriba). `git diff` ayuda a ver qué tocó Bagisto vs lo nuestro.
6. **Requisitos de plataforma**: si la versión pide PHP/Node más nuevos, actualizar `docker/php/Dockerfile` (hoy: PHP 8.3, Node 20).
7. **Reinstalar deps + migrar + assets**:
   ```bash
   composer install --no-dev --optimize-autoloader   # o composer update según la guía
   php artisan migrate --force
   npm install && npm run build
   php artisan optimize:clear
   ```
8. **Verificar en staging**: home con branding, productos con fotos, checkout, admin.
9. **Merge a `main`** → el runner despliega (migrate corre solo). Backup hecho en el paso 1 por si hay que revertir.

## Flujo resumido
```
backup → rama upgrade → subir versión + re-merge 4 archivos + Dockerfile si aplica
       → probar en staging (con datos clonados) → merge a main → runner despliega → verificar
```

## Recomendación: tema hijo (para upgrades a prueba de balas)
Hoy editamos 4 archivos del core directamente — funciona, pero es el único punto de fricción en upgrades. Para eliminarlo, mover esas 4 personalizaciones a **overrides de tema** (sin tocar el core):

- Bagisto carga vistas del tema activo (`shop` → `default`) por *namespace*. Se puede sobreescribir una vista publicándola/colocándola en el directorio de vistas del tema activo, con prioridad sobre la del paquete.
- Pasos generales (confirmar rutas exactas con devdocs según versión):
  1. Identificar el tema activo (`config/themes.php` o el canal).
  2. Crear el directorio de overrides del tema y copiar SOLO los 4 blades ahí, con nuestros cambios.
  3. Revertir los 4 archivos del core a su estado original.
  4. Verificar que el front carga las versiones override (logo halo, tiles, fuentes).
- Resultado: el core queda 100% sin tocar → los upgrades no chocan nunca con el branding.

> Con solo 4 ediciones pequeñas, re-fusionar en cada upgrade también es perfectamente viable. El tema hijo es la opción "ideal" pero no urgente.

## Reglas de oro
- **Nunca** `docker compose down -v` en producción (borra el volumen de BD).
- **Backup** siempre antes de migrar el esquema.
- **APP_KEY** no cambia entre upgrades (datos cifrados).
- Probar en **staging** antes de prod.

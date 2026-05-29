# PinkMonkey Ecuador E-commerce — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Levantar la tienda Bagisto de PinkMonkey Ecuador con branding propio, dockerizada y reproducible en local, documentada, con el workflow de despliegue listo y desplegada en la VM Oracle ARM64 vía self-hosted runner.

**Architecture:** Bagisto (Laravel/PHP 8.3) en contenedores construidos desde bases oficiales multi-arch (php-fpm, nginx, mysql, redis). Local con Docker Compose; producción en VM Oracle ARM64 detrás de Caddy (TLS). CI/CD con GitHub Actions sobre un self-hosted runner instalado en la VM (build nativo arm64, sin SSH ni GHCR).

**Tech Stack:** Bagisto 2.x, PHP 8.3-FPM, Nginx, MySQL 8, Redis 7, Docker Compose, GitHub Actions (self-hosted), Caddy.

**Verificación:** Este proyecto es infra/config-heavy. Donde no aplica TDD de unidades, cada tarea usa **comandos de verificación con salida esperada** como prueba.

**Referencia oficial a consultar:** https://devdocs.bagisto.com/2.x/ (instalación, temas, configuración).

---

## File Structure

```
pinkmonkey-store/
├── docker/
│   ├── php/Dockerfile              # imagen app (multi-arch, FROM php:8.3-fpm)
│   ├── php/php.ini                 # ajustes PHP (uploads, memoria)
│   └── nginx/default.conf          # vhost nginx → php-fpm
├── docker-compose.yml              # base (app, web, db, redis)
├── docker-compose.override.yml     # solo DEV (puertos, volúmenes, mailpit)
├── docker-compose.prod.yml         # solo PROD (sin puertos públicos; tras Caddy)
├── .env.example                    # plantilla (sin secretos reales)
├── .gitignore
├── src/                            # código Bagisto (composer create-project)
├── assets/brand/                   # logos oficiales (ya copiados)
│   ├── pink-monkey-logo.png
│   └── pink-monkey-brand-manual.png
├── docker/branding/pinkmonkey.css  # CSS de marca (colores, fuentes, logo halo)
├── .github/workflows/deploy.yml    # workflow self-hosted runner
├── deploy/caddy/pinkmonkeyec.caddy # bloque Caddy para el subdominio
├── deploy/runner-setup.md          # pasos para instalar el self-hosted runner
└── README.md                       # documentación
```

---

## FASE 0 — Scaffolding del repositorio

### Task 0.1: Inicializar repo y estructura base

**Files:**
- Create: `pinkmonkey-store/.gitignore`
- Create: `pinkmonkey-store/README.md` (placeholder, se completa en Fase 4)

- [ ] **Step 1: Inicializar git en el proyecto**

```bash
cd ~/Repositorios/pinkmonkey-store
git init -b main
```

- [ ] **Step 2: Crear `.gitignore`**

```gitignore
# Secrets / entorno
.env
.env.*
!.env.example

# Bagisto / Laravel
/src/vendor/
/src/node_modules/
/src/storage/*.key
/src/.env
/src/public/storage
/src/bootstrap/cache/*.php

# Docker
*.log

# Brainstorm companion
.superpowers/

# SO
.DS_Store
Thumbs.db
```

- [ ] **Step 3: Crear README placeholder**

```markdown
# PinkMonkey Ecuador — Tienda Online

E-commerce de ropa deportiva (Bagisto) dockerizado. Documentación en progreso — ver `docs/`.
```

- [ ] **Step 4: Verificar y commit**

Run: `cd ~/Repositorios/pinkmonkey-store && git add -A && git status --short`
Expected: lista `.gitignore`, `README.md`, `assets/brand/*`, `docs/*` (NO debe aparecer `.env` ni `.superpowers/`).

```bash
git commit -m "chore: scaffold repo structure and gitignore"
```

---

## FASE 1 — Docker dev: Bagisto corriendo en local (tienda + admin)

> Objetivo: `docker compose up` y tener la tienda + panel admin funcionando en `http://localhost:8080`.

### Task 1.1: Dockerfile de la app (PHP 8.3-FPM, multi-arch)

**Files:**
- Create: `docker/php/Dockerfile`
- Create: `docker/php/php.ini`

- [ ] **Step 1: Crear `docker/php/Dockerfile`**

```dockerfile
# Base oficial multi-arch (tiene linux/arm64 y linux/amd64)
FROM php:8.3-fpm

# Dependencias del sistema para Bagisto
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
    libonig-dev libxml2-dev libicu-dev default-mysql-client \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" \
    pdo_mysql mbstring bcmath gd zip intl exif pcntl opcache \
 && pecl install redis && docker-php-ext-enable redis \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Node 20 (para compilar assets del tema)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
 && apt-get install -y nodejs && rm -rf /var/lib/apt/lists/*

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-bagisto.ini

WORKDIR /var/www/html
EXPOSE 9000
CMD ["php-fpm"]
```

- [ ] **Step 2: Crear `docker/php/php.ini`**

```ini
memory_limit = 512M
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 180
```

- [ ] **Step 3: Verificar que la imagen construye**

Run: `cd ~/Repositorios/pinkmonkey-store && docker build -f docker/php/Dockerfile -t pinkmonkey-app:test .`
Expected: termina con `naming to docker.io/library/pinkmonkey-app:test` sin errores.

- [ ] **Step 4: Verificar extensiones PHP dentro de la imagen**

Run: `docker run --rm pinkmonkey-app:test php -m | grep -E "pdo_mysql|gd|intl|redis|bcmath"`
Expected: imprime las 5 extensiones.

- [ ] **Step 5: Commit**

```bash
git add docker/php/
git commit -m "feat(docker): add PHP 8.3-FPM image with Bagisto extensions"
```

### Task 1.2: Nginx vhost

**Files:**
- Create: `docker/nginx/default.conf`

- [ ] **Step 1: Crear `docker/nginx/default.conf`**

```nginx
server {
    listen 80;
    server_name _;
    root /var/www/html/public;
    index index.php;

    client_max_body_size 64M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_read_timeout 180;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
```

- [ ] **Step 2: Commit**

```bash
git add docker/nginx/
git commit -m "feat(docker): add nginx vhost for Bagisto"
```

### Task 1.3: docker-compose base + override (dev)

**Files:**
- Create: `docker-compose.yml`
- Create: `docker-compose.override.yml`
- Create: `.env.example`

- [ ] **Step 1: Crear `docker-compose.yml`**

```yaml
services:
  app:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    image: pinkmonkey-app:latest
    restart: unless-stopped
    working_dir: /var/www/html
    volumes:
      - ./src:/var/www/html
    env_file: .env
    depends_on:
      - db
      - redis

  web:
    image: nginx:1.27
    restart: unless-stopped
    volumes:
      - ./src:/var/www/html
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
    depends_on:
      - app

  db:
    image: mysql:8.0
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: ${DB_DATABASE}
      MYSQL_USER: ${DB_USERNAME}
      MYSQL_PASSWORD: ${DB_PASSWORD}
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
    volumes:
      - db-data:/var/lib/mysql

  redis:
    image: redis:7-alpine
    restart: unless-stopped

volumes:
  db-data:
```

- [ ] **Step 2: Crear `docker-compose.override.yml` (DEV — se carga automáticamente)**

```yaml
services:
  web:
    ports:
      - "8080:80"

  db:
    ports:
      - "3307:3306"

  mailpit:
    image: axllent/mailpit:latest
    restart: unless-stopped
    ports:
      - "8025:8025"
```

- [ ] **Step 3: Crear `.env.example`**

```dotenv
APP_NAME="Pink Monkey Ecuador"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8080
APP_LOCALE=es
APP_CURRENCY=USD

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=pinkmonkey
DB_USERNAME=pinkmonkey
DB_PASSWORD=changeme_dev
DB_ROOT_PASSWORD=changeme_root_dev

REDIS_HOST=redis
REDIS_PORT=6379
CACHE_DRIVER=redis
SESSION_DRIVER=redis

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_FROM_ADDRESS="ventas@pinkmonkey.ec"
MAIL_FROM_NAME="Pink Monkey Ecuador"
```

- [ ] **Step 4: Crear `.env` local desde la plantilla**

Run: `cd ~/Repositorios/pinkmonkey-store && cp .env.example .env`
Expected: existe `.env` (ignorado por git).

- [ ] **Step 5: Verificar que compose es válido**

Run: `docker compose config --quiet && echo "compose OK"`
Expected: `compose OK` (sin errores de sintaxis).

- [ ] **Step 6: Commit**

```bash
git add docker-compose.yml docker-compose.override.yml .env.example
git commit -m "feat(docker): add compose stack (app, web, db, redis, mailpit)"
```

### Task 1.4: Instalar Bagisto dentro de `src/`

**Files:**
- Create: `src/` (proyecto Bagisto vía composer)

- [ ] **Step 1: Levantar solo db y redis**

Run: `docker compose up -d db redis`
Expected: `Started` para ambos. Verifica: `docker compose ps` muestra `db` y `redis` `running`.

- [ ] **Step 2: Crear el proyecto Bagisto en `src/` usando la imagen app**

Run:
```bash
docker compose run --rm --no-deps app \
  composer create-project bagisto/bagisto . --no-interaction
```
Expected: descarga Bagisto 2.x y crea `composer.json`, `artisan`, `app/`, etc. dentro de `src/`.
(Referencia si cambia el comando: https://devdocs.bagisto.com/2.x/introduction/installation.html)

- [ ] **Step 3: Generar APP_KEY y escribirla en `.env`**

Run:
```bash
KEY=$(docker compose run --rm --no-deps app php artisan key:generate --show)
sed -i "s|^APP_KEY=.*|APP_KEY=${KEY}|" .env
grep ^APP_KEY= .env
```
Expected: `APP_KEY=base64:...` con valor.

- [ ] **Step 4: Copiar el `.env` del proyecto a `src/.env`**

Run: `cp .env src/.env`
Expected: `src/.env` existe con las mismas variables (Bagisto lee desde la raíz del proyecto = `/var/www/html/.env`).

- [ ] **Step 5: Ejecutar el instalador de Bagisto (migraciones + seeders)**

Run:
```bash
docker compose run --rm app php artisan migrate --force
docker compose run --rm app php artisan db:seed --force
docker compose run --rm app php artisan storage:link
```
Expected: migraciones `DONE`, seeders sin error, symlink creado.
(Alternativa todo-en-uno si está disponible en esta versión: `php artisan bagisto:install` — confirmar en devdocs.)

- [ ] **Step 6: Compilar assets del tema**

Run:
```bash
docker compose run --rm --no-deps app npm install
docker compose run --rm --no-deps app npm run build
```
Expected: build de Vite termina sin error (genera `public/build`).

- [ ] **Step 7: Commit (sin vendor/node_modules — los ignora .gitignore)**

```bash
git add src/ -A
git commit -m "feat(app): install Bagisto 2.x e-commerce platform"
```

### Task 1.5: Verificar tienda + admin arriba

- [ ] **Step 1: Levantar el stack completo**

Run: `docker compose up -d`
Expected: `app`, `web`, `db`, `redis`, `mailpit` todos `running` (`docker compose ps`).

- [ ] **Step 2: Verificar la tienda responde**

Run: `curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080`
Expected: `200`.

- [ ] **Step 3: Verificar el panel admin responde**

Run: `curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/admin/login`
Expected: `200`.

- [ ] **Step 4: Login manual en navegador**

Abrir `http://localhost:8080/admin/login`. Credenciales por defecto Bagisto: `admin@example.com` / `admin123` (confirmar en salida del seeder).
Expected: entra al dashboard del admin.

- [ ] **Step 5: (No hay commit — es verificación)**

---

## FASE 2 — Configuración Ecuador y pagos

### Task 2.1: Activar moneda USD, locale ES y métodos de pago

> Bagisto gestiona esto desde el admin (Configurar). Documentamos los pasos para reproducibilidad.

- [ ] **Step 1: Configurar canal/locale/moneda en el admin**

En `http://localhost:8080/admin`:
- Configurar → General → Moneda base = **USD**; quitar otras monedas.
- Locales = **Español** (predeterminado).
- Canal por defecto: nombre "Pink Monkey Ecuador", URL `http://localhost:8080`.

- [ ] **Step 2: Activar Cash on Delivery (contra entrega)**

Admin → Configurar → Métodos de pago → **Cash On Delivery** → Activar. Título: "Efectivo contra entrega". Descripción para el cliente.
Expected: aparece como opción en el checkout.

- [ ] **Step 3: Activar Money Transfer (transferencia)**

Admin → Configurar → Métodos de pago → **Money Transfer** → Activar. Título: "Transferencia bancaria". En la descripción, los datos de la cuenta (placeholder hasta tener los reales del cliente).
Expected: aparece como opción en el checkout.

- [ ] **Step 4: Configurar envío básico (Ecuador)**

Admin → Configurar → Métodos de envío → activar "Flat Rate" con tarifa inicial (ej. $3.50) o "Free Shipping" según política del cliente.

- [ ] **Step 5: Verificar checkout muestra ambos pagos**

Crear un producto de prueba (Admin → Catálogo → Productos), agregarlo al carrito en la tienda, ir al checkout.
Expected: en el paso de pago aparecen "Efectivo contra entrega" y "Transferencia bancaria".

- [ ] **Step 6: Exportar configuración reproducible (documentar)**

Guardar las decisiones en `docs/config-tienda.md` (lista de settings aplicados). Commit:

```bash
git add docs/config-tienda.md
git commit -m "docs: document Ecuador store config (USD, ES, COD + transfer)"
```

---

## FASE 3 — Branding PinkMonkey (look aprobado)

> Aplicar: logo oficial + halo rosa, paleta #D64C68/#F599A4/#4C4C4C, fuentes Syne+Manrope, estilo del prototipo aprobado.

### Task 3.1: Subir el logo oficial

- [ ] **Step 1: Copiar el logo a storage accesible**

Run: `cp assets/brand/pink-monkey-logo.png src/public/pink-monkey-logo.png`
Expected: archivo disponible en `http://localhost:8080/pink-monkey-logo.png`.

- [ ] **Step 2: Configurar el logo en el admin**

Admin → Configurar → General → Tienda (Store front) → Logo → subir `pink-monkey-logo.png`. Favicon idem.
Expected: el logo aparece en la cabecera de la tienda.

- [ ] **Step 3: Commit del asset**

```bash
git add src/public/pink-monkey-logo.png
git commit -m "feat(brand): add official PinkMonkey logo to storefront"
```

### Task 3.2: CSS de marca (colores, fuentes, logo halo)

**Files:**
- Create: `docker/branding/pinkmonkey.css`
- Modify: layout del tema storefront para inyectar el CSS y las fuentes

- [ ] **Step 1: Crear `docker/branding/pinkmonkey.css`**

```css
:root{
  --pm-magenta:#D64C68; --pm-magenta-dark:#b83954;
  --pm-rosa:#F599A4; --pm-rosa-soft:#ffe9ef; --pm-gris:#4C4C4C;
}
body{font-family:'Manrope',system-ui,sans-serif;color:var(--pm-gris)}
h1,h2,h3,.font-display{font-family:'Syne','Manrope',sans-serif}

/* Botones primarios y precios con magenta de marca */
.primary-button,.btn-primary,button[type=submit].primary-button{
  background:var(--pm-magenta)!important;border-color:var(--pm-magenta)!important;
  transition:background .2s ease;
}
.primary-button:hover{background:var(--pm-magenta-dark)!important}
.price,.final-price,.product-price{color:var(--pm-magenta)!important;font-weight:800}

/* Logo con halo rosa (énfasis aprobado, opción B) */
.logo-image,header img[alt*="logo" i]{
  position:relative;z-index:1;transition:transform .25s ease;
}
.header-logo-wrapper{
  position:relative;display:inline-grid;place-items:center;padding:14px;
}
.header-logo-wrapper::before{
  content:"";position:absolute;width:120px;height:120px;border-radius:50%;
  background:radial-gradient(circle,var(--pm-rosa-soft) 0%,rgba(255,233,239,0) 70%);
  z-index:0;transition:transform .3s ease;
}
.header-logo-wrapper:hover::before{transform:scale(1.12)}

@media (prefers-reduced-motion: reduce){*{transition:none!important}}
```

- [ ] **Step 2: Cargar fuentes y CSS en el layout del storefront**

Editar el layout principal del tema (en Bagisto 2.x suele ser
`src/packages/Webkul/Shop/src/Resources/views/components/layouts/index.blade.php`
o el equivalente publicado en `src/resources/themes/`; confirmar ruta exacta con
`grep -rl "<head>" src/packages/Webkul/Shop/src/Resources/views/ | head`).
Dentro del `<head>` añadir:

```blade
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('pinkmonkey.css') }}">
```

- [ ] **Step 3: Publicar el CSS a `public/`**

Run: `cp docker/branding/pinkmonkey.css src/public/pinkmonkey.css`
Expected: accesible en `http://localhost:8080/pinkmonkey.css`.

- [ ] **Step 4: Envolver el logo con el wrapper del halo**

En el componente de cabecera del tema (buscar:
`grep -rl "logo" src/packages/Webkul/Shop/src/Resources/views/components/layouts/header* `),
envolver la etiqueta del logo:

```blade
<div class="header-logo-wrapper">
    {{-- etiqueta <img> del logo existente --}}
</div>
```

- [ ] **Step 5: Recompilar assets y verificar**

Run: `docker compose run --rm --no-deps app npm run build && docker compose restart web`
Then: abrir `http://localhost:8080`.
Expected: la tienda muestra el logo con halo rosa, botones magenta, precios en magenta, fuentes Syne/Manrope.

- [ ] **Step 6: Verificar el CSS se sirve**

Run: `curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/pinkmonkey.css`
Expected: `200`.

- [ ] **Step 7: Commit**

```bash
git add docker/branding/pinkmonkey.css src/public/pinkmonkey.css src/packages/Webkul/Shop/ -A
git commit -m "feat(brand): apply PinkMonkey theme (colors, fonts, logo halo)"
```

### Task 3.3: Revisión visual contra el prototipo aprobado

- [ ] **Step 1: Comparar con el prototipo**

Abrir lado a lado `http://localhost:8080` y el prototipo aprobado
(`.superpowers/brainstorm/.../home-interactive-v2.html`).
Checklist: logo con halo ✓, magenta en botones/precios ✓, hover suaves ✓, responsive a 375px ✓.

- [ ] **Step 2: Ajustar discrepancias** (si las hay) editando `pinkmonkey.css`, recompilar, recommit. No hay test automatizado; es revisión visual.

---

## FASE 4 — Documentación

### Task 4.1: README completo

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Escribir `README.md`**

````markdown
# Pink Monkey Ecuador — Tienda Online

E-commerce de ropa deportiva basado en **Bagisto** (Laravel/PHP), dockerizado.

## Stack
- Bagisto 2.x · PHP 8.3-FPM · Nginx · MySQL 8 · Redis 7
- Docker Compose (local) · GitHub Actions self-hosted runner (deploy) · Caddy (TLS)

## Desarrollo local

```bash
cp .env.example .env          # ajustar credenciales
docker compose up -d db redis
docker compose run --rm app composer create-project bagisto/bagisto .   # (solo primera vez)
docker compose run --rm app php artisan migrate --force && php artisan db:seed --force
docker compose up -d
```
Tienda: http://localhost:8080 · Admin: http://localhost:8080/admin
Correos de prueba (Mailpit): http://localhost:8025

## Pagos
- Efectivo contra entrega (Cash on Delivery)
- Transferencia bancaria (Money Transfer)

## Branding
Logos en `assets/brand/`. Tema en `docker/branding/pinkmonkey.css`. Paleta: #D64C68 / #F599A4 / #4C4C4C.

## Despliegue
Push a `main` → el self-hosted runner de la VM construye y levanta. Ver `deploy/runner-setup.md`.
````

- [ ] **Step 2: Commit**

```bash
git add README.md
git commit -m "docs: complete README with setup and deploy instructions"
```

### Task 4.2: Runbook de despliegue y secretos

**Files:**
- Create: `deploy/runner-setup.md`
- Create: `docs/secretos.md`

- [ ] **Step 1: Crear `deploy/runner-setup.md`** (los pasos exactos del runner se detallan en Fase 6; este archivo los documenta).

```markdown
# Self-hosted runner (VM Oracle ARM64)

Pasos resumidos (detalle en Fase 6 del plan):
1. En GitHub: repo (privado) → Settings → Actions → Runners → New self-hosted runner → Linux ARM64.
2. En la VM (ubuntu@149.130.183.24): descargar, configurar (`./config.sh`), instalar como servicio (`./svc.sh install && ./svc.sh start`).
3. El workflow `deploy.yml` corre con `runs-on: [self-hosted, ARM64]`.
4. Caddy: añadir `deploy/caddy/pinkmonkeyec.caddy` a la config de Caddy y recargar.
```

- [ ] **Step 2: Crear `docs/secretos.md`**

```markdown
# Manejo de secretos

- `.env` de producción vive SOLO en la VM (`~/pinkmonkey-store/.env`), nunca en git.
- Variables sensibles: APP_KEY, DB_PASSWORD, DB_ROOT_PASSWORD, MAIL_*, datos bancarios.
- Opción: cifrar `.env` en el repo con sops+age:
    sops --encrypt --age <pubkey> .env > .env.enc   # .env.enc SÍ puede ir a git
    sops --decrypt .env.enc > .env                  # en la VM al desplegar
- Rotación: cambiar DB_PASSWORD requiere actualizar `.env` y `docker compose up -d db`.
```

- [ ] **Step 3: Commit**

```bash
git add deploy/runner-setup.md docs/secretos.md
git commit -m "docs: add deploy runbook and secrets handling"
```

---

## FASE 5 — Workflow de GitHub Actions (archivo listo, sin activar)

### Task 5.1: Crear `deploy.yml`

**Files:**
- Create: `.github/workflows/deploy.yml`

- [ ] **Step 1: Crear el workflow**

```yaml
name: Deploy PinkMonkey

on:
  push:
    branches: [main]
  workflow_dispatch:

concurrency:
  group: deploy-prod
  cancel-in-progress: false

jobs:
  deploy:
    runs-on: [self-hosted, ARM64]
    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Build images (native arm64)
        run: docker compose -f docker-compose.yml -f docker-compose.prod.yml build

      - name: Start / update stack
        run: docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d

      - name: Run migrations
        run: docker compose -f docker-compose.yml -f docker-compose.prod.yml run --rm app php artisan migrate --force

      - name: Cache config & routes
        run: |
          docker compose -f docker-compose.yml -f docker-compose.prod.yml run --rm app php artisan optimize
          docker compose -f docker-compose.yml -f docker-compose.prod.yml run --rm app npm run build

      - name: Health check
        run: |
          sleep 5
          curl -fsS -o /dev/null -w "store: %{http_code}\n" http://localhost:8080 || exit 1
```

- [ ] **Step 2: Crear `docker-compose.prod.yml`**

**Files:**
- Create: `docker-compose.prod.yml`

```yaml
services:
  app:
    restart: always
    environment:
      APP_ENV: production
      APP_DEBUG: "false"
  web:
    restart: always
    ports:
      - "127.0.0.1:8080:80"   # solo localhost; Caddy hace de proxy público
  db:
    restart: always
  redis:
    restart: always
```

- [ ] **Step 3: Crear bloque Caddy**

**Files:**
- Create: `deploy/caddy/pinkmonkeyec.caddy`

```caddy
pinkmonkeyec.it-services.center {
    reverse_proxy 127.0.0.1:8080
    encode gzip
}
```

- [ ] **Step 4: Validar sintaxis YAML del workflow**

Run: `docker run --rm -v "$PWD":/w -w /w mikefarah/yq:latest '.jobs.deploy."runs-on"' .github/workflows/deploy.yml`
Expected: imprime `- self-hosted` / `- ARM64` (la lista).

- [ ] **Step 5: Verificar compose prod combina bien**

Run: `docker compose -f docker-compose.yml -f docker-compose.prod.yml config --quiet && echo "prod compose OK"`
Expected: `prod compose OK`.

- [ ] **Step 6: Commit**

```bash
git add .github/workflows/deploy.yml docker-compose.prod.yml deploy/caddy/pinkmonkeyec.caddy
git commit -m "ci: add deploy workflow, prod compose, caddy block (self-hosted runner)"
```

---

## FASE 6 — CI/CD: activar despliegue en la VM

> Se ejecuta una vez que el repo está en GitHub (privado) y queremos ir a producción.

### Task 6.1: Subir el repo a GitHub (privado)

- [ ] **Step 1: Crear el repo privado y push**

Run:
```bash
cd ~/Repositorios/pinkmonkey-store
gh repo create pinkmonkey-store --private --source=. --remote=origin --push
```
Expected: repo creado y `main` subido. Verifica: `gh repo view --json visibility -q .visibility` → `PRIVATE`.

### Task 6.2: Instalar el self-hosted runner en la VM

> Comandos a ejecutar EN la VM. El token de registro se obtiene en GitHub → repo → Settings → Actions → Runners → New self-hosted runner (Linux / ARM64). El usuario debe pegar ese token.

- [ ] **Step 1: Conexión a la VM**

Run (desde local): `ssh -i "/home/byron-realpe/Documentos/oracle/ssh-key-2026-05-04 (1).key" ubuntu@149.130.183.24`
Expected: prompt `ubuntu@...`.

- [ ] **Step 2: Verificar Docker en la VM**

Run (en VM): `docker --version && docker compose version`
Expected: ambas versiones impresas. Si falta, instalar Docker antes de continuar.

- [ ] **Step 3: Descargar y configurar el runner** (en VM, en `~/actions-runner`)

```bash
mkdir -p ~/actions-runner && cd ~/actions-runner
curl -o runner.tar.gz -L https://github.com/actions/runner/releases/latest/download/actions-runner-linux-arm64-2.latest.tar.gz
# (usar la URL exacta del release ARM64 que muestra la página de GitHub)
tar xzf runner.tar.gz
./config.sh --url https://github.com/<usuario>/pinkmonkey-store --token <TOKEN_DE_GITHUB> --labels ARM64 --unattended
```
Expected: "Runner successfully added".

- [ ] **Step 4: Instalar como servicio systemd**

```bash
sudo ./svc.sh install
sudo ./svc.sh start
sudo ./svc.sh status
```
Expected: servicio `active (running)`. En GitHub → Runners aparece `Idle`.

### Task 6.3: Preparar entorno de producción en la VM

- [ ] **Step 1: Clonar el repo en la VM** (el runner usará su workspace, pero el `.env` lo dejamos fijo)

```bash
cd ~ && git clone https://github.com/<usuario>/pinkmonkey-store.git
cd pinkmonkey-store
cp .env.example .env
```

- [ ] **Step 2: Editar `.env` de producción**

Editar `~/pinkmonkey-store/.env`:
- `APP_ENV=production`, `APP_DEBUG=false`
- `APP_URL=https://pinkmonkeyec.it-services.center`
- `DB_PASSWORD` / `DB_ROOT_PASSWORD` fuertes (14+ chars)
- `APP_KEY` (generar: `docker compose run --rm app php artisan key:generate --show`)
- `MAIL_*` reales (o Mailpit deshabilitado)

- [ ] **Step 3: Añadir el bloque Caddy y recargar**

```bash
sudo cp deploy/caddy/pinkmonkeyec.caddy /etc/caddy/conf.d/   # ajustar a cómo esté montado Caddy
# o añadir el bloque al Caddyfile principal
sudo systemctl reload caddy   # o: docker exec caddy caddy reload (según el setup)
```
Expected: Caddy recarga sin error.

### Task 6.4: Primer despliegue y verificación

- [ ] **Step 1: Disparar el workflow**

Run (desde local): `gh workflow run "Deploy PinkMonkey" --repo <usuario>/pinkmonkey-store`
o hacer un push a `main`.
Expected: en `gh run list` aparece el run; `gh run watch` muestra los pasos en verde.

- [ ] **Step 2: Verificar TLS y la tienda pública**

Run (desde local): `curl -s -o /dev/null -w "%{http_code}\n" https://pinkmonkeyec.it-services.center`
Expected: `200` con certificado válido (Let's Encrypt vía Caddy).

- [ ] **Step 3: Verificar el admin público**

Run: `curl -s -o /dev/null -w "%{http_code}\n" https://pinkmonkeyec.it-services.center/admin/login`
Expected: `200`.

- [ ] **Step 4: Smoke test funcional**

En navegador: abrir la tienda, agregar producto, llegar al checkout, confirmar que aparecen "Efectivo contra entrega" y "Transferencia bancaria", y que el branding (logo halo, magenta) está aplicado.

- [ ] **Step 5: Documentar el go-live**

Actualizar `README.md` con la URL de producción y commit:

```bash
git add README.md && git commit -m "docs: mark production go-live URL"
git push
```

---

## Self-Review (cobertura del spec)

- ✅ Tipo/enfoque/plataforma (Bagisto) → Fase 1
- ✅ Pagos COD + transferencia → Task 2.1
- ✅ USD / ES → Task 2.1
- ✅ Branding aprobado (logo halo, paleta, Syne/Manrope) → Fase 3
- ✅ Docker local + prod, ARM64 (build nativo desde bases multi-arch) → Fases 1 y 5
- ✅ Documentación (README, runbook, secretos) → Fase 4
- ✅ Workflow Actions (self-hosted) → Fase 5
- ✅ CI/CD self-hosted runner + Caddy + dominio + TLS → Fase 6
- ✅ Repo privado → Task 6.1
- ✅ No auto-commit / orden de trabajo (app→docker→docs→workflow→CICD) → respetado en el orden de fases
- ✅ Fuera de alcance (tarjeta, móvil, SRI) → no incluido

**Riesgos anotados:**
- Rutas exactas de las vistas del tema Bagisto 2.x pueden variar por versión → cada paso de tema incluye un `grep` para localizar el archivo real antes de editar.
- Comando de instalación (`bagisto:install` vs migrate+seed) → confirmar en devdocs según la versión instalada.

# PinkMonkey Ecuador — E-commerce · Diseño técnico

**Fecha:** 2026-05-29
**Autor:** Byron Realpe (DevSecOps)
**Estado:** Aprobado para implementación

## 1. Objetivo

Tienda online (e-commerce) para **PinkMonkey Ecuador**, marca de **ropa deportiva**. El cliente final navega el catálogo, agrega al carrito y compra; el administrador gestiona productos, stock, pedidos, clientes y correos de promoción desde un panel. Todo dockerizado, documentado y desplegado vía GitHub Actions con un **self-hosted runner** en la VM Oracle.

## 2. Decisiones tomadas (brainstorming)

| Tema | Decisión |
|------|----------|
| Tipo de sitio | E-commerce (tienda + panel admin) |
| Enfoque | Plataforma open-source (no a medida) |
| Plataforma | **Bagisto** (Laravel/PHP) — todo-en-uno |
| Pagos | **Cash on Delivery** (contra entrega) + **Money Transfer** (transferencia) — ambos nativos de Bagisto |
| Moneda / idioma | USD / Español |
| Despliegue | VM Oracle Cloud (ARM64 / aarch64), usuario `ubuntu`, IP `149.130.183.24` |
| Dominio | `pinkmonkeyec.it-services.center` (registro A ya creado → 149.130.183.24) |
| Reverse proxy / TLS | Caddy ya existente en la VM (Let's Encrypt) |
| CI/CD | GitHub Actions con **self-hosted runner** instalado en la VM (build nativo ARM64, sin SSH ni GHCR) |
| Repo | **Privado** (requisito de seguridad para self-hosted runner) |
| Commits | No auto-commit; specs/docs en rama `docs/*` |

## 3. Identidad visual (manual de marca del cliente)

- **Logo oficial:** monito con moña + "Pink Monkey" (script gris) + "ECUADOR". Archivos en `assets/brand/` (`pink-monkey-logo.png`, `pink-monkey-brand-manual.png`).
- **Paleta:**
  - Magenta `#D64C68` — color principal (precios, botones, acentos, badges)
  - Rosa claro `#F599A4` — secundario (fondos suaves, hero, hovers)
  - Gris `#4C4C4C` — texto y barras (en vez de negro puro)
  - Blanco — fondos
- **Tipografías:** Syne (display/títulos) + Manrope (cuerpo). El logo usa Yanesca (solo asset, no web).
- **Tratamiento de logo aprobado:** opción **B — halo rosa** (logo grande sobre resplandor radial rosa, micro-animación en hover).
- **Estilo UI:** "Vibrant & Block-based"; iconos **SVG** (no emoji), hover 150–300ms, `cursor-pointer`, responsive (375/768/1024/1440), `prefers-reduced-motion` respetado, contraste AA.
- **Prototipo interactivo aprobado:** `.superpowers/brainstorm/.../home-interactive-v2.html` (home con carrito funcional, filtros, favoritos, toasts, drawer).

## 4. Arquitectura

```
LOCAL (Docker Compose) ──git push(main)──► GitHub (repo privado)
                                                  │ dispara job
                                                  ▼
                              VM Oracle ARM64: self-hosted runner (systemd)
                              ├─ docker compose build (nativo arm64)
                              ├─ docker compose up -d
                              ├─ migraciones / seeders
                              └─ Caddy → pinkmonkeyec.it-services.center (TLS)
```

### Servicios Docker Compose

| Servicio | Imagen | Rol | ARM64 |
|----------|--------|-----|-------|
| `app` | Bagisto (PHP-FPM), build propio | Tienda + panel admin | ✓ (build nativo) |
| `web` | Nginx | Sirve la app (Caddy queda al frente en prod) | ✓ |
| `db` | MySQL 8 | Productos, pedidos, clientes, stock | ✓ |
| `redis` | Redis 7 | Caché y sesiones | ✓ |

Local: los 4 servicios con `docker compose up`. Producción: los mismos; Caddy hace reverse-proxy + TLS hacia `web`.

## 5. Alcance funcional

**De fábrica en Bagisto (configurar, no programar):**
- Catálogo, carrito, checkout, búsqueda
- Pagos: COD + Money Transfer
- Panel admin: productos, **stock/inventario**, pedidos, clientes
- Newsletter / suscriptores (correos de promoción)
- Multi-moneda (USD) + multi-idioma (ES)

**Custom (lo que construimos):**
- Tema/branding PinkMonkey (logo halo, paleta, tipografías, estilo aprobado)
- Configuración Ecuador (USD, zonas de envío, métodos de pago activados)
- Dockerización completa (Dockerfile + compose dev y prod)
- Documentación (README, runbook de despliegue, manejo de secretos)
- Workflow de GitHub Actions
- Self-hosted runner en la VM

## 6. CI/CD — Self-hosted runner

- Runner instalado en la VM (`~/actions-runner`), registrado al repo privado, corriendo como **servicio systemd** (sobrevive reinicios).
- Workflow `deploy.yml`: `runs-on: [self-hosted, ARM64]` → `checkout` → `docker compose build` → `up -d` → migraciones. Restringido a rama `main`.
- Sin QEMU (build nativo), sin SSH keys, sin GHCR.
- **Seguridad:** repo privado obligatorio; workflows solo desde `main`; opción de escaneo de imagen (Trivy/Grype/Hadolint) como paso previo.

## 7. Secretos y configuración

- `.env` de producción en la VM, **fuera de git**. Opción de cifrarlo en el repo con `sops`/`age`.
- Credenciales de app (`APP_KEY`, DB pass) y datos bancarios de transferencia se configuran en el `.env` / panel admin, nunca hardcodeados.
- Caddy: añadir bloque `pinkmonkeyec.it-services.center` apuntando al servicio `web`.

## 8. Orden de implementación (acordado)

1. **App + admin** — Bagisto funcionando con el tema/branding PinkMonkey
2. **Docker** — Dockerfile + compose, todo corriendo en local
3. **Documentación** — README + runbook + secretos
4. **Workflow de Actions** — `deploy.yml` listo (sin activar)
5. **CI/CD** — instalar self-hosted runner en la VM y activar el despliegue

## 9. Fuera de alcance (YAGNI por ahora)

- Pasarela de tarjeta (Datafast, PayPhone, Stripe) — solo COD + transferencia
- App móvil
- Multi-tienda / multi-marca
- Facturación electrónica SRI (se puede evaluar después)

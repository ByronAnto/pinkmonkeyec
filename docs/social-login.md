# Login Social (Google y Facebook) — Pink Monkey Ecuador

Bagisto incluye el paquete **SocialLogin** (sobre `laravel/socialite`) que permite "Iniciar sesión con…" usando proveedores externos. Soporta **Google, Facebook, Twitter/X, LinkedIn y GitHub**. Aquí documentamos **Google** y **Facebook** (los más usados).

> El error "Acceso bloqueado: Missing required parameter: client_id" significa simplemente que faltan las credenciales en el `.env`. Una vez configuradas, funciona.

## Ruta de callback (igual para todos los proveedores)

```
<APP_URL>/customer/social-login/{provider}/callback
```

Para Pink Monkey:
| Entorno | Google | Facebook |
|---------|--------|----------|
| Local   | `http://localhost:8080/customer/social-login/google/callback` | `http://localhost:8080/customer/social-login/facebook/callback` |
| Producción | `https://pinkmonkeyec.it-services.center/customer/social-login/google/callback` | `https://pinkmonkeyec.it-services.center/customer/social-login/facebook/callback` |

## Variables de entorno (`.env`)

```dotenv
# Google
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_CALLBACK_URL=http://localhost:8080/customer/social-login/google/callback

# Facebook
FACEBOOK_CLIENT_ID=
FACEBOOK_CLIENT_SECRET=
FACEBOOK_CALLBACK_URL=http://localhost:8080/customer/social-login/facebook/callback
```

> En producción cambia las dos `*_CALLBACK_URL` a la URL `https://pinkmonkeyec.it-services.center/...`.
> El `.env` NUNCA va a git (ya está en `.gitignore`). Los *secrets* solo viven en la máquina.

Tras editar el `.env`:
```bash
docker compose exec -T app php artisan config:clear
docker compose exec -T app php artisan cache:clear
```

---

## 1) Google — crear credenciales OAuth

1. Entra a **https://console.cloud.google.com/** y crea o elige un proyecto.
2. **APIs y servicios → Pantalla de consentimiento OAuth**:
   - Tipo de usuario: **Externo** → Crear.
   - Nombre de la app: `Pink Monkey Ecuador`, correo de asistencia, logo (opcional).
   - Dominios autorizados: `it-services.center`.
   - Guarda y publica (en "Producción" para que cualquiera pueda usarlo; en "Pruebas" solo los correos que agregues).
3. **APIs y servicios → Credenciales → Crear credenciales → ID de cliente de OAuth**:
   - Tipo: **Aplicación web**.
   - Nombre: `Pink Monkey Web`.
   - **Orígenes autorizados de JavaScript** (opcional): `http://localhost:8080` y `https://pinkmonkeyec.it-services.center`.
   - **URIs de redireccionamiento autorizados** (obligatorio, exactos):
     ```
     http://localhost:8080/customer/social-login/google/callback
     https://pinkmonkeyec.it-services.center/customer/social-login/google/callback
     ```
4. Google te entrega **Client ID** y **Client Secret** → ponlos en `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET`.

---

## 2) Facebook — crear app en Meta for Developers

1. Entra a **https://developers.facebook.com/** → **Mis Apps → Crear app**.
2. Caso de uso: **Autenticar y solicitar datos de los usuarios con Facebook Login** (tipo "Consumidor").
3. En el panel de la app → **Configuración → Básica**: copia el **Identificador de la app** (= `FACEBOOK_CLIENT_ID`) y la **Clave secreta de la app** (= `FACEBOOK_CLIENT_SECRET`). Agrega el **Dominio de la app**: `it-services.center`.
4. Agrega el producto **Inicio de sesión con Facebook → Configuración**:
   - **URI de redireccionamiento de OAuth válidos**:
     ```
     http://localhost:8080/customer/social-login/facebook/callback
     https://pinkmonkeyec.it-services.center/customer/social-login/facebook/callback
     ```
   - Activa "Inicio de sesión con OAuth del cliente" y "Inicio de sesión con OAuth web".
5. Para que funcione con cualquier usuario, **publica la app** (Modo en vivo). En modo desarrollo solo entran los roles/probadores que agregues.
6. Pon el ID y el secreto en `FACEBOOK_CLIENT_ID` / `FACEBOOK_CLIENT_SECRET`.

> Facebook **exige HTTPS** para el callback en modo en vivo. En local (`http://localhost`) sirve para pruebas en modo desarrollo; el flujo completo de producción usa la URL `https://pinkmonkeyec...`.

---

## 3) Activar los proveedores en el admin

En **Admin → Configurar** busca la sección **Social Login** (del paquete SocialLogin) y **habilita** Google y Facebook. Algunos campos de credenciales también pueden gestionarse ahí; si están vacíos, el sistema usa los del `.env`.

## 4) Probar

1. Cierra sesión de cliente y ve a **Iniciar sesión** en la tienda (`/customer/login`).
2. Aparecen los botones de Google / Facebook → clic → flujo OAuth → vuelve logueado.
3. Si falla: revisa que la **URI de redireccionamiento** en Google/Facebook coincida **exactamente** (incluido http vs https y sin barra final extra) con la `*_CALLBACK_URL` del `.env`, y que corriste `config:clear`.

---

## Otros proveedores disponibles
El mismo patrón aplica a **Twitter/X, LinkedIn y GitHub**: variables `TWITTER_*`, `LINKEDIN_*`, `GITHUB_*` en `.env` y callback `.../customer/social-login/{provider}/callback`.

## Nota sobre móvil
La tienda es **responsive (mobile-first)**: el tema Bagisto adapta menú, grid y tipografías a celular, y el branding de Pink Monkey (`docker/branding/pinkmonkey.css`) incluye ajustes móviles (logo/halo compactos, tiles 4→2 columnas, hero con tipografía fluida `clamp()`). Los botones de login social heredan ese diseño responsive.

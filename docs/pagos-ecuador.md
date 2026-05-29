# Métodos de pago en Ecuador — Pink Monkey (PENDIENTE para después)

Estado: **planificado / no implementado todavía.** Arrancamos solo con Contra entrega + Transferencia.

## Activo ahora (de fábrica en Bagisto, sin comisión de pasarela)
- **Efectivo contra entrega** (Cash on Delivery) — el más usado en Ecuador.
- **Transferencia / depósito bancario** (Money Transfer) — Pichincha, Guayaquil, Produbanco, Pacífico, etc. El cliente paga y sube comprobante.
- Configurables en: **Admin → Configurar → Ventas → Métodos de pago** (`/admin/configuration/sales/payment_methods`).
- Pendiente: poner los **datos bancarios reales** del cliente en la descripción de Transferencia.

## Pendiente: pasarela de tarjeta (requiere integración + contrato + API keys)
Candidatas para Ecuador (Bagisto NO las trae de fábrica → módulo de comunidad o método de pago custom con su API):

| Pasarela | Notas | Facilidad |
|----------|-------|-----------|
| **PayPhone** | Ecuatoriana, muy popular en PYMES, botón de pago + app | Alta |
| **Pagoplux** | Ecuatoriana, tarjetas con cuotas (corriente/diferido), retail | Alta |
| **Datafast** | Adquirente bancaria fuerte (Diners, Visa, Mastercard) | Media (más trámite) |
| **Kushki** | Regional, API dev-friendly, tarjetas + transferencias | Media |
| **PlacetoPay** | Usada por varios bancos ecuatorianos | Media |

**Recomendación:** empezar con **PayPhone** o **Pagoplux** cuando se quiera cobrar con tarjeta.

## NO usar en Ecuador (desactivar en el admin)
- **Stripe** — no opera con entidad ecuatoriana (no se puede cobrar localmente).
- **PayPal** — solo útil para compradores internacionales; retiro a Ecuador con fricción.
- **Razorpay / PayU / Mercado Pago** — no aplican a Ecuador.

## Próximos pasos cuando se retome
1. Elegir pasarela (probable PayPhone o Pagoplux).
2. Crear cuenta de comercio y obtener API keys / credenciales.
3. Buscar módulo Bagisto de esa pasarela en el marketplace, o desarrollar un payment method custom (clase en `Webkul\Payment` + método en `config`).
4. Configurar URL de callback/notificación y probar en sandbox.
5. Desactivar Stripe/PayPal/Razorpay/PayU en el admin.

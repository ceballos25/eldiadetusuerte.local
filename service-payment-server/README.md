# Microservicio de pagos — accesorios.caballosrevelo.com

Paquete de despliegue para el subdominio **accesorios**. Recibe webhooks de OpenPay y muestra la página de confirmación PSE.

> Documentación completa: [docs/openpay-arquitectura.md](../docs/openpay-arquitectura.md)

---

## Despliegue rápido

1. Sube **todo el contenido de esta carpeta** a `public_html/` del hosting accesorios.
2. Copia `.env.example` → `$VH_ROOT/.env` (un nivel **arriba** de `public_html`).
3. Crea `$VH_ROOT/logs/` (755).
4. Verifica que exista `openpay/webhooks/.htaccess` (protege los JSON).
5. Configura `.env` con URLs de **caballosrevelo.com**.
6. Registra en OpenPay: `https://accesorios.caballosrevelo.com/openpay/webhook.php`

---

## Archivos JSON — auditoría en accesorios

| Evento OpenPay | ¿Guarda JSON? | Carpeta final |
|----------------|---------------|---------------|
| `verification` | ❌ No | — |
| Cualquier otro (`charge.succeeded`, `charge.failed`, etc.) | ✅ Sí | `processed/` si forward OK, `error/` si falla |

Ejemplo:

```
openpay/webhooks/processed/PB-20260530072352793_charge.succeeded.json
openpay/webhooks/error/PB-xxx_charge.failed.json
```

El JSON **nunca se borra** tras un forward exitoso (respaldo de seguridad). Campo `sale_approved` indica si el bridge aprobó venta.

La BD del principal (`webhook_events`) sigue siendo la fuente para reprocesar ventas desde Admin.

---

## Flujo

```
1. OpenPay POST → openpay/webhook.php
2. Guarda JSON en openpay/webhooks/pending/
3. Reenvía a caballosrevelo.com/openpay/webhook.bridge.php
4. OK  → openpay/webhooks/processed/
   FAIL → openpay/webhooks/error/
```

**Importante:** `pending/` no tiene worker automático. El movimiento ocurre **solo** cuando OpenPay hace POST a `webhook.php` (o cuando ejecutas el script de reproceso). Copiar un JSON a `pending/` manualmente **no** lo procesa.

| Resultado del forward | Destino del archivo |
|-----------------------|---------------------|
| Forward OK (HTTP 2xx al bridge) | `processed/` (siempre se conserva) |
| Forward fallido (404, 500, timeout…) | `error/` |

---

## Reprocesar JSON atascados (pending / error)

Desde SSH en accesorios (`public_html`):

```bash
# Ver qué hay sin reenviar
php scripts/reprocess_webhooks.php --dry-run

# Reprocesar todo pending/
php scripts/reprocess_webhooks.php

# Reprocesar error/
php scripts/reprocess_webhooks.php --dir=error

# Un archivo concreto
php scripts/reprocess_webhooks.php --file=PB-20260531135119265.json

# pending + error
php scripts/reprocess_webhooks.php --dir=all
```

Revisa `$VH_ROOT/logs/payment-server.log` (`WEBHOOK REPROCESS`, `WEBHOOK FILE moved to …`).

---

## Variables `.env`

Ver `.env.example`. Secrets iguales en caballosrevelo.com y accesorios.

---

## Logs

`$VH_ROOT/logs/payment-server.log`

```
WEBHOOK FILE moved to processed: PB-xxx_charge.succeeded.json
FORWARD HTTP 500                    → revisar principal
```

---

## Estructura del paquete

```
service-payment-server/
├── README.md
├── .env.example
├── config.php
├── lib/WebhookFileStorage.php
├── lib/PaymentWebhookForward.php
├── scripts/reprocess_webhooks.php
└── openpay/
    ├── webhook.php
    ├── success.php
    └── webhooks/
        ├── .htaccess
        ├── index.html
        ├── pending/
        ├── processed/
        └── error/
```

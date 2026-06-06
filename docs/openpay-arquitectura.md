# OpenPay PSE — Arquitectura de pagos Caballos Revelo

Documentación del flujo de pagos con OpenPay (PSE), el microservicio en **accesorios** y el servidor principal en **caballosrevelo.com**.

---

## Resumen en una frase

OpenPay habla con **accesorios** (URL pública del webhook). Accesorios reenvía al **principal**, que aprueba la venta en BD. El usuario ve sus boletas en **accesorios/success.php**.

---

## Servidores

| Servidor | Dominio | Rol |
|----------|---------|-----|
| **Principal** | `caballosrevelo.com` | App completa, rifas, ventas, BD, bridges |
| **Pagos** | `accesorios.caballosrevelo.com` | Solo webhook OpenPay + página de éxito PSE |

Ambos pueden estar en **hosting compartido** (cPanel). No requieren root ni `sudo`.

---

## Diagrama de flujo

```
Comprador → caballosrevelo.com (checkout PSE)
                ↓
            OpenPay (banco)
                ↓ webhook POST
    accesorios/openpay/webhook.php
                ↓ HMAC + POST
    caballosrevelo.com/openpay/webhook.bridge.php
                ↓
         BD: webhook_events + payment_backups → sales
                ↓
Comprador ← accesorios/openpay/success.php?order_id=PB-...
                ↓ poll
    caballosrevelo.com/openpay/status.bridge.php → html_recibo (boletas)
```

---

## Qué guarda cada servidor

### caballosrevelo.com (principal)

| Dónde | Qué |
|-------|-----|
| **MySQL** `webhook_events` | Todos los webhooks procesados (payload, estado, UUID) |
| **MySQL** `payment_backups` | Reserva de números antes del pago |
| **MySQL** `sales` / tickets | Venta aprobada y boletas |
| **logs/** `openpay-bridge.log` | Traza del bridge |
| ~~data/webhooks/~~ | **No se usa** — sin archivos JSON en disco |

Reprocesar webhooks fallidos: **Admin → Webhooks OpenPay** (usa BD, no archivos).

### accesorios.caballosrevelo.com (pagos)

| Dónde | Qué |
|-------|-----|
| **openpay/webhooks/pending/** | JSON recién llegados (no procesados) |
| **openpay/webhooks/processed/** | JSON con forward OK al principal |
| **openpay/webhooks/error/** | JSON si falló el forward (se purgan a los 7 días) |
| **logs/** `payment-server.log` | Traza de webhooks y forwards |

Ruta en el servidor (dentro de `public_html`):

```
public_html/openpay/webhooks/
├── .htaccess          ← bloquea acceso web
├── pending/
├── processed/
└── error/
```

Nombre de archivo: `PB-20260530063411494_charge.succeeded.json`

---

## Archivos clave

### Principal (`caballosrevelo.com`)

```
openpay/webhook.bridge.php   ← recibe eventos de accesorios (HMAC)
openpay/status.bridge.php    ← API para success.php (boletas por order_id)
src/Application/Webhook/     ← procesador + BD
controllers/paymentBackupsController.php
```

### Accesorios (carpeta `service-payment-server/` → `public_html/`)

```
openpay/webhook.php          ← entrada OpenPay
openpay/success.php          ← pantalla post-PSE (polling)
lib/WebhookFileStorage.php   ← cola temporal en disco
config.php / .env
```

---

## Variables de entorno

### Principal (`.env-cr`)

```env
OPENPAY_BRIDGE_SECRET=secreto_largo_compartido
OPENPAY_STATUS_TOKEN=token_largo_compartido   # puede ser igual al bridge
OPENPAY_REQUIRE_BRIDGE_SIGNATURE=true
```

### Accesorios (`$VH_ROOT/.env`)

```env
OPENPAY_WEBHOOK_USER=usuario_webhook_openpay
OPENPAY_WEBHOOK_PASSWORD=clave_webhook_openpay

OPENPAY_RETURN_URL=https://accesorios.caballosrevelo.com/openpay/success.php

OPENPAY_WEBHOOK_FORWARD_URL=https://caballosrevelo.com/openpay/webhook.bridge.php
OPENPAY_STATUS_API_URL=https://caballosrevelo.com/openpay/status.bridge.php

OPENPAY_BRIDGE_SECRET=secreto_largo_compartido    # IGUAL que principal
OPENPAY_STATUS_TOKEN=token_largo_compartido         # IGUAL que principal

DEBUG_MODE=false
```

`OPENPAY_BRIDGE_SECRET` y `OPENPAY_STATUS_TOKEN` deben ser **idénticos** en ambos servidores.

---

## OpenPay — webhook registrado

URL en panel OpenPay (sandbox o producción):

```
https://accesorios.caballosrevelo.com/openpay/webhook.php
```

Registrar desde el principal (con acceso a API OpenPay):

```bash
php database/scripts/openpay_register_webhook.php \
  --url=https://accesorios.caballosrevelo.com/openpay/webhook.php
```

Usuario/clave del webhook = `OPENPAY_WEBHOOK_USER` / `OPENPAY_WEBHOOK_PASSWORD` en accesorios.

---

## Despliegue en hosting compartido

### 1. Principal

1. Subir código completo a `public_html/`
2. `.env-cr` **fuera** de `public_html`
3. Crear `logs/` fuera de `public_html` (755)
4. Verificar URLs:
   - `https://caballosrevelo.com/openpay/webhook.bridge.php` → no 404
   - `https://caballosrevelo.com/openpay/status.bridge.php` → no 404

### 2. Accesorios

1. Subir **contenido** de `service-payment-server/` a `public_html/`
2. `.env` en `$VH_ROOT/.env` (un nivel arriba de `public_html`)
3. Crear fuera de `public_html`:

```
$VH_ROOT/
├── .env
└── logs/                     ← 755
```

4. Al primer webhook, PHP crea automáticamente (dentro de `public_html`):

```
openpay/webhooks/pending/     ← 777
openpay/webhooks/processed/   ← 777
openpay/webhooks/error/       ← 777
```

Si hace falta, créalas manualmente en cPanel con permisos **777**. Incluye `openpay/webhooks/.htaccess` (bloquea acceso web).

5. Actualizar `.env` con URLs de **producción** (no ngrok)

---

## Desarrollo local (ngrok)

Solo para pruebas. Accesorios en producción puede apuntar temporalmente a ngrok:

```env
OPENPAY_WEBHOOK_FORWARD_URL=https://xxxx.ngrok-free.app/openpay/webhook.bridge.php
OPENPAY_STATUS_API_URL=https://xxxx.ngrok-free.app/openpay/status.bridge.php
```

En local, el principal **no necesita** `data/webhooks/` — solo BD.

**No dejar ngrok en producción:** si ngrok cae, los webhooks fallan y quedan en `error/` en accesorios.

---

## Logs y diagnóstico

| Log | Servidor | Qué buscar |
|-----|----------|------------|
| `payment-server.log` | accesorios | `FORWARD HTTP 200` = OK |
| `payment-server.log` | accesorios | `FORWARD HTTP 500/404` = problema en principal |
| `payment-server.log` | accesorios | `moved to processed` = JSON guardado en openpay/webhooks/processed/ |
| `openpay-bridge.log` | principal | `PROCESADO uuid=... action=approved` |
| `openpay.log` | principal | Errores del procesador |

### Códigos de pedido

Formato: `PB-YYYYMMDDHHMMSSxxx` (ej. `PB-20260530063411494`).

En accesorios, archivos bajo `openpay/webhooks/`:

```
openpay/webhooks/processed/PB-20260530063411494_charge.succeeded.json
openpay/webhooks/error/PB-xxx_charge.succeeded.json   ← solo si falló el forward
```

---

## Reprocesar un pago fallido

1. **Principal:** Admin → Webhooks OpenPay → Reprocesar (usa UUID de `webhook_events`)
2. **Accesorios:** si hay JSON en `error/`, reenviar manualmente el `payload` al bridge, o corregir URL/secret y esperar nuevo evento de OpenPay

---

## Seguridad

- OpenPay → accesorios: **Basic Auth** (`OPENPAY_WEBHOOK_USER/PASSWORD`)
- Accesorios → principal: **HMAC-SHA256** (`X-Bridge-Signature`, `X-Bridge-Timestamp`, ventana 10 min)
- success.php → status API: **header** `X-Status-Token`
- `data/` y `logs/` siempre **fuera** de `public_html`

---

## Checklist producción

- [ ] Webhook OpenPay apunta a accesorios
- [ ] Bridges desplegados en caballosrevelo.com (no 404)
- [ ] Secrets iguales en ambos `.env`
- [ ] Sin ngrok en accesorios
- [ ] `DEBUG_MODE=false` en ambos
- [ ] Credenciales OpenPay de **producción** (cuando corresponda)
- [ ] Webhooks obsoletos eliminados en panel OpenPay

---

## Carpeta del microservicio

Todo lo de accesorios vive en:

```
service-payment-server/
├── README.md              ← guía rápida de despliegue
├── .env.example
├── config.php
├── lib/WebhookFileStorage.php
└── openpay/
    ├── webhook.php
    └── success.php
```

Ver también: [service-payment-server/README.md](../service-payment-server/README.md)

# Cron jobs — producción

Programar en el servidor (crontab del usuario del sitio):

```cron
# Liberar reservas de números expiradas cada 2 minutos
*/2 * * * * /usr/bin/php /ruta/absoluta/al/proyecto/database/cron/release_expired_reservations.php >> /ruta/logs/cron-reservations.log 2>&1
```

Verificar manualmente:

```bash
php database/cron/release_expired_reservations.php
```

Sin este cron, números en estado **reservado** y respaldos PSE pendientes pueden quedar bloqueados.

Los respaldos OpenPay y sus números usan el TTL de la rifa (`reservation_minutes_raffle`, por defecto **15 minutos**). Al vencer, el cron marca el respaldo como expirado y libera los números.

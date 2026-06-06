<?php
declare(strict_types=1);

/**
 * Cron: release expired ticket reservations.
 * Usage: php database/cron/release_expired_reservations.php
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../bootstrap/container.php';
require_once __DIR__ . '/../../controllers/paymentBackupsController.php';

$expiredBackups = PaymentBackupsController::expireStalePendingBackups();
$released = AppContainer::get()->tickets()->releaseExpired();
$orphans = PaymentBackupsController::cleanupOrphanedPaymentBackupTicketLinks();

echo date('Y-m-d H:i:s')
    . " — Released {$released} expired reservation(s), expired {$expiredBackups} payment backup(s), cleaned {$orphans} orphan link(s)\n";

if ($released > 0) {
    AppContainer::get()->audit()->record('tickets.released.expired', null, null, null, [
        'count' => $released,
    ]);
}

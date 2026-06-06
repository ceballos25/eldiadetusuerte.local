<?php

require_once __DIR__ . '/clientes.controller.php';
require_once __DIR__ . '/ventas.controller.php';
require_once __DIR__ . '/../bootstrap/container.php';

use App\Domain\Payment\ValueObject\PaymentBackupStatus;
use App\Domain\Ticket\ValueObject\TicketStatus;
use App\Shared\Exception\DomainException;

/**
 * Respaldo de pagos (OpenPay) — MySQL directo con reserva de números v2.
 */
class PaymentBackupsController
{
    public const TABLE_BACKUP = 'payment_backups';

    public static function crearRespaldo(array $data)
    {
        if (
            empty($data['id_raffle']) ||
            empty($data['quantity']) ||
            empty($data['amount'])
        ) {
            return ['success' => false, 'message' => 'Datos incompletos para crear respaldo'];
        }

        $cantidad = (int)$data['quantity'];
        $idRaffle = (int)$data['id_raffle'];

        $minError = VentasController::validarCantidadMinimaRifa($idRaffle, $cantidad);
        if ($minError !== null) {
            return $minError;
        }

        $calc = VentasController::calcularTotalPorPrecioRifa($idRaffle, $cantidad);
        if (!$calc['success']) {
            return $calc;
        }
        if (!VentasController::montosEquivalentesCOP((float)$data['amount'], (float)$calc['total'])) {
            return [
                'success' => false,
                'message' => 'El monto no coincide con el precio vigente. Recarga la página e intenta de nuevo.',
            ];
        }

        $ticketIds = null;
        if (!empty($data['ticket_ids']) && is_array($data['ticket_ids'])) {
            $ticketIds = array_values(array_unique(array_map('intval', $data['ticket_ids'])));
        }

        try {
            $allocationService = AppContainer::get()->checkoutAllocation();
            if (!$allocationService->isManualRaffle($idRaffle)) {
                $ticketIds = null;
            }
        } catch (DomainException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $code = 'PB-' . date('YmdHis') . rand(100, 999);

        $idCustomer = ClientesController::obtenerOCrearCliente([
            'name_customer' => $data['name_customer'],
            'lastname_customer' => $data['lastname_customer'],
            'phone_customer' => $data['phone_customer'],
            'email_customer' => $data['email_customer'],
            'department_customer' => $data['department_customer'],
            'city_customer' => $data['city_customer'],
        ]);

        if (!$idCustomer) {
            return ['success' => false, 'message' => 'No se pudo crear u obtener el cliente'];
        }

        try {
            // TTL de la rifa (p. ej. 15 min): respaldo y números expiran si no hay pago.
            $reservation = AppContainer::get()->checkoutAllocation()->reserveForPendingPayment(
                $idRaffle,
                $cantidad,
                $ticketIds,
                false
            );
        } catch (DomainException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            self::log('ERROR RESERVA: ' . $e->getMessage());

            return ['success' => false, 'message' => 'No se pudieron reservar los nros'];
        }

        $reservedIds = $reservation['ticket_ids'];
        $insert = [
            'code_payment_backup' => $code,
            'id_raffle_payment_backup' => $idRaffle,
            'id_customer_payment_backup' => $idCustomer,
            'quantity_payment_backup' => $cantidad,
            'ticket_ids_payment_backup' => $reservedIds !== []
                ? json_encode($reservedIds)
                : null,
            'amount_payment_backup' => $data['amount'],
            'currency_payment_backup' => 'COP',
            'status_payment_backup' => 1,
            'expires_at_payment_backup' => $reservation['expires_at'],
            'source_payment_backup' => $data['source_payment_backup'],
            'date_created_payment_backup' => date('Y-m-d H:i:s'),
        ];

        $metaContext = \App\Application\Marketing\MetaConversionsApi::buildCheckoutMetaContext($data);
        $insert['openpay_response_payment_backup'] = json_encode(
            ['meta' => $metaContext],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        $idBackup = Db::insert(self::TABLE_BACKUP, $insert);

        if ($idBackup <= 0) {
            if ($reservedIds !== []) {
                AppContainer::get()->tickets()->release($idRaffle, $reservedIds);
            }

            return ['success' => false, 'message' => 'Error creando respaldo'];
        }

        if ($reservedIds !== []) {
            try {
                self::linkReservedTickets($idBackup, $reservedIds);
            } catch (Throwable $e) {
                self::log('ERROR VINCULO TICKETS: ' . $e->getMessage());
                AppContainer::get()->tickets()->release($idRaffle, $reservedIds);
                self::limpiarRespaldo($idBackup);

                return ['success' => false, 'message' => 'No se pudieron vincular los nros. Intenta de nuevo.'];
            }
        }

        AppContainer::get()->audit()->record('payment.backup.created', 'payment_backup', $idBackup, null, [
            'code' => $code,
            'ticket_ids' => $reservedIds,
            'expires_at' => $reservation['expires_at'],
            'allocation_mode' => $reservation['allocation_mode'],
        ]);

        return [
            'success' => true,
            'id_payment_backup' => $idBackup,
            'code_payment_backup' => $code,
            'reserved_numbers' => $reservation['numbers'],
            'expires_at' => $reservation['expires_at'],
        ];
    }

    public static function obtenerPorCode(string $code)
    {
        $row = Db::fetchOne(
            'SELECT * FROM payment_backups WHERE code_payment_backup = :c LIMIT 1',
            [':c' => $code]
        );

        return $row ? (array)$row : null;
    }

    private static function log(string $message): void
    {
        writeAppLog('openpay.log', $message);
    }

    /** OpenPay PSE puede enviar in_progress en charge.succeeded; en BD guardamos completed al aprobar. */
    private static function openpayStatusAlAprobar(string $txStatus): string
    {
        $s = strtolower(trim($txStatus));
        if (in_array($s, ['completed', 'paid', 'in_progress', 'charge_pending'], true)) {
            return 'completed';
        }

        return $s !== '' ? $s : 'completed';
    }

    public static function aprobarPago(array $backup, array $tx)
    {
        $txStatus = strtolower(trim($tx['status'] ?? ''));
        $backupId = (int)$backup['id_payment_backup'];
        $backupStatus = (int)$backup['status_payment_backup'];

        self::log('========== INICIO APROBACIÓN ==========');
        self::log('Status TX: ' . $txStatus);
        self::log('ID Backup: ' . $backupId);

        $saleExists = Db::fetchOne(
            'SELECT id_sale FROM sales WHERE code_sale = :c LIMIT 1',
            [':c' => (string)$backup['code_payment_backup']]
        );
        if ($saleExists) {
            self::log('⚠️ La venta ya existe — id_sale=' . $saleExists->id_sale);
            self::limpiarRespaldo($backupId);

            return;
        }

        if (!in_array($backupStatus, [1, 2, 3, 4], true)) {
            self::log('⚠️ El backup no está en estado procesable: ' . $backupStatus);

            return;
        }

        if ($backupStatus === 3) {
            self::log('⚠️ Reconciliación: backup estaba rechazado, se intenta aprobar por webhook tardío');
            Db::update(
                self::TABLE_BACKUP,
                ['status_payment_backup' => 1],
                'id_payment_backup = :id',
                [':id' => $backupId]
            );
        }

        if (!in_array($txStatus, ['completed', 'paid', 'in_progress', 'charge_pending'], true)) {
            self::log('❌ APROBACIÓN BLOQUEADA - Status no es válido: ' . $txStatus);

            return;
        }

        self::log('✓ Status válido para aprobación');

        Db::update(
            self::TABLE_BACKUP,
            [
                // Evita que el cron de expiración (status=1) expire este backup
                // mientras el webhook está procesando/reintentando la creación de venta.
                'status_payment_backup' => 2,
                'openpay_status_payment_backup' => self::openpayStatusAlAprobar($txStatus),
                'openpay_response_payment_backup' => json_encode($tx, JSON_UNESCAPED_UNICODE),
            ],
            'id_payment_backup = :id',
            [':id' => $backupId]
        );

        $cantidad = (int)$backup['quantity_payment_backup'];

        if ($cantidad <= 0) {
            self::log('❌ Cantidad inválida');
            self::marcarBackupError($backupId);

            return;
        }

        self::log('Cantidad comprada: ' . $cantidad);

        $reservedIds = self::getReservedTicketIds($backupId);
        if ($reservedIds === []) {
            $json = $backup['ticket_ids_payment_backup'] ?? null;
            if (is_string($json) && $json !== '') {
                $decoded = json_decode($json, true);
                if (is_array($decoded)) {
                    $reservedIds = array_map('intval', $decoded);
                }
            }
        }

        try {
            $allocation = AppContainer::get()->checkoutAllocation()->resolveTicketIdsForSaleApproval(
                (int)$backup['id_raffle_payment_backup'],
                $cantidad,
                $reservedIds !== [] ? $reservedIds : null
            );
            $ticketIdsForSale = $allocation['ticket_ids'];

            if ($ticketIdsForSale === null && $reservedIds !== []) {
                AppContainer::get()->tickets()->release(
                    (int)$backup['id_raffle_payment_backup'],
                    $reservedIds
                );
                self::log('Reservas manuales obsoletas liberadas (rifa automática): ' . count($reservedIds));
            }
        } catch (DomainException $e) {
            self::log('❌ ' . $e->getMessage());
            self::marcarBackupError($backupId);

            return;
        }

        if ($ticketIdsForSale !== null) {
            self::log('Tickets reservados (manual): ' . count($ticketIdsForSale));
        } else {
            self::log('Asignación automática al aprobar: ' . $cantidad . ' números');
        }

        $resVenta = VentasController::crearVenta([
            'id_customer' => $backup['id_customer_payment_backup'],
            'id_raffle' => $backup['id_raffle_payment_backup'],
            'quantity_sale' => $cantidad,
            'total_sale' => $backup['amount_payment_backup'],
            'code_sale' => $backup['code_payment_backup'],
            'payment_method_sale' => 'Página Web',
            'source_sale' => $backup['source_payment_backup'] ?? null,
            'id_admin_sale' => 99,
            'id_payment_backup' => $backupId,
            'meta_user_data' => \App\Application\Marketing\MetaConversionsApi::userDataFromPaymentBackup($backup),
        ], $ticketIdsForSale, false);

        if (!empty($resVenta['success']) && !empty($resVenta['id_sale'])) {
            self::log('✓ Venta creada correctamente');
            self::log('ID Venta: ' . $resVenta['id_sale']);

            Db::update(
                self::TABLE_BACKUP,
                ['status_payment_backup' => 2],
                'id_payment_backup = :id',
                [':id' => $backupId]
            );

            self::log('✓ Venta creada (correo enviado desde crearVenta)');

            self::limpiarRespaldo($backupId);
            self::log('✓ Respaldo eliminado');
        } else {
            self::log('❌ Error creando venta: ' . ($resVenta['message'] ?? 'desconocido'));
            self::marcarBackupError($backupId);
        }

        self::log('========== FIN APROBACIÓN ==========');
    }

    private static function marcarBackupError(int $backupId): void
    {
        Db::update(
            self::TABLE_BACKUP,
            ['status_payment_backup' => 4],
            'id_payment_backup = :id',
            [':id' => $backupId]
        );
        self::log('⚠️ Backup marcado como ERROR');
    }

    public static function rechazarPago(array $backup, array $tx)
    {
        try {
            $idBackup = (int)$backup['id_payment_backup'];
            $status = (int)$backup['status_payment_backup'];

            self::log('========== INICIO RECHAZO ==========');
            self::log('ID Backup: ' . $idBackup);

            if ($status === 3) {
                self::releaseBackupTickets($backup);
                self::log('✓ Rechazo duplicado — números liberados si aún estaban reservados');
                self::log('========== FIN RECHAZO ==========');

                return;
            }

            if ($status !== 1) {
                self::log('⚠️ Rechazo ignorado — backup no está pendiente (status=' . $status . ')');
                self::log('========== FIN RECHAZO ==========');

                return;
            }

            Db::update(
                self::TABLE_BACKUP,
                [
                    'status_payment_backup' => 3,
                    'openpay_status_payment_backup' => $tx['status'] ?? 'failed',
                    'openpay_response_payment_backup' => json_encode($tx, JSON_UNESCAPED_UNICODE),
                ],
                'id_payment_backup = :id',
                [':id' => $idBackup]
            );

            self::releaseBackupTickets($backup);

            AppContainer::get()->audit()->record('payment.backup.rejected', 'payment_backup', $idBackup, $backup, [
                'tx_status' => $tx['status'] ?? null,
            ]);

            self::log('✓ Respaldo marcado como RECHAZADO');
            self::log('========== FIN RECHAZO ==========');
        } catch (Exception $e) {
            self::log('❌ ERROR: ' . $e->getMessage());
        }
    }

    /** Libera tickets reservados sin cambiar estado (reintento de rechazo). */
    public static function liberarTicketsReservados(array $backup): void
    {
        self::releaseBackupTickets($backup);
    }

    private static function limpiarRespaldo(int $idBackup): void
    {
        try {
            self::log('========== LIMPIANDO RESPALDO ==========');
            self::log('ID Backup: ' . $idBackup);

            Db::delete('payment_backup_tickets', 'id_payment_backup = :id', [':id' => $idBackup]);
            $n = Db::delete(self::TABLE_BACKUP, 'id_payment_backup = :id', [':id' => $idBackup]);

            self::log($n > 0 ? '✓ Respaldo eliminado correctamente' : '⚠️ No se pudo eliminar el respaldo');
            self::log('========== FIN LIMPIEZA RESPALDO ==========');
        } catch (Exception $e) {
            self::log('❌ ERROR LIMPIANDO RESPALDO: ' . $e->getMessage());
        }
    }

    /**
     * Marca respaldos PSE pendientes vencidos y libera sus números.
     */
    public static function expireStalePendingBackups(): int
    {
        $rows = Db::fetchAll(
            'SELECT * FROM payment_backups
             WHERE status_payment_backup = :pending
               AND (
                 (expires_at_payment_backup IS NOT NULL AND expires_at_payment_backup < NOW())
                 OR (
                   expires_at_payment_backup IS NULL
                   AND date_created_payment_backup < DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                 )
               )',
            [':pending' => PaymentBackupStatus::PENDING]
        );

        $expired = 0;

        foreach ($rows as $row) {
            $backup = (array)$row;
            $idBackup = (int)$backup['id_payment_backup'];

            Db::update(
                self::TABLE_BACKUP,
                ['status_payment_backup' => PaymentBackupStatus::EXPIRED],
                'id_payment_backup = :id',
                [':id' => $idBackup]
            );

            $backup['status_payment_backup'] = PaymentBackupStatus::EXPIRED;
            self::releaseBackupTickets($backup);

            AppContainer::get()->audit()->record('payment.backup.expired', 'payment_backup', $idBackup, null, [
                'code' => $backup['code_payment_backup'] ?? null,
                'expires_at' => $backup['expires_at_payment_backup'] ?? null,
            ]);

            self::log('✓ Respaldo expirado: ' . ($backup['code_payment_backup'] ?? $idBackup));
            $expired++;
        }

        return $expired;
    }

    /**
     * Elimina vínculos huérfanos en payment_backup_tickets (ticket ya liberado).
     *
     * @param list<int>|null $ticketIds Si es null, limpia todos los tickets disponibles.
     */
    public static function cleanupOrphanedPaymentBackupTicketLinks(?array $ticketIds = null): int
    {
        if ($ticketIds === null) {
            return Db::execute(
                'DELETE pbt FROM payment_backup_tickets pbt
                 INNER JOIN tickets t ON t.id_ticket = pbt.id_ticket
                 WHERE t.status_ticket = :available',
                [':available' => TicketStatus::AVAILABLE]
            );
        }

        $ticketIds = array_values(array_unique(array_map('intval', $ticketIds)));
        if ($ticketIds === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));

        return Db::execute(
            "DELETE FROM payment_backup_tickets WHERE id_ticket IN ({$placeholders})",
            $ticketIds
        );
    }

    /**
     * @param list<int> $ticketIds
     */
    private static function linkReservedTickets(int $idBackup, array $ticketIds): void
    {
        self::cleanupOrphanedPaymentBackupTicketLinks($ticketIds);

        foreach ($ticketIds as $ticketId) {
            Db::insert('payment_backup_tickets', [
                'id_payment_backup' => $idBackup,
                'id_ticket' => $ticketId,
            ]);
        }
    }

    /**
     * @return list<int>
     */
    private static function getReservedTicketIds(int $idBackup): array
    {
        $rows = Db::fetchAll(
            'SELECT id_ticket FROM payment_backup_tickets WHERE id_payment_backup = :id',
            [':id' => $idBackup]
        );

        return array_map(static fn ($r) => (int)$r->id_ticket, $rows);
    }

    private static function releaseBackupTickets(array $backup): void
    {
        $idBackup = (int)$backup['id_payment_backup'];
        $idRaffle = (int)$backup['id_raffle_payment_backup'];
        $ticketIds = self::getReservedTicketIds($idBackup);

        if ($ticketIds === []) {
            $json = $backup['ticket_ids_payment_backup'] ?? null;
            if (is_string($json) && $json !== '') {
                $decoded = json_decode($json, true);
                if (is_array($decoded)) {
                    $ticketIds = array_map('intval', $decoded);
                }
            }
        }

        if ($ticketIds !== []) {
            $released = AppContainer::get()->tickets()->release($idRaffle, $ticketIds);
            self::log('✓ Números liberados: ' . $released);
            AppContainer::get()->audit()->record('tickets.released.payment_failed', 'payment_backup', $idBackup, null, [
                'released' => $released,
                'ticket_ids' => $ticketIds,
            ]);
        }

        Db::delete('payment_backup_tickets', 'id_payment_backup = :id', [':id' => $idBackup]);
    }
}

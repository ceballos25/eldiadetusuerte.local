<?php

require_once __DIR__ . '/clientes.controller.php';
require_once __DIR__ . '/ventas.controller.php';
require_once __DIR__ . '/mail.controller.php';
require_once __DIR__ . '/../bootstrap/container.php';

use App\Shared\Exception\DomainException;

class TransfersController
{
    public const TABLE = 'transfers';

    public static function crearTransferencia(array $data)
    {
        if (
            !isset($data['id_raffle']) || trim((string)$data['id_raffle']) === ''
            || !isset($data['quantity']) || trim((string)$data['quantity']) === ''
        ) {
            return ['success' => false, 'message' => 'Datos incompletos'];
        }

        $cantidad = (int)$data['quantity'];
        $idRaffle = (int)$data['id_raffle'];

        $minError = VentasController::validarCantidadMinimaRifa($idRaffle, $cantidad);
        if ($minError !== null) {
            return $minError;
        }

        $countRow = Db::fetchOne(
            'SELECT COUNT(*) AS c FROM tickets WHERE id_raffle_ticket = :r AND status_ticket = 0',
            [':r' => (int)$data['id_raffle']]
        );
        $disponibles = (int)($countRow->c ?? 0);

        if ($disponibles < $cantidad) {
            return [
                'success' => false,
                'message' => $disponibles < 1 ? 'No hay nros disponibles' : 'No hay suficientes nros disponibles',
            ];
        }

        $calc = VentasController::calcularTotalPorPrecioRifa((int)$data['id_raffle'], $cantidad);
        if (!$calc['success']) {
            return $calc;
        }
        $montoEsperado = $calc['total'];
        if (isset($data['amount']) && trim((string)$data['amount']) !== '') {
            $montoCliente = (float)$data['amount'];
            if (!VentasController::montosEquivalentesCOP($montoCliente, $montoEsperado)) {
                return [
                    'success' => false,
                    'message' => 'El monto no coincide con el precio vigente. Recarga la página e intenta de nuevo.',
                ];
            }
        }

        $code = str_pad((string)random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);

        $idCustomer = ClientesController::obtenerOCrearCliente([
            'name_customer' => $data['name_customer'],
            'lastname_customer' => $data['lastname_customer'],
            'phone_customer' => $data['phone_customer'],
            'email_customer' => $data['email_customer'],
            'department_customer' => $data['department_customer'],
            'city_customer' => $data['city_customer'],
        ]);

        if (!$idCustomer) {
            return ['success' => false, 'message' => 'Error con cliente'];
        }

        $ticketIdsInput = null;
        if (!empty($data['ticket_ids']) && is_array($data['ticket_ids'])) {
            $ticketIdsInput = array_values(array_unique(array_map('intval', $data['ticket_ids'])));
        }

        try {
            $allocationService = AppContainer::get()->checkoutAllocation();
            if (!$allocationService->isManualRaffle((int)$data['id_raffle'])) {
                $ticketIdsInput = null;
            } elseif ($ticketIdsInput !== null && count($ticketIdsInput) !== $cantidad) {
                return ['success' => false, 'message' => 'La cantidad no coincide con los nros seleccionados'];
            }
        } catch (DomainException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $ticketIds = null;
        try {
            $reservation = AppContainer::get()->checkoutAllocation()->reserveForPendingPayment(
                (int)$data['id_raffle'],
                $cantidad,
                $ticketIdsInput,
                true
            );
            $ticketIds = $reservation['ticket_ids'] !== [] ? $reservation['ticket_ids'] : null;
        } catch (DomainException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (Throwable) {
            return ['success' => false, 'message' => 'No se pudieron reservar los nros'];
        }

        $insert = [
            'code_transfer' => $code,
            'id_raffle_transfer' => (int)$data['id_raffle'],
            'id_customer_transfer' => $idCustomer,
            'quantity_transfer' => $cantidad,
            'amount_transfer' => $montoEsperado,
            'currency_transfer' => 'COP',
            'status_transfer' => 1,
            'source_transfer' => $data['source_transfer'] ?? null,
            'date_created_transfer' => date('Y-m-d H:i:s'),
        ];
        $metaContext = \App\Application\Marketing\MetaConversionsApi::buildCheckoutMetaContext($data);
        if ($metaContext !== []) {
            $insert['meta_transfer'] = json_encode($metaContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if ($ticketIds !== null) {
            $insert['ticket_ids_transfer'] = json_encode($ticketIds);
        }

        $idTransfer = Db::insert(self::TABLE, $insert);

        if ($idTransfer <= 0) {
            if ($ticketIds !== null) {
                AppContainer::get()->tickets()->release((int)$data['id_raffle'], $ticketIds);
            }

            return ['success' => false, 'message' => 'Error creando transferencia'];
        }

        return [
            'success' => true,
            'id_transfer' => $idTransfer,
            'code_transfer' => $code,
        ];
    }

    public static function obtenerPorCode(string $code)
    {
        $code = trim($code);
        $row = Db::fetchOne(
            'SELECT * FROM transfers WHERE code_transfer = :c LIMIT 1',
            [':c' => $code]
        );

        return $row ? (array)$row : null;
    }

    public static function aprobarTransferencia(array $transfer)
    {
        $transfer = self::loadTransferForAction($transfer);
        if ($transfer === null) {
            return ['success' => false, 'message' => 'Transferencia no encontrada'];
        }

        if ((int)$transfer['status_transfer'] !== 1) {
            return ['success' => false, 'message' => 'Ya procesado'];
        }

        $cantidad = (int)$transfer['quantity_transfer'];
        $idRaffle = (int)$transfer['id_raffle_transfer'];

        if ($cantidad <= 0) {
            return ['success' => false, 'message' => 'Cantidad inválida'];
        }

        $reservedIds = \App\Application\Ticket\RaffleCheckoutAllocationService::decodeTicketIds(
            $transfer['ticket_ids_transfer'] ?? null
        );

        try {
            if (!AppContainer::get()->checkoutAllocation()->isManualRaffle($idRaffle) && $reservedIds !== []) {
                AppContainer::get()->tickets()->release($idRaffle, $reservedIds);
                $reservedIds = [];
            }
        } catch (DomainException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        if ($reservedIds === []) {
            $countRow = Db::fetchOne(
                'SELECT COUNT(*) AS c FROM tickets WHERE id_raffle_ticket = :r AND status_ticket = 0',
                [':r' => $idRaffle]
            );
            $disponibles = (int)($countRow->c ?? 0);

            if ($disponibles < $cantidad) {
                return ['success' => false, 'message' => 'No hay suficientes nros disponibles'];
            }
        } elseif (!self::verifyReservedTicketsStillHeld($idRaffle, $reservedIds)) {
            return [
                'success' => false,
                'message' => 'Algunos nros reservados ya no están disponibles. Rechace la solicitud para liberarlos o pida al cliente una nueva compra.',
            ];
        }

        $calc = VentasController::calcularTotalPorPrecioRifa((int)$transfer['id_raffle_transfer'], $cantidad);
        if (!$calc['success']) {
            return $calc;
        }
        $totalEsperado = $calc['total'];
        $montoReg = (float)($transfer['amount_transfer'] ?? 0);
        if (!VentasController::montosEquivalentesCOP($montoReg, $totalEsperado)) {
            return [
                'success' => false,
                'message' => 'El monto guardado en la transferencia no coincide con el precio vigente. Rechace la solicitud o actualice el precio y vuelva a intentar.',
            ];
        }

        try {
            $allocation = AppContainer::get()->checkoutAllocation()->resolveTicketIdsForSaleApproval(
                $idRaffle,
                $cantidad,
                $reservedIds !== [] ? $reservedIds : null
            );
            $ticketIdsForSale = $allocation['ticket_ids'];
        } catch (DomainException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $resVenta = VentasController::crearVenta([
            'id_customer' => $transfer['id_customer_transfer'],
            'id_raffle' => $transfer['id_raffle_transfer'],
            'quantity_sale' => $cantidad,
            'total_sale' => $totalEsperado,
            'code_sale' => $transfer['code_transfer'],
            'payment_method_sale' => 'Transferencia',
            'id_admin' => $_SESSION['user_id'] ?? null,
            'source_sale' => $transfer['source_transfer'] ?? null,
            'meta_user_data' => \App\Application\Marketing\MetaConversionsApi::userDataFromStoredMeta(
                \App\Application\Marketing\MetaConversionsApi::extractStoredMeta($transfer)
            ),
        ], $ticketIdsForSale, true);

        if (empty($resVenta['success']) || empty($resVenta['id_sale'])) {
            return ['success' => false, 'message' => $resVenta['message'] ?? 'Error creando la venta'];
        }

        $n = Db::update(
            self::TABLE,
            ['status_transfer' => 2],
            'id_transfer = :id AND status_transfer = 1',
            [':id' => (int)$transfer['id_transfer']]
        );

        if ($n < 1) {
            return [
                'success' => false,
                'message' => 'La venta se creó pero no se pudo marcar la transferencia como aprobada. Revisar manualmente.',
                'id_sale' => (int)$resVenta['id_sale'],
            ];
        }

        return [
            'success' => true,
            'id_sale' => (int)$resVenta['id_sale'],
            'message' => 'Venta creada correctamente',
        ];
    }

    public static function rechazarTransferencia(array $transfer)
    {
        $transfer = self::loadTransferForAction($transfer);
        if ($transfer === null) {
            return ['success' => false, 'message' => 'Transferencia no encontrada'];
        }

        $rawIds = $transfer['ticket_ids_transfer'] ?? null;
        if ($rawIds) {
            $decoded = is_string($rawIds) ? json_decode($rawIds, true) : $rawIds;
            if (is_array($decoded) && $decoded !== []) {
                try {
                    AppContainer::get()->tickets()->release(
                        (int)$transfer['id_raffle_transfer'],
                        array_map('intval', $decoded)
                    );
                } catch (Throwable) {
                    /* best effort */
                }
            }
        }

        $n = Db::update(
            self::TABLE,
            ['status_transfer' => 3],
            'id_transfer = :id',
            [':id' => (int)$transfer['id_transfer']]
        );

        if ($n < 1) {
            return ['success' => false, 'message' => 'Error al rechazar'];
        }

        return ['success' => true, 'message' => 'Transferencia rechazada'];
    }

    public static function obtenerTransferencias(array $params = []): array
    {
        $page = max(1, (int)($params['page'] ?? $_POST['page'] ?? 1));
        $limit = min(50, max(1, (int)($params['limit'] ?? $_POST['limit'] ?? 10)));
        $offset = ($page - 1) * $limit;

        $statusRaw = $params['status'] ?? $_POST['status'] ?? '1';
        $status = trim((string)$statusRaw);
        $search = trim((string)($params['search'] ?? $_POST['search'] ?? ''));
        $fechaInicio = trim((string)($params['fecha_inicio'] ?? $_POST['fecha_inicio'] ?? ''));
        $fechaFin = trim((string)($params['fecha_fin'] ?? $_POST['fecha_fin'] ?? ''));

        $where = ['1=1'];
        $bind = [];

        if ($status !== '') {
            $where[] = 't.status_transfer = :status';
            $bind[':status'] = (int)$status;
        }

        if ($search !== '') {
            $where[] = '(t.code_transfer LIKE :q OR c.name_customer LIKE :q OR c.lastname_customer LIKE :q
                OR c.phone_customer LIKE :q OR c.email_customer LIKE :q)';
            $bind[':q'] = '%' . $search . '%';
        }

        if ($fechaInicio !== '' && $fechaFin !== '') {
            $where[] = 'DATE(t.date_created_transfer) BETWEEN :fi AND :ff';
            $bind[':fi'] = $fechaInicio;
            $bind[':ff'] = $fechaFin;
        }

        $whereSql = implode(' AND ', $where);
        $fromSql = ' FROM transfers t INNER JOIN customers c ON c.id_customer = t.id_customer_transfer';

        $selectSql = 'SELECT t.id_transfer,t.code_transfer,t.quantity_transfer,t.amount_transfer,t.status_transfer,
            t.date_created_transfer,t.source_transfer,t.url_transfer,t.id_raffle_transfer,t.id_customer_transfer,
            t.ticket_ids_transfer,t.expires_at_transfer,
            c.name_customer,c.lastname_customer,c.phone_customer,c.email_customer,c.city_customer,
            r.title_raffle,r.type_raffle'
            . $fromSql
            . ' INNER JOIN raffles r ON r.id_raffle = t.id_raffle_transfer'
            . ' WHERE ' . $whereSql
            . ' ORDER BY t.id_transfer DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;

        $lista = Db::fetchAll($selectSql, $bind);
        $lista = self::enrichTransfersWithReservedNumbers($lista);

        $countSt = Db::pdo()->prepare('SELECT COUNT(*)' . $fromSql . ' WHERE ' . $whereSql);
        $countSt->execute($bind);
        $total = (int)$countSt->fetchColumn();

        $pendingSt = Db::pdo()->query('SELECT COUNT(*) FROM transfers WHERE status_transfer = 1');
        $pendingTotal = (int)$pendingSt->fetchColumn();

        return [
            'success' => true,
            'data' => $lista,
            'total' => $total,
            'pending_total' => $pendingTotal,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    /**
     * @param array<string, mixed> $transfer
     * @return array<string, mixed>|null
     */
    private static function loadTransferForAction(array $transfer): ?array
    {
        $id = (int)($transfer['id_transfer'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        $row = Db::fetchOne(
            'SELECT * FROM transfers WHERE id_transfer = :id LIMIT 1',
            [':id' => $id]
        );

        if (!$row) {
            return null;
        }

        // La BD es la fuente de verdad; el POST del admin puede traer status obsoleto.
        return (array)$row;
    }

    /**
     * @param list<int> $ticketIds
     */
    private static function verifyReservedTicketsStillHeld(int $raffleId, array $ticketIds): bool
    {
        if ($ticketIds === []) {
            return true;
        }

        $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));
        $sql = "SELECT COUNT(*) FROM tickets
                WHERE id_raffle_ticket = ?
                  AND status_ticket = 2
                  AND id_ticket IN ({$placeholders})";
        $params = array_merge([$raffleId], $ticketIds);
        $st = Db::pdo()->prepare($sql);
        $st->execute($params);

        return (int)$st->fetchColumn() === count($ticketIds);
    }

    /**
     * @param list<object|array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private static function enrichTransfersWithReservedNumbers(array $rows): array
    {
        $allocation = AppContainer::get()->checkoutAllocation();
        $out = [];

        foreach ($rows as $row) {
            $item = (array)$row;
            $idRaffle = (int)($item['id_raffle_transfer'] ?? 0);
            $ticketIds = \App\Application\Ticket\RaffleCheckoutAllocationService::decodeTicketIds(
                $item['ticket_ids_transfer'] ?? null
            );
            $numbers = $ticketIds !== []
                ? $allocation->numbersForTicketIds($idRaffle, $ticketIds)
                : [];
            $type = (string)($item['type_raffle'] ?? 'automatic');
            $item['reserved_numbers'] = $numbers;
            $item['reserved_numbers_label'] = $numbers !== []
                ? implode(', ', $numbers)
                : ($type === 'manual' ? '—' : 'Automático al aprobar');
            $out[] = $item;
        }

        return $out;
    }

    public static function obtenerSettings()
    {
        $rows = Db::fetchAll('SELECT key_setting, value_setting FROM settings');

        $map = [];
        foreach ($rows as $item) {
            $map[$item->key_setting] = $item->value_setting;
        }

        return $map;
    }
}

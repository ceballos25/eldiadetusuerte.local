<?php
declare(strict_types=1);

namespace App\Application\Webhook;

use App\Application\Audit\AuditService;
use App\Domain\Payment\ValueObject\PaymentBackupStatus;
use App\Domain\Webhook\Repository\WebhookRepositoryInterface;
use App\Shared\Exception\DomainException;

/**
 * Processes OpenPay webhook events with idempotency and full traceability.
 * Delegates payment approval/rejection to legacy PaymentBackupsController until full migration.
 */
final class OpenPayWebhookProcessor
{
    public function __construct(
        private readonly WebhookRepositoryInterface $webhooks,
        private readonly WebhookStorageService $storage,
        private readonly AuditService $audit
    ) {
    }

    public function process(array $payload, string $source = 'openpay'): array
    {
        $stored = $this->storage->store($source, $payload);
        $webhookId = $stored['id'];
        $uuid = $stored['uuid'];
        $filename = $stored['filename'];

        if (!$this->webhooks->markProcessing($webhookId)) {
            $existing = $this->webhooks->findByUuid($uuid);
            if ($existing && $existing['status_webhook'] === 'processed') {
                return ['success' => true, 'message' => 'already_processed', 'uuid' => $uuid];
            }
        }

        try {
            $result = $this->handleEvent($payload);
            $this->webhooks->markProcessed($webhookId);
            $this->storage->moveToProcessed($filename);

            $this->audit->record('webhook.processed', 'webhook', $webhookId, null, [
                'uuid' => $uuid,
                'event' => $payload['type'] ?? null,
                'result' => $result,
            ]);

            return ['success' => true, 'uuid' => $uuid, 'result' => $result];
        } catch (\Throwable $e) {
            $this->webhooks->markError($webhookId, $e->getMessage());
            $this->storage->moveToError($filename);

            $this->audit->record('webhook.error', 'webhook', $webhookId, null, [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);

            if (function_exists('writeAppLog')) {
                writeAppLog('openpay.log', "Webhook error [{$uuid}]: " . $e->getMessage());
            }

            throw $e;
        }
    }

    /**
     * Reprocess a failed webhook by UUID.
     */
    public function reprocess(string $uuid): array
    {
        $data = $this->storage->reprocessFromFile($uuid);
        if ($data === null) {
            $row = $this->webhooks->findByUuid($uuid);
            if ($row === null) {
                throw new DomainException('Webhook no encontrado', 'WEBHOOK_NOT_FOUND');
            }
            $payload = json_decode($row['payload_webhook'], true);
            if (!is_array($payload)) {
                throw new DomainException('Payload inválido', 'WEBHOOK_INVALID');
            }
        } else {
            $payload = $data['payload'] ?? $data;
        }

        return $this->process(is_array($payload) ? $payload : [], 'openpay-reprocess');
    }

    private function handleEvent(array $data): array
    {
        $normalized = OpenPayWebhookPayloadNormalizer::normalize($data);
        $type = $normalized['type'];
        $tx = $normalized['transaction'];

        if ($type === OpenPayWebhookEventTypes::VERIFICATION) {
            return ['action' => 'verified'];
        }

        if ($type !== null && in_array($type, OpenPayWebhookEventTypes::ignoredEvents(), true)) {
            return ['action' => 'ignored', 'reason' => 'informational_event', 'event' => $type];
        }

        if (!$type || !$tx || empty($tx['order_id'])) {
            return ['action' => 'ignored', 'reason' => 'incomplete_data', 'event' => $type];
        }

        $code = (string)$tx['order_id'];

        if (!class_exists(\PaymentBackupsController::class)) {
            throw new DomainException('PaymentBackupsController no disponible', 'LEGACY_MISSING');
        }

        $backup = \PaymentBackupsController::obtenerPorCode($code);
        if (!$backup) {
            return ['action' => 'ignored', 'reason' => 'backup_not_found', 'code' => $code];
        }

        $backupStatus = (int)$backup['status_payment_backup'];

        if (in_array($type, OpenPayWebhookEventTypes::rejectedEvents(), true)) {
            $rejectDecision = OpenPayWebhookGuard::evaluateRejectedEvent($type, $tx, $backupStatus);

            if ($rejectDecision['decision'] === 'reject') {
                \PaymentBackupsController::rechazarPago($backup, $tx);

                return ['action' => 'rejected', 'code' => $code, 'event' => $type];
            }

            if ($rejectDecision['decision'] === 'release_reserved') {
                \PaymentBackupsController::liberarTicketsReservados($backup);

                return ['action' => 'ignored', 'reason' => 'already_rejected', 'code' => $code];
            }

            return [
                'action' => 'ignored',
                'reason' => $rejectDecision['reason'] ?? 'reject_not_applicable',
                'code' => $code,
                'event' => $type,
                'tx_status' => $rejectDecision['tx_status'] ?? OpenPayTransactionStatus::statusFromTransaction($tx),
            ];
        }

        $saleExists = \Db::fetchOne(
            'SELECT id_sale FROM sales WHERE code_sale = :c LIMIT 1',
            [':c' => $code]
        ) !== null;

        if ($saleExists) {
            return ['action' => 'ignored', 'reason' => 'sale_exists', 'code' => $code];
        }

        $canApprove = OpenPayWebhookGuard::canApproveBackup($backupStatus, $type);

        if (!$canApprove) {
            return ['action' => 'ignored', 'reason' => 'already_processed', 'code' => $code];
        }

        if (in_array($type, OpenPayWebhookEventTypes::approvedEvents(), true)) {
            \PaymentBackupsController::aprobarPago($backup, $tx);

            return ['action' => 'approved', 'code' => $code, 'event' => $type];
        }

        return ['action' => 'ignored', 'reason' => 'unknown_event', 'event' => $type];
    }
}

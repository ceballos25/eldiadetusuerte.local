<?php
declare(strict_types=1);

/**
 * Application service container — centralizes dependency wiring.
 */
final class AppContainer
{
    private static ?self $instance = null;

    private readonly \App\Infrastructure\Repository\PdoTicketRepository $ticketRepository;
    private readonly \App\Infrastructure\Repository\PdoRaffleRepository $raffleRepository;
    private readonly \App\Infrastructure\Repository\PdoAuditRepository $auditRepository;
    private readonly \App\Infrastructure\Repository\PdoWebhookRepository $webhookRepository;
    private readonly \App\Infrastructure\Repository\PdoPermissionRepository $permissionRepository;
    private readonly \App\Shared\Audit\AuditLogger $fileAuditLogger;
    private readonly \App\Application\Audit\AuditService $auditService;
    private readonly \App\Application\Ticket\TicketReservationService $ticketReservationService;
    private readonly \App\Application\Ticket\RaffleCheckoutAllocationService $checkoutAllocationService;
    private readonly \App\Application\Reservation\PendingReservationService $pendingReservationService;
    private readonly \App\Application\Sale\SaleCancellationService $saleCancellationService;
    private readonly \App\Application\Webhook\WebhookStorageService $webhookStorageService;
    private readonly \App\Application\Webhook\OpenPayWebhookProcessor $openPayWebhookProcessor;
    private readonly \App\Application\Webhook\OpenPayWebhookRegistrationService $openPayWebhookRegistration;
    private readonly \App\Application\Raffle\RaffleService $raffleService;
    private readonly \App\Application\Maintenance\MaintenanceService $maintenanceService;
    private readonly \App\Shared\Config\DynamicConfig $config;

    private function __construct()
    {
        $this->config = new \App\Shared\Config\DynamicConfig();
        $this->ticketRepository = new \App\Infrastructure\Repository\PdoTicketRepository();
        $this->raffleRepository = new \App\Infrastructure\Repository\PdoRaffleRepository();
        $this->auditRepository = new \App\Infrastructure\Repository\PdoAuditRepository();
        $this->webhookRepository = new \App\Infrastructure\Repository\PdoWebhookRepository();
        $this->permissionRepository = new \App\Infrastructure\Repository\PdoPermissionRepository();
        $this->fileAuditLogger = new \App\Shared\Audit\AuditLogger();
        $this->auditService = new \App\Application\Audit\AuditService($this->auditRepository, $this->fileAuditLogger);
        $this->ticketReservationService = new \App\Application\Ticket\TicketReservationService(
            $this->ticketRepository,
            $this->raffleRepository
        );
        $this->checkoutAllocationService = new \App\Application\Ticket\RaffleCheckoutAllocationService(
            $this->ticketReservationService,
            $this->raffleRepository
        );
        $this->pendingReservationService = new \App\Application\Reservation\PendingReservationService(
            $this->checkoutAllocationService
        );
        $this->saleCancellationService = new \App\Application\Sale\SaleCancellationService(
            $this->ticketRepository,
            $this->auditService
        );
        $this->webhookStorageService = new \App\Application\Webhook\WebhookStorageService($this->webhookRepository);
        $this->openPayWebhookProcessor = new \App\Application\Webhook\OpenPayWebhookProcessor(
            $this->webhookRepository,
            $this->webhookStorageService,
            $this->auditService
        );
        $this->openPayWebhookRegistration = new \App\Application\Webhook\OpenPayWebhookRegistrationService(
            \App\Infrastructure\OpenPay\OpenPayHttpClient::fromConfig()
        );
        $this->raffleService = new \App\Application\Raffle\RaffleService(
            $this->raffleRepository,
            $this->auditService
        );
        $this->maintenanceService = new \App\Application\Maintenance\MaintenanceService($this->config);
    }

    public static function get(): self
    {
        return self::$instance ??= new self();
    }

    public function audit(): \App\Application\Audit\AuditService
    {
        return $this->auditService;
    }

    public function tickets(): \App\Application\Ticket\TicketReservationService
    {
        return $this->ticketReservationService;
    }

    public function checkoutAllocation(): \App\Application\Ticket\RaffleCheckoutAllocationService
    {
        return $this->checkoutAllocationService;
    }

    public function pendingReservations(): \App\Application\Reservation\PendingReservationService
    {
        return $this->pendingReservationService;
    }

    public function sales(): \App\Application\Sale\SaleCancellationService
    {
        return $this->saleCancellationService;
    }

    public function webhooks(): \App\Application\Webhook\OpenPayWebhookProcessor
    {
        return $this->openPayWebhookProcessor;
    }

    public function openPayWebhookRegistration(): \App\Application\Webhook\OpenPayWebhookRegistrationService
    {
        return $this->openPayWebhookRegistration;
    }

    public function raffles(): \App\Application\Raffle\RaffleService
    {
        return $this->raffleService;
    }

    public function permissions(): \App\Infrastructure\Repository\PdoPermissionRepository
    {
        return $this->permissionRepository;
    }

    public function maintenance(): \App\Application\Maintenance\MaintenanceService
    {
        return $this->maintenanceService;
    }

    public function config(): \App\Shared\Config\DynamicConfig
    {
        return $this->config;
    }

    public function raffleRepository(): \App\Infrastructure\Repository\PdoRaffleRepository
    {
        return $this->raffleRepository;
    }
}

<?php
declare(strict_types=1);

namespace App\Application\Maintenance;

use App\Shared\Config\DynamicConfig;

final class MaintenanceService
{
    public function __construct(
        private readonly DynamicConfig $config
    ) {
    }

    public function isPublicBlocked(): bool
    {
        return $this->config->get('maintenance_mode', '0') === '1';
    }

    public function areSalesBlocked(): bool
    {
        return $this->config->get('web_compras_habilitadas', '1') !== '1';
    }

    public function getMaintenanceMessage(): string
    {
        return $this->config->get(
            'maintenance_message',
            'El sitio está en mantenimiento. Vuelve pronto.'
        );
    }

    public function getSalesBlockedMessage(): string
    {
        return $this->config->get(
            'web_mensaje_compras_bloqueadas',
            'Las ventas están temporalmente suspendidas.'
        );
    }

    public function allowsAdminAccess(): bool
    {
        return true;
    }
}

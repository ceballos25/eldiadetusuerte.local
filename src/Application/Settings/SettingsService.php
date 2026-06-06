<?php
declare(strict_types=1);

namespace App\Application\Settings;

use App\Shared\Config\DynamicConfig;
use SettingsController as LegacySettingsController;

final class SettingsService
{
    public function __construct(private readonly DynamicConfig $dynamicConfig)
    {
    }

    public function execute(string $action, array $payload): array
    {
        $result = match ($action) {
            'obtener' => LegacySettingsController::obtenerSettings(),
            'actualizar' => LegacySettingsController::actualizarSettings($payload),
            'crear' => LegacySettingsController::crearSetting($payload),
            'eliminar' => LegacySettingsController::eliminarSetting($payload),
            default => ['success' => false, 'message' => 'Accion invalida'],
        };

        if (in_array($action, ['actualizar', 'crear', 'eliminar'], true) && !empty($result['success'])) {
            $this->dynamicConfig->flush();
        }

        return $result;
    }
}

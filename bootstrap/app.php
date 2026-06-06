<?php
declare(strict_types=1);

use App\Application\Audit\AuditService;
use App\Application\Ticketing\TicketSalesService;
use App\Infrastructure\Repository\LegacySalesRepository;
use App\Infrastructure\Repository\PdoAdminRepository;
use App\Infrastructure\Repository\PdoAuditQueryRepository;
use App\Infrastructure\Repository\PdoRaffleRepository;
use App\Infrastructure\Repository\PdoSiteImageRepository;
use App\Infrastructure\Repository\PdoWebhookRepository;
use App\Presentation\Http\Controller\AuditHttpController;
use App\Presentation\Http\Controller\ClientesHttpController;
use App\Presentation\Http\Controller\DashboardHttpController;
use App\Presentation\Http\Controller\MetaEventsController;
use App\Presentation\Http\Controller\NumerosHttpController;
use App\Presentation\Http\Controller\RaffleHttpController;
use App\Presentation\Http\Controller\ReportHttpController;
use App\Presentation\Http\Controller\SettingsHttpController;
use App\Presentation\Http\Controller\TransferenciasHttpController;
use App\Presentation\Http\Controller\SiteImageHttpController;
use App\Presentation\Http\Controller\TicketSalesController;
use App\Presentation\Http\Controller\UserHttpController;
use App\Presentation\Http\Controller\WebCheckoutController;
use App\Presentation\Http\Controller\WebhookAdminController;
use App\Presentation\Http\Middleware\CsrfMiddleware;
use App\Presentation\Http\Middleware\PermissionMiddleware;
use App\Presentation\Http\Middleware\RbacMiddleware;
use App\Presentation\Http\Middleware\RateLimitMiddleware;
use App\Application\Analytics\DashboardDbService;
use App\Application\Marketing\MetaEventsService;
use App\Application\Reporting\ReportExportService;
use App\Application\Reporting\ReportSchemaRegistry;
use App\Application\Reporting\SafeReportExecutor;
use App\Application\Reporting\SavedReportRepository;
use App\Application\Settings\SettingsService;
use App\Application\Web\WebCheckoutService;
use App\Shared\Audit\AuditLogger;
use App\Shared\Routing\Router;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/container.php';
require_once __DIR__ . '/../controllers/rifas.controller.php';
require_once __DIR__ . '/../controllers/ventas.controller.php';
require_once __DIR__ . '/../controllers/numeros.controller.php';
require_once __DIR__ . '/../controllers/settings.controller.php';
require_once __DIR__ . '/../controllers/paymentBackupsController.php';
require_once __DIR__ . '/../controllers/openpay.controller.php';
require_once __DIR__ . '/../controllers/transfersController.php';
require_once __DIR__ . '/../controllers/clientes.controller.php';

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

$container = AppContainer::get();
$router = new Router();
$audit = new AuditLogger();
$auditService = $container->audit();
$rbac = new RbacMiddleware();
$permissions = new PermissionMiddleware($container->permissions());
$config = $container->config();
$csrf = new CsrfMiddleware();
$rateLimit = new RateLimitMiddleware();

$router->post('/front/ajax/ventas.ajax.php', new TicketSalesController(
    new TicketSalesService(new LegacySalesRepository(), $container->sales()),
    $csrf,
    $rbac,
    $audit
));
$router->post('/front/ajax/settings.ajax.php', new SettingsHttpController(new SettingsService($config), $rbac, $csrf, $audit));
$router->post('/front/ajax/web.ajax.php', new WebCheckoutController(new WebCheckoutService($config), $audit, $csrf, $rateLimit));
$router->post('/front/ajax/meta.ajax.php', new MetaEventsController(new MetaEventsService(), $audit));

$router->post('/front/ajax/rifas.ajax.php', new RaffleHttpController(
    $container->raffles(),
    new PdoRaffleRepository(),
    $permissions,
    $csrf,
    $audit
));

$router->post('/front/ajax/webhooks.ajax.php', new WebhookAdminController(
    $container->webhooks(),
    new PdoWebhookRepository(),
    $container->openPayWebhookRegistration(),
    $permissions,
    $csrf
));

$router->post('/front/ajax/usuarios.ajax.php', new UserHttpController(
    new PdoAdminRepository(),
    $permissions,
    $csrf,
    $audit
));

$router->post('/front/ajax/auditoria.ajax.php', new AuditHttpController(
    new PdoAuditQueryRepository(),
    $permissions
));

$router->post('/front/ajax/visual.ajax.php', new SiteImageHttpController(
    new PdoSiteImageRepository(),
    $permissions,
    $csrf,
    $auditService
));

$router->post('/front/ajax/clientes.ajax.php', new ClientesHttpController($permissions, $csrf));
$router->post('/front/ajax/numeros.ajax.php', new NumerosHttpController($permissions, $csrf));
$router->post('/front/ajax/transferencias.ajax.php', new TransferenciasHttpController($permissions, $csrf));
$router->post('/front/ajax/reservas.ajax.php', new \App\Presentation\Http\Controller\ReservasHttpController(
    $container->pendingReservations(),
    $permissions
));

$registry = new ReportSchemaRegistry();
$executor = new SafeReportExecutor($registry);
$savedReports = new SavedReportRepository();
$router->post('/front/ajax/dashboard.ajax.php', new DashboardHttpController(new DashboardDbService(), $rbac, $audit));
$router->post(
    '/front/ajax/reports.ajax.php',
    new ReportHttpController($registry, $executor, $savedReports, $permissions, $audit, new ReportExportService())
);

return $router;

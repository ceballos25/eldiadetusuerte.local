<?php
declare(strict_types=1);

namespace App\Presentation\Http\Controller;

use App\Application\Reporting\ReportExportService;
use App\Application\Reporting\ReportSchemaRegistry;
use App\Application\Reporting\SafeReportExecutor;
use App\Application\Reporting\SavedReportRepository;
use App\Presentation\Http\Middleware\PermissionMiddleware;
use App\Shared\Audit\AuditLogger;
use App\Shared\Http\Request;
use App\Shared\Http\Response;
use Throwable;

final class ReportHttpController
{
    private const PERMISSION_BY_ACTION = [
        'schema' => ['reportes.view'],
        'run' => ['reportes.view'],
        'presets' => ['reportes.view'],
        'saved_list' => ['reportes.view'],
        'saved_get' => ['reportes.view'],
        'saved_save' => ['reportes.view'],
        'saved_delete' => ['reportes.view'],
        'export_excel' => ['reportes.export'],
        'export_pdf' => ['reportes.export'],
    ];

    public function __construct(
        private readonly ReportSchemaRegistry $registry,
        private readonly SafeReportExecutor $executor,
        private readonly SavedReportRepository $saved,
        private readonly PermissionMiddleware $permissions,
        private readonly AuditLogger $audit,
        private readonly ReportExportService $exporter
    ) {
    }

    public function __invoke(Request $request): never
    {
        try {
            $action = trim((string)$request->input('action', ''));
            if (!isset(self::PERMISSION_BY_ACTION[$action])) {
                Response::json(['success' => false, 'message' => 'Accion invalida'], 422);
            }
            $this->permissions->authorize($action, self::PERMISSION_BY_ACTION);

            match ($action) {
                'schema' => $this->schema(),
                'presets' => $this->presets(),
                'run' => $this->run($request),
                'saved_list' => $this->savedList(),
                'saved_get' => $this->savedGet($request),
                'saved_save' => $this->savedSave($request),
                'saved_delete' => $this->savedDelete($request),
                'export_excel' => $this->exportExcel($request),
                'export_pdf' => $this->exportPdf($request),
                default => Response::json(['success' => false, 'message' => 'Accion invalida'], 422),
            };
        } catch (Throwable $e) {
            $this->audit->log('reports.error', ['error' => $e->getMessage()]);
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function schema(): never
    {
        $out = [];
        foreach ($this->registry->datasets() as $key => $meta) {
            $out[] = [
                'key' => $key,
                'label' => $meta['label'],
                'fields' => $this->registry->fieldDescriptors($key),
                'date_column' => $meta['date_column'],
            ];
        }
        Response::json(['success' => true, 'datasets' => $out, 'aggregates' => $this->registry->allowedAggregates()]);
    }

    private function presets(): never
    {
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');
        Response::json([
            'success' => true,
            'presets' => [
                [
                    'name' => 'Ventas por día y medio de pago (recaudo)',
                    'spec' => [
                        'dataset' => 'sales_detail',
                        'date_from' => $monthStart,
                        'date_to' => $today,
                        'dimensions' => [
                            ['field' => 'date_sale_day', 'alias' => 'dia'],
                            ['field' => 'payment_method_sale', 'alias' => 'medio_pago'],
                        ],
                        'measures' => [
                            ['fn' => 'SUM', 'field' => 'total_sale', 'alias' => 'total_recaudado'],
                            ['fn' => 'COUNT', 'field' => '*', 'alias' => 'num_ventas'],
                            ['fn' => 'SUM', 'field' => 'quantity_sale', 'alias' => 'tickets_vendidos'],
                        ],
                        'filters' => [],
                        'order_by' => 'total_recaudado',
                        'order_dir' => 'DESC',
                        'limit' => 5000,
                    ],
                ],
                [
                    'name' => 'Ventas por ciudad (tickets)',
                    'spec' => [
                        'dataset' => 'sales_detail',
                        'date_from' => $monthStart,
                        'date_to' => $today,
                        'dimensions' => [['field' => 'city_customer', 'alias' => 'ciudad']],
                        'measures' => [
                            ['fn' => 'SUM', 'field' => 'quantity_sale', 'alias' => 'tickets'],
                            ['fn' => 'SUM', 'field' => 'total_sale', 'alias' => 'total'],
                        ],
                        'filters' => [],
                        'order_by' => 'tickets',
                        'order_dir' => 'DESC',
                        'limit' => 500,
                    ],
                ],
                [
                    'name' => 'Tickets premium / bendecidos por dinámica',
                    'spec' => [
                        'dataset' => 'tickets_detail',
                        'dimensions' => [
                            ['field' => 'title_raffle', 'alias' => 'rifa'],
                            ['field' => 'is_premium_ticket', 'alias' => 'premium'],
                            ['field' => 'is_winner_ticket', 'alias' => 'ganador'],
                        ],
                        'measures' => [['fn' => 'COUNT', 'field' => '*', 'alias' => 'cantidad']],
                        'filters' => [],
                        'order_by' => 'cantidad',
                        'order_dir' => 'DESC',
                        'limit' => 2000,
                    ],
                ],
            ],
        ]);
    }

    private function run(Request $request): never
    {
        $result = $this->executeSpec($request);
        $this->audit->log('reports.run', ['dataset' => $result['spec']['dataset'] ?? '']);
        Response::json(['success' => true] + $result['data']);
    }

    private function exportExcel(Request $request): never
    {
        $result = $this->executeSpec($request);
        $columns = $result['data']['columns'];
        $rows = $result['data']['rows'];
        $title = trim((string)$request->input('title', 'Reporte'));
        $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $title) ?: 'reporte';
        $xml = $this->exporter->toSpreadsheetXml($columns, $rows, $title);

        $this->audit->log('reports.export_excel', ['rows' => count($rows)]);
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        header('Cache-Control: no-store');
        echo "\xEF\xBB\xBF" . $xml;
        exit;
    }

    private function exportPdf(Request $request): never
    {
        $result = $this->executeSpec($request);
        $columns = $result['data']['columns'];
        $rows = $result['data']['rows'];
        $title = trim((string)$request->input('title', 'Reporte'));
        $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $title) ?: 'reporte';
        $html = $this->exporter->toPdfHtml($columns, $rows, $title);
        $pdf = $this->exporter->renderPdf($html);

        $this->audit->log('reports.export_pdf', ['rows' => count($rows)]);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
        header('Cache-Control: no-store');
        echo $pdf;
        exit;
    }

    /**
     * @return array{spec: array<string, mixed>, data: array{columns: list<string>, rows: list<array<string, mixed>>}}
     */
    private function executeSpec(Request $request): array
    {
        $raw = trim((string)$request->input('spec', ''));
        if ($raw === '') {
            Response::json(['success' => false, 'message' => 'Spec JSON requerido'], 422);
        }
        $spec = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($spec)) {
            Response::json(['success' => false, 'message' => 'Spec invalido'], 422);
        }

        return ['spec' => $spec, 'data' => $this->executor->run($spec)];
    }

    private function savedList(): never
    {
        Response::json(['success' => true, 'data' => $this->saved->listAll()]);
    }

    private function savedGet(Request $request): never
    {
        $id = (int)$request->input('id', 0);
        if ($id <= 0) {
            Response::json(['success' => false, 'message' => 'ID requerido'], 422);
        }
        $row = $this->saved->get($id);
        if ($row === null) {
            Response::json(['success' => false, 'message' => 'No encontrado'], 404);
        }
        $spec = json_decode((string)$row['spec_report'], true);
        if (!is_array($spec)) {
            Response::json(['success' => false, 'message' => 'Reporte corrupto en base de datos'], 500);
        }
        Response::json(['success' => true, 'name' => $row['name_report'], 'spec' => $spec]);
    }

    private function savedSave(Request $request): never
    {
        $name = trim((string)$request->input('name', ''));
        $specRaw = (string)$request->input('spec', '');
        if ($name === '' || $specRaw === '') {
            Response::json(['success' => false, 'message' => 'Nombre y spec requeridos'], 422);
        }
        json_decode($specRaw, true, 512, JSON_THROW_ON_ERROR);
        $adminId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $id = $this->saved->save($name, $specRaw, $adminId);
        $this->audit->log('reports.saved', ['id' => $id]);
        Response::json(['success' => true, 'id_saved_report' => $id]);
    }

    private function savedDelete(Request $request): never
    {
        $id = (int)$request->input('id', 0);
        if ($id <= 0) {
            Response::json(['success' => false, 'message' => 'ID requerido'], 422);
        }
        $ok = $this->saved->delete($id);
        Response::json(['success' => $ok]);
    }
}

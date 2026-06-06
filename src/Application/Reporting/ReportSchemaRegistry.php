<?php
declare(strict_types=1);

namespace App\Application\Reporting;

use InvalidArgumentException;

/**
 * Esquema permitido: solo tablas/campos listados aquí pueden usarse en el constructor visual.
 */
final class ReportSchemaRegistry
{
    /** @return array<string, array<string, string>> */
    public function datasets(): array
    {
        return [
            'sales_detail' => [
                'label' => 'Ventas (detalle)',
                'from' => 'sales s
                    LEFT JOIN customers c ON c.id_customer = s.id_customer_sale
                    LEFT JOIN raffles r ON r.id_raffle = s.id_raffle_sale',
                'date_column' => 's.date_created_sale',
                'fields' => [
                    'id_sale' => 's.id_sale',
                    'code_sale' => 's.code_sale',
                    'total_sale' => 's.total_sale',
                    'quantity_sale' => 's.quantity_sale',
                    'payment_method_sale' => 's.payment_method_sale',
                    'date_sale_day' => 'DATE(s.date_created_sale)',
                    'date_created_sale' => 's.date_created_sale',
                    'source_sale' => 's.source_sale',
                    'status_sale' => 's.status_sale',
                    'id_admin_sale' => 's.id_admin_sale',
                    'id_raffle_sale' => 's.id_raffle_sale',
                    'name_customer' => 'c.name_customer',
                    'lastname_customer' => 'c.lastname_customer',
                    'phone_customer' => 'c.phone_customer',
                    'email_customer' => 'c.email_customer',
                    'city_customer' => 'c.city_customer',
                    'department_customer' => 'c.department_customer',
                    'title_raffle' => 'r.title_raffle',
                ],
            ],
            'tickets_detail' => [
                'label' => 'Tickets (detalle)',
                'from' => 'tickets t
                    INNER JOIN raffles r ON r.id_raffle = t.id_raffle_ticket
                    LEFT JOIN customers c ON c.id_customer = t.id_customer_ticket
                    LEFT JOIN sales s ON s.id_sale = t.id_sale_ticket',
                'date_column' => 't.date_updated_ticket',
                'fields' => [
                    'id_ticket' => 't.id_ticket',
                    'number_ticket' => 't.number_ticket',
                    'status_ticket' => 't.status_ticket',
                    'is_winner_ticket' => 't.is_winner_ticket',
                    'is_premium_ticket' => 't.is_premium_ticket',
                    'id_raffle_ticket' => 't.id_raffle_ticket',
                    'id_customer_ticket' => 't.id_customer_ticket',
                    'id_sale_ticket' => 't.id_sale_ticket',
                    'date_ticket_day' => 'DATE(COALESCE(t.date_created_ticket, t.date_updated_ticket))',
                    'date_created_ticket' => 't.date_created_ticket',
                    'date_updated_ticket' => 't.date_updated_ticket',
                    'title_raffle' => 'r.title_raffle',
                    'name_customer' => 'c.name_customer',
                    'lastname_customer' => 'c.lastname_customer',
                    'phone_customer' => 'c.phone_customer',
                    'code_sale' => 's.code_sale',
                    'payment_method_sale' => 's.payment_method_sale',
                    'total_sale' => 's.total_sale',
                ],
            ],
        ];
    }

    /** @return list<array{key: string, label: string, type: string}> */
    public function fieldDescriptors(string $datasetKey): array
    {
        $ds = $this->getDataset($datasetKey);
        $numeric = [
            'id_sale', 'total_sale', 'quantity_sale', 'status_sale', 'id_admin_sale', 'id_raffle_sale',
            'id_ticket', 'status_ticket', 'is_winner_ticket', 'is_premium_ticket', 'id_raffle_ticket',
            'id_customer_ticket', 'id_sale_ticket',
        ];
        $date = ['date_created_sale', 'date_created_ticket', 'date_updated_ticket', 'date_sale_day', 'date_ticket_day'];
        $out = [];
        foreach ($ds['fields'] as $key => $_expr) {
            $type = 'string';
            if (in_array($key, $numeric, true)) {
                $type = 'number';
            }
            if (in_array($key, $date, true)) {
                $type = 'datetime';
            }
            $out[] = ['key' => $key, 'label' => $key, 'type' => $type];
        }
        return $out;
    }

    /** @return array{label: string, from: string, date_column: string, fields: array<string, string>} */
    public function getDataset(string $key): array
    {
        $all = $this->datasets();
        if (!isset($all[$key])) {
            throw new InvalidArgumentException('Dataset no permitido');
        }
        return $all[$key];
    }

    /** Agregaciones permitidas en medidas */
    public function allowedAggregates(): array
    {
        return ['SUM', 'COUNT', 'AVG', 'MIN', 'MAX'];
    }
}

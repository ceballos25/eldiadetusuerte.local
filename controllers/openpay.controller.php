<?php

class OpenPayController
{
    public static function irAOpenPay(array $data)
    {
        if (empty($data['id_payment_backup'])) {
            return ['success' => false, 'message' => 'ID respaldo requerido'];
        }

        $b = Db::fetchOne(
            'SELECT * FROM payment_backups WHERE id_payment_backup = :id LIMIT 1',
            [':id' => (int)$data['id_payment_backup']]
        );

        if (!$b) {
            return ['success' => false, 'message' => 'Respaldo no encontrado'];
        }

        $phoneNumber = preg_replace('/^(\+?57)?0?/', '', (string)$data['phone_customer']);

        $payload = [
            'method' => 'bank_account',
            'amount' => (float)$b->amount_payment_backup,
            'currency' => 'COP',
            'iva' => '0',
            'description' => 'Compra El Día de Tu Suerte',
            'order_id' => $b->code_payment_backup,
            'redirect_url' => OPENPAY_RETURN_URL . '?order_id=' . $b->code_payment_backup,
            'customer' => [
                'name' => $data['name_customer'],
                'last_name' => $data['lastname_customer'],
                'email' => $data['email_customer'],
                'phone_number' => $phoneNumber,
                'requires_account' => false,
                'customer_address' => [
                    'department' => $data['department_customer'] ?? 'N/A',
                    'city' => $data['city_customer'] ?? 'N/A',
                    'additional' => $data['address_customer'] ?? 'N/A',
                ],
            ],
            'device_session_id' => $data['device_session_id'] ?? uniqid('dev_', true),
        ];

        if (!empty($data['metadata'])) {
            $payload['metadata'] = $data['metadata'];
        }

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => OPENPAY_URL . '/charges',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode(OPENPAY_PRIVATE_KEY . ':'),
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $responseData = json_decode((string)$response, true);

        if ($httpCode === 200 || $httpCode === 201) {
            $existingMeta = \App\Application\Marketing\MetaConversionsApi::extractStoredMeta((array)$b);
            if ($existingMeta !== []) {
                $responseData['meta'] = $existingMeta;
            }

            Db::update(
                'payment_backups',
                [
                    'openpay_id_payment_backup' => $responseData['id'] ?? '',
                    'openpay_status_payment_backup' => $responseData['status'] ?? '',
                    'openpay_response_payment_backup' => json_encode($responseData, JSON_UNESCAPED_UNICODE),
                ],
                'id_payment_backup = :id',
                [':id' => (int)$b->id_payment_backup]
            );

            return [
                'success' => true,
                'redirect_url' => $responseData['payment_method']['url'] ?? '',
            ];
        }

        return ['success' => false, 'message' => 'OpenPay error', 'http' => $httpCode];
    }
}

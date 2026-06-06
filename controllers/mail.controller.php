<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailController {

    public static function enviarCorreoVenta(int $idSale): bool {

        $venta = VentasController::consultarVenta($idSale);
        if (!$venta) {
            return false;
        }

        $tickets = VentasController::consultarTicketsVenta($idSale);
        $html = VentasController::generarRecibo($venta, $tickets);
        if (!$html) {
            return false;
        }

        $recipient = $venta->email_customer;
        $recipientName = trim($venta->name_customer . ' ' . $venta->lastname_customer);
        $subject = '🎟️ Confirmación de compra - ' . SITE_NAME . ' - ' . $idSale;

        if (self::sendViaSmtp($recipient, $recipientName, $subject, $html)) {
            return true;
        }

        return self::sendViaSendmail($recipient, $recipientName, $subject, $html);
    }

    /**
     * Prueba SMTP con el .env y perfiles típicos de cPanel (localhost).
     */
    private static function sendViaSmtp(
        string $recipient,
        string $recipientName,
        string $subject,
        string $html
    ): bool {
        $errors = [];

        foreach (self::smtpProfiles() as $profile) {
            $mail = new PHPMailer(true);

            try {
                self::configureBase($mail);
                self::applySmtpProfile($mail, $profile);
                self::fillMessage($mail, $recipient, $recipientName, $subject, $html);
                $mail->send();

                self::logMail(sprintf(
                    'OK SMTP %s:%d/%s',
                    $profile['host'],
                    $profile['port'],
                    $profile['encryption'] ?: 'none'
                ));
                return true;
            } catch (Exception $e) {
                $errors[] = sprintf(
                    '%s:%d/%s — %s',
                    $profile['host'],
                    $profile['port'],
                    $profile['encryption'] ?: 'none',
                    $e->getMessage()
                );
            }
        }

        foreach ($errors as $error) {
            self::logMail($error);
        }

        return false;
    }

    /** Respaldo: Exim/sendmail local en cPanel (sin conexión SMTP remota). */
    private static function sendViaSendmail(
        string $recipient,
        string $recipientName,
        string $subject,
        string $html
    ): bool {
        $mail = new PHPMailer(true);

        try {
            self::configureBase($mail);
            $mail->isSendmail();
            self::fillMessage($mail, $recipient, $recipientName, $subject, $html);
            $mail->send();

            self::logMail('OK sendmail (fallback local)');
            return true;
        } catch (Exception $e) {
            self::logMail('sendmail fallback — ' . $e->getMessage());
            return false;
        }
    }

    /** @return list<array{host: string, port: int, encryption: string}> */
    private static function smtpProfiles(): array
    {
        $candidates = [
            [
                'host' => trim((string)SMTP_HOST),
                'port' => (int)SMTP_PORT ?: 465,
                'encryption' => strtolower(trim((string)SMTP_ENCRYPTION)),
            ],
            ['host' => 'localhost', 'port' => 465, 'encryption' => 'ssl'],
            ['host' => 'localhost', 'port' => 587, 'encryption' => 'tls'],
        ];

        $seen = [];
        $profiles = [];

        foreach ($candidates as $profile) {
            if ($profile['host'] === '') {
                continue;
            }

            $key = strtolower($profile['host']) . ':' . $profile['port'] . ':' . $profile['encryption'];
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $profiles[] = $profile;
        }

        return $profiles;
    }

    private static function configureBase(PHPMailer $mail): void
    {
        $mail->CharSet = 'UTF-8';
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->Timeout = 8;
        $mail->SMTPKeepAlive = false;
    }

    private static function applySmtpProfile(PHPMailer $mail, array $profile): void
    {
        $mail->isSMTP();
        $mail->Host = $profile['host'];
        $mail->Port = $profile['port'];

        $encryption = $profile['encryption'];
        if ($encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPAutoTLS = false;
            $mail->SMTPSecure = '';
        }

        // cPanel: certificado del host (host11...) no coincide con localhost.
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];
    }

    private static function fillMessage(
        PHPMailer $mail,
        string $recipient,
        string $recipientName,
        string $subject,
        string $html
    ): void {
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($recipient, $recipientName);

        if (MAIL_BCC) {
            $mail->addBCC(MAIL_BCC);
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
    }

    private static function logMail(string $message): void
    {
        if (function_exists('writeAppLog')) {
            writeAppLog('mail.log', $message);
            return;
        }

        @file_put_contents(
            __DIR__ . '/../logs/mail.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL,
            FILE_APPEND
        );
    }
}

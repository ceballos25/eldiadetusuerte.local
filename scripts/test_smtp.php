<?php
/**
 * Prueba envío de correo en producción.
 * Uso: php scripts/test_smtp.php tu@email.com
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$to = $argv[1] ?? '';
if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: php scripts/test_smtp.php destino@email.com\n");
    exit(1);
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/ventas.controller.php';

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

echo "SMTP_HOST=" . SMTP_HOST . " PORT=" . SMTP_PORT . " ENC=" . SMTP_ENCRYPTION . "\n";
echo "SMTP_USER=" . SMTP_USER . "\n";
echo "Enviando prueba a {$to}...\n";

$profiles = [
    [
        'host' => trim((string)SMTP_HOST),
        'port' => (int)SMTP_PORT ?: 465,
        'encryption' => strtolower(trim((string)SMTP_ENCRYPTION)),
        'label' => '.env',
    ],
    ['host' => 'localhost', 'port' => 465, 'encryption' => 'ssl', 'label' => 'localhost:465'],
    ['host' => 'localhost', 'port' => 587, 'encryption' => 'tls', 'label' => 'localhost:587'],
];

$seen = [];
$sent = false;

foreach ($profiles as $profile) {
    if ($profile['host'] === '') {
        continue;
    }

    $key = strtolower($profile['host']) . ':' . $profile['port'] . ':' . $profile['encryption'];
    if (isset($seen[$key])) {
        continue;
    }
    $seen[$key] = true;

    $mail = new PHPMailer(true);

    try {
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host = $profile['host'];
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->Port = $profile['port'];
        $mail->Timeout = 8;

        if ($profile['encryption'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($profile['encryption'] === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPAutoTLS = false;
            $mail->SMTPSecure = '';
        }

        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = 'Prueba SMTP El Día de Tu Suerte — ' . $profile['label'];
        $mail->Body = '<p>Prueba OK vía <strong>' . htmlspecialchars($profile['label']) . '</strong></p>';

        $mail->send();
        echo "OK: {$profile['label']}\n";
        $sent = true;
        break;
    } catch (Exception $e) {
        echo "FAIL {$profile['label']}: {$e->getMessage()}\n";
    }
}

if (!$sent) {
    echo "Probando sendmail...\n";
    $mail = new PHPMailer(true);
    try {
        $mail->CharSet = 'UTF-8';
        $mail->isSendmail();
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = 'Prueba sendmail El Día de Tu Suerte';
        $mail->Body = '<p>Prueba OK vía <strong>sendmail</strong></p>';
        $mail->send();
        echo "OK: sendmail\n";
        $sent = true;
    } catch (Exception $e) {
        echo "FAIL sendmail: {$e->getMessage()}\n";
    }
}

exit($sent ? 0 : 1);

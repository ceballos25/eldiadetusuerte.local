<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

$url = cr_site_favicon_url();
header('Location: ' . $url, true, 302);
header('Cache-Control: public, max-age=3600');
exit;

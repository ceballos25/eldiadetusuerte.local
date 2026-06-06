<!doctype html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['csrf_token'])): ?>
  <meta name="csrf-token" content="<?= htmlspecialchars((string)$_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
  <script>window.APP_CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'], JSON_UNESCAPED_UNICODE) ?>;</script>
  <?php endif; ?>
  <title><?php echo isset($page_title) ? $page_title . ' - ' . SITE_NAME : 'El Día de Tu Suerte 🍀'; ?></title>
  <link rel="icon" type="image/png" href="<?= cr_site_favicon_href() ?>" data-site-favicon />
  <link rel="shortcut icon" type="image/png" href="<?= cr_site_favicon_href() ?>" data-site-favicon />
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/app.css?v=49" />
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/styles.min.css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/alertify.min.css"/>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/themes/bootstrap.min.css"/>

<script src="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/alertify.min.js"></script>

  <?php if(isset($extra_css)) echo $extra_css; ?>
</head>

<body>
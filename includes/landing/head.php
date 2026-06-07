<?php
$edtsTitle = edts_site_display_name() . ' 🍀';
$edtsDescription = 'Participa en las dinámicas oficiales de El Día de Tu Suerte. Compra segura, confirmación inmediata y experiencias únicas en Colombia.';
$edtsOgImage = edts_cdn('images/logos/logo.jpg');
$edtsCanonical = edts_public_url();
?>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['csrf_token'])): ?>
    <meta name="csrf-token" content="<?= htmlspecialchars((string)$_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>

    <title><?= htmlspecialchars($edtsTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="<?= htmlspecialchars($edtsDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= htmlspecialchars($edtsCanonical, ENT_QUOTES, 'UTF-8') ?>">

    <meta property="og:type" content="website">
    <meta property="og:locale" content="es_CO">
    <meta property="og:site_name" content="<?= htmlspecialchars(edts_site_name(), ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:title" content="<?= htmlspecialchars($edtsTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($edtsDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:url" content="<?= htmlspecialchars($edtsCanonical, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image" content="<?= htmlspecialchars($edtsOgImage, ENT_QUOTES, 'UTF-8') ?>">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($edtsTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($edtsDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($edtsOgImage, ENT_QUOTES, 'UTF-8') ?>">

    <meta name="theme-color" content="#16a34a">

    <link rel="icon" type="image/png" href="<?= cr_site_favicon_href() ?>" data-site-favicon>
    <link rel="shortcut icon" type="image/png" href="<?= cr_site_favicon_href() ?>" data-site-favicon>
    <link rel="preconnect" href="https://cdn.eldiadetusuerte.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preload" href="<?= htmlspecialchars(ASSETS_URL . '/css/app.css', ENT_QUOTES, 'UTF-8') ?>?v=58" as="style">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.4/dist/css/splide.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= htmlspecialchars(ASSETS_URL . '/css/app.css', ENT_QUOTES, 'UTF-8') ?>?v=58">

    <?php edts_meta_pixel_head(); ?>
</head>

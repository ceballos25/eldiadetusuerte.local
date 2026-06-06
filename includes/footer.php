<script src="<?= ASSETS_URL ?>/libs/jquery/dist/jquery.min.js"></script>
  <script src="<?= ASSETS_URL ?>/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="<?= ASSETS_URL ?>/js/sidebarmenu.js"></script>
  <script src="<?= ASSETS_URL ?>/js/app.min.js"></script>
  <script src="<?= ASSETS_URL ?>/libs/simplebar/dist/simplebar.js"></script>
  <script>
    window.APP_CSRF_TOKEN = window.APP_CSRF_TOKEN || <?= json_encode($_SESSION['csrf_token'] ?? '', JSON_UNESCAPED_UNICODE) ?>;
  </script>
  <?php include_once "pagination_script.php"; ?>
  <?php include_once "preloader.php"; ?>
  <?php include_once "btn-share.php"; ?>
  <script src="<?= ASSETS_URL ?>/js/admin-brand.js?v=2"></script>
  <script src="<?= ASSETS_URL ?>/js/admin-fetch.js?v=4"></script>
  <?php if(isset($extra_js)) echo $extra_js; ?>

</body>
</html>
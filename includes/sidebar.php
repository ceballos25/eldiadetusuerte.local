<?php
// Verificar si el usuario está logueado
$isLoggedIn = isset($_SESSION['user_id']);
$userRole   = strtolower((string)($_SESSION['user_role'] ?? 'vendedor'));
$isAdmin    = in_array($userRole, ['admin', 'administrador', 'superadmin'], true);

// Helper para marcar activo por página
$currentPage = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

function isActive($fileName, $currentPage) {
  return $currentPage === $fileName ? 'active' : '';
}
function isOpen($files, $currentPage) {
  return in_array($currentPage, $files) ? 'in' : '';
}
?>

<aside class="left-sidebar">
  <div class="sidebar-shell">
    <div class="brand-logo d-flex align-items-center justify-content-between">
      <a href="dashboard.php" class="text-nowrap logo-img" style="display:flex; justify-content:center; width:100%;">
        <img style="width:50%; margin-top:10px;" class="d-flex" data-site-logo-white src="<?= htmlspecialchars(edts_cdn('images/logos/logo-blanco.jpg'), ENT_QUOTES, 'UTF-8') ?>" alt="<?php echo SITE_NAME; ?>" loading="lazy" />
      </a>
      <div class="close-btn d-xl-none d-block sidebartoggler cursor-pointer" id="sidebarCollapse">
        <i class="ti ti-x fs-6"></i>
      </div>
    </div>

    <nav class="sidebar-nav scroll-sidebar">
      <ul id="sidebarnav">

        <li class="nav-small-cap">
          <iconify-icon icon="solar:menu-dots-linear" class="nav-small-cap-icon fs-4"></iconify-icon>
          <span class="hide-menu">Principal</span>
        </li>

        <li class="sidebar-item <?= isActive('dashboard.php', $currentPage); ?>">
          <a class="sidebar-link" href="dashboard.php" aria-expanded="false">
            <i class="ti ti-home"></i>
            <span class="hide-menu">Dashboard</span>
          </a>
        </li>

        <li class="sidebar-item <?= isActive('vender.php', $currentPage); ?>">
          <a class="sidebar-link" href="vender.php" aria-expanded="false">
            <i class="ti ti-shopping-cart"></i>
            <span class="hide-menu">Vender</span>
          </a>
        </li>
        
        <li class="sidebar-item <?= isActive('transferencias.php', $currentPage); ?>">
          <a class="sidebar-link" href="transferencias.php" aria-expanded="false">
            <i class="ti ti-building-bank"></i>
            <span class="hide-menu">Transferencias</span>
          </a>
        </li>

        <li><span class="sidebar-divider lg"></span></li>

        <li class="nav-small-cap">
          <iconify-icon icon="solar:menu-dots-linear" class="nav-small-cap-icon fs-4"></iconify-icon>
          <span class="hide-menu">Terceros</span>
        </li>

        <li class="sidebar-item <?= isActive('clientes.php', $currentPage); ?>">
          <a class="sidebar-link" href="clientes.php" aria-expanded="false">
            <i class="ti ti-users"></i>
            <span class="hide-menu">Clientes</span>
          </a>
        </li>

        <li><span class="sidebar-divider lg"></span></li>

        <li class="nav-small-cap">
          <iconify-icon icon="solar:menu-dots-linear" class="nav-small-cap-icon fs-4"></iconify-icon>
          <span class="hide-menu">Ventas y Nros</span>
        </li>

        <?php
          // Solo páginas que viven bajo "Ventas & Números" (transferencias.php es ítem aparte arriba).
          $prodPages = [
            'ventas.php', 'numeros-vendidos.php', 'numeros.php',
          ];
        ?>
        <li class="sidebar-item">
          <a class="sidebar-link justify-content-between has-arrow" href="javascript:void(0)" aria-expanded="false">
            <div class="d-flex align-items-center gap-3">
              <span class="d-flex"><i class="ti ti-box"></i></span>
              <span class="hide-menu">Ventas & Nros</span>
            </div>
          </a>

          <ul aria-expanded="false" class="collapse first-level <?= isOpen($prodPages, $currentPage); ?>">

            <li class="sidebar-item <?= isActive('ventas.php', $currentPage); ?>">
              <a class="sidebar-link" href="ventas.php">
                <div class="round-16 d-flex align-items-center justify-content-center"><i class="ti ti-circle"></i></div>
                <span class="hide-menu">Ventas</span>
              </a>
            </li>

            <li class="sidebar-item <?= isActive('numeros-vendidos.php', $currentPage); ?>">
              <a class="sidebar-link" href="numeros-vendidos.php">
                <div class="round-16 d-flex align-items-center justify-content-center"><i class="ti ti-circle"></i></div>
                <span class="hide-menu">Nros Vendidos</span>
              </a>
            </li>

            <li class="sidebar-item <?= isActive('numeros.php', $currentPage); ?>">
              <a class="sidebar-link" href="numeros.php">
                <div class="round-16 d-flex align-items-center justify-content-center"><i class="ti ti-circle"></i></div>
                <span class="hide-menu">Nros Disponibles</span>
              </a>
            </li>

            <?php if ($isAdmin): ?>

            <?php endif; ?>
          </ul>
        </li>

        <li><span class="sidebar-divider lg"></span></li>

        <li class="nav-small-cap">
          <iconify-icon icon="solar:menu-dots-linear" class="nav-small-cap-icon fs-4"></iconify-icon>
          <span class="hide-menu">Configuración</span>
        </li>

        <li class="sidebar-item <?= isActive('rifas.php', $currentPage); ?>">
          <a class="sidebar-link" href="rifas.php" aria-expanded="false">
            <i class="ti ti-ticket"></i>
            <span class="hide-menu">Rifas</span>
          </a>          
        </li>


        <?php if ($isAdmin): ?>
        <li class="sidebar-item <?= isActive('usuarios.php', $currentPage); ?>">
          <a class="sidebar-link" href="usuarios.php"><i class="ti ti-user-shield"></i><span class="hide-menu">Usuarios</span></a>
        </li>
        <li class="sidebar-item <?= isActive('auditoria.php', $currentPage); ?>">
          <a class="sidebar-link" href="auditoria.php"><i class="ti ti-history"></i><span class="hide-menu">Auditoría</span></a>
        </li>
        <li class="sidebar-item <?= isActive('configuracion-visual.php', $currentPage); ?>">
          <a class="sidebar-link" href="configuracion-visual.php"><i class="ti ti-photo"></i><span class="hide-menu">Config. Visual</span></a>
        </li>
        <li class="sidebar-item <?= isActive('webhooks.php', $currentPage); ?>">
          <a class="sidebar-link" href="webhooks.php"><i class="ti ti-webhook"></i><span class="hide-menu">Webhooks</span></a>
        </li>
        <?php endif; ?>

        <li class="sidebar-item <?= isActive('settings.php', $currentPage); ?>">
          <a class="sidebar-link" href="settings.php" aria-expanded="false">
            <i class="ti ti-settings"></i>
            <span class="hide-menu">Ajustes</span>
          </a>          
        </li>
        
        <li class="sidebar-item <?= isActive('reportes.php', $currentPage); ?>">
          <a class="sidebar-link" href="reportes.php" aria-expanded="false">
            <i class="ti ti-file-report"></i>
            <span class="hide-menu">Reportería</span>
          </a>
        </li>     

        <li><span class="sidebar-divider lg"></span></li>

      </ul>
    </nav>
  </div>
</aside>

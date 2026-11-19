<?php
use App\Core\Auth;

$user = Auth::user();
$isSuper = $user['role'] === 'superadmin';
$menu = [
    ['dashboard', 'admin',      'bi-grid-1x2',        'Dashboard'],
    ['kucing',    'admin/kucing',   'bi-card-list',      'Data Kucing'],
    ['feeding',   'admin/feeding',  'bi-egg-fried',   'Feeding Log'],
    ['kesehatan', 'admin/kesehatan', 'bi-heart-pulse', 'Kesehatan'],
    ['adopsi',    'admin/adopsi',   'bi-clipboard-check', 'Pengajuan Adopsi'],
    ['pasca',     'admin/pasca-adopsi', 'bi-house-heart', 'Pasca-Adopsi'],
    ['artikel',   'admin/artikel',  'bi-newspaper',   'Artikel (CMS)'],
];
if ($isSuper) {
    $menu[] = ['superadmin', 'admin/superadmin', 'bi-shield-lock', 'Super Admin'];
}
?>
<!doctype html>
<html lang="id">
<head><?php require __DIR__ . '/_head.php'; ?></head>
<body class="bg-cream mn-admin">
<div class="d-flex">
    <aside class="mn-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="sidebar">
        <div class="p-3 d-flex align-items-center justify-content-between">
            <a href="<?= url() ?>"><img src="<?= asset('img/brand/meownesa-logo.png') ?>" alt="MeowNesa" height="34"></a>
            <button class="btn-close d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Tutup"></button>
        </div>
        <nav class="nav flex-column px-2 gap-1">
            <?php foreach ($menu as [$key, $path, $icon, $label]): ?>
                <a class="nav-link mn-side-link <?= ($active ?? '') === $key ? 'active' : '' ?>" href="<?= url($path) ?>">
                    <i class="bi <?= $icon ?> me-2"></i><?= e($label) ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="mt-auto p-3 border-top">
            <div class="small fw-semibold text-truncate"><?= e($user['nama']) ?></div>
            <div class="small text-muted mb-2"><?= $isSuper ? 'Super Admin' : 'Admin Jaga Satwa' ?></div>
            <form method="post" action="<?= url('logout') ?>">
                <?= csrf_field() ?>
                <button class="btn btn-outline-brand btn-sm w-100"><i class="bi bi-box-arrow-right me-1"></i>Keluar</button>
            </form>
        </div>
    </aside>

    <div class="flex-grow-1 min-vw-0">
        <header class="mn-topbar d-flex align-items-center gap-3 px-3 px-lg-4">
            <button class="btn btn-light d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-label="Menu">
                <i class="bi bi-list fs-5"></i>
            </button>
            <h1 class="h5 mb-0 fw-bold"><?= e($title ?? '') ?></h1>
            <a class="btn btn-sm btn-outline-secondary ms-auto" href="<?= url() ?>" target="_blank"><i class="bi bi-box-arrow-up-right me-1"></i>Lihat situs</a>
        </header>
        <main class="p-3 p-lg-4">
            <?php if ($m = flash('success')): ?><div class="alert alert-success"><?= e($m) ?></div><?php endif; ?>
            <?php if ($m = flash('error')): ?><div class="alert alert-danger"><?= e($m) ?></div><?php endif; ?>
            <?= $content ?>
        </main>
    </div>
</div>
<?php require __DIR__ . '/_scripts.php'; ?>
</body>
</html>

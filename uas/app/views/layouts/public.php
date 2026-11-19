<?php
$nav = [
    ''        => 'Beranda',
    'kucing'  => 'Katalog Kucing',
];
$current = trim(substr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), strlen(base_path())), '/');
?>
<!doctype html>
<html lang="id">
<head><?php require __DIR__ . '/_head.php'; ?></head>
<body class="bg-cream">
<nav class="navbar navbar-expand-lg sticky-top mn-navbar">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= url() ?>">
            <img src="<?= asset('img/brand/meownesa-logo.png') ?>" alt="MeowNesa" height="36">
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-label="Menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                <?php foreach ($nav as $path => $label): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= ($current === $path || ($path !== '' && str_starts_with($current, $path))) ? 'active' : '' ?>"
                           href="<?= url($path) ?>"><?= e($label) ?></a>
                    </li>
                <?php endforeach; ?>
                <li class="nav-item ms-lg-2">
                    <?php if (\App\Core\Auth::check()): ?>
                        <a class="btn btn-brand btn-sm" href="<?= url(\App\Core\Auth::homeFor(\App\Core\Auth::user()['role'])) ?>">
                            <i class="bi bi-speedometer2 me-1"></i>Dashboard
                        </a>
                    <?php else: ?>
                        <a class="btn btn-outline-brand btn-sm" href="<?= url('login') ?>"><i class="bi bi-box-arrow-in-right me-1"></i>Masuk Admin</a>
                    <?php endif; ?>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main>
    <?php if ($m = flash('success')): ?><div class="container mt-3"><div class="alert alert-success mb-0"><?= e($m) ?></div></div><?php endif; ?>
    <?php if ($m = flash('error')): ?><div class="container mt-3"><div class="alert alert-danger mb-0"><?= e($m) ?></div></div><?php endif; ?>
    <?= $content ?>
</main>

<footer class="mn-footer mt-5">
    <div class="container py-4 d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-center gap-3">
            <img src="<?= asset('img/brand/jagasatwa-logo.png') ?>" alt="Jaga Satwa UNESA" height="40">
            <small>Dikelola oleh Jaga Satwa UNESA · Kampus Ketintang &amp; Lidah Wetan</small>
        </div>
        <small class="text-muted">&copy; <?= date('Y') ?> MeowNesa — Kelompok 7, Pemrograman Web</small>
    </div>
</footer>
<?php require __DIR__ . '/_scripts.php'; ?>
</body>
</html>
